<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Services;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use InvoiceService\AmoCRM\AccountRecord;
use InvoiceService\AmoCRM\ConnectedAccount;
use InvoiceService\AmoCRM\InMemoryAccountRepository;
use InvoiceService\AmoCRM\InvoicePublisher;
use InvoiceService\AmoCRM\InvoiceSourceReadException;
use InvoiceService\AmoCRM\InvoiceSourceReader;
use InvoiceService\AmoCRM\OAuthToken;
use InvoiceService\Documents\InvoiceDocumentRenderer;
use InvoiceService\Documents\InvoiceOfferDocumentData;
use InvoiceService\Documents\InvoiceOfferProfile;
use InvoiceService\Documents\RenderedInvoiceDocument;
use InvoiceService\Domain\BuyerRequisites;
use InvoiceService\Domain\DealProduct;
use InvoiceService\Domain\InvoiceSource;
use InvoiceService\Domain\InvoiceSourceValidator;
use InvoiceService\Domain\Money;
use InvoiceService\Jobs\InvoiceJob;
use InvoiceService\Revisions\InMemoryInvoiceRevisionRepository;
use InvoiceService\Revisions\InvoiceRevision;
use InvoiceService\Services\InvoiceGenerationService;
use PHPUnit\Framework\TestCase;

final class InvoiceGenerationServiceTest extends TestCase
{
    public function testGeneratesOnceForSameSnapshotAndCreatesNextNumberForChangedData(): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('access', 'refresh', new DateTimeImmutable('+1 hour'))));
        $source = new class($this->source(1)) implements InvoiceSourceReader {
            public function __construct(public InvoiceSource $source) {}
            public function read(AccountRecord $account, int $leadId): InvoiceSource { return $this->source; }
        };
        $renderer = new class implements InvoiceDocumentRenderer {
            public int $calls = 0;
            public function render(InvoiceOfferDocumentData $data, InvoiceRevision $revision): RenderedInvoiceDocument { ++$this->calls; return new RenderedInvoiceDocument('/tmp/invoice.docx', '/tmp/invoice.pdf', hash('sha256', $revision->id)); }
        };
        $publisher = new class implements InvoicePublisher {
            public int $uploads = 0;
            public int $attachments = 0;
            public int $currentNotes = 0;
            public function upload(AccountRecord $account, InvoiceRevision $revision, string $pdfPath): string { ++$this->uploads; return '6f4045e5-01d0-4a31-aaf4-69a907f0975f'; }
            public function attach(AccountRecord $account, InvoiceRevision $revision): void { ++$this->attachments; }
            public function noteValidationBlocked(AccountRecord $account, int $leadId, array $reasons): void {}
            public function noteCurrentInvoice(AccountRecord $account, InvoiceRevision $revision): void { ++$this->currentNotes; }
        };
        $service = new InvoiceGenerationService($accounts, $source, new InvoiceSourceValidator(), new InMemoryInvoiceRevisionRepository(), $renderer, InvoiceOfferProfile::sonicIpV1(), $publisher);

        $first = $service->generate($this->job($account->id));
        $second = $service->generate($this->job($account->id));
        $source->source = $this->source(2);
        $third = $service->generate($this->job($account->id));

        self::assertSame('generated', $first->status);
        self::assertSame('ЛЦ-АМ-28457194-000001', $first->invoiceNumber);
        self::assertSame('unchanged', $second->status);
        self::assertSame('ЛЦ-АМ-28457194-000002', $third->invoiceNumber);
        self::assertSame(2, $renderer->calls);
        self::assertSame(2, $publisher->uploads);
        self::assertSame(2, $publisher->attachments);
        self::assertSame(2, $publisher->currentNotes);
    }

    public function testSourceReadExceptionBlocksCurrentInvoiceAndLeavesValidationNote(): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('access', 'refresh', new DateTimeImmutable('+1 hour'))));
        $source = new class($this->source(1)) implements InvoiceSourceReader {
            public ?InvoiceSourceReadException $failure = null;

            public function __construct(public InvoiceSource $source) {}

            public function read(AccountRecord $account, int $leadId): InvoiceSource
            {
                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->source;
            }
        };
        $renderer = new class implements InvoiceDocumentRenderer {
            public function render(InvoiceOfferDocumentData $data, InvoiceRevision $revision): RenderedInvoiceDocument
            {
                return new RenderedInvoiceDocument('/tmp/invoice.docx', '/tmp/invoice.pdf', hash('sha256', $revision->id));
            }
        };
        $publisher = new class implements InvoicePublisher {
            /** @var list<string> */
            public array $validationReasons = [];

            public int $validationNotes = 0;

            public function upload(AccountRecord $account, InvoiceRevision $revision, string $pdfPath): string { return '6f4045e5-01d0-4a31-aaf4-69a907f0975f'; }
            public function attach(AccountRecord $account, InvoiceRevision $revision): void {}
            public function noteValidationBlocked(AccountRecord $account, int $leadId, array $reasons): void { ++$this->validationNotes; $this->validationReasons = $reasons; }
            public function noteCurrentInvoice(AccountRecord $account, InvoiceRevision $revision): void {}
        };
        $revisions = new InMemoryInvoiceRevisionRepository();
        $service = new InvoiceGenerationService($accounts, $source, new InvoiceSourceValidator(), $revisions, $renderer, InvoiceOfferProfile::sonicIpV1(), $publisher);

        $service->generate($this->job($account->id));
        self::assertNotNull($revisions->currentId($account->id, 28457194));
        $source->failure = new InvoiceSourceReadException('Цена лицензии должна быть больше нуля.');

        $result = $service->generate($this->job($account->id));

        self::assertSame('validation_blocked', $result->status);
        self::assertNull($revisions->currentId($account->id, 28457194));
        self::assertSame(['Цена лицензии должна быть больше нуля.'], $publisher->validationReasons);
        $service->generate($this->job($account->id));
        self::assertSame(1, $publisher->validationNotes);
    }

    #[DataProvider('ambiguousEffects')]
    public function testDoesNotRepeatAnAmbiguousExternalEffect(string $effect, int $expectedUploads, int $expectedAttachments, int $expectedNotes): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('access', 'refresh', new DateTimeImmutable('+1 hour'))));
        $source = new class($this->source(1)) implements InvoiceSourceReader {
            public function __construct(public InvoiceSource $source) {}
            public function read(AccountRecord $account, int $leadId): InvoiceSource { return $this->source; }
        };
        $renderer = new class implements InvoiceDocumentRenderer {
            public function render(InvoiceOfferDocumentData $data, InvoiceRevision $revision): RenderedInvoiceDocument { return new RenderedInvoiceDocument('/tmp/invoice.docx', '/tmp/invoice.pdf', hash('sha256', $revision->id)); }
        };
        $publisher = new class($effect) implements InvoicePublisher {
            public int $uploads = 0;
            public int $attachments = 0;
            public int $currentNotes = 0;
            public function __construct(private string $effect) {}
            public function upload(AccountRecord $account, InvoiceRevision $revision, string $pdfPath): string { ++$this->uploads; if ($this->effect === 'upload') { throw new \RuntimeException('ambiguous upload'); } return '6f4045e5-01d0-4a31-aaf4-69a907f0975f'; }
            public function attach(AccountRecord $account, InvoiceRevision $revision): void { ++$this->attachments; if ($this->effect === 'attach') { throw new \RuntimeException('ambiguous attachment'); } }
            public function noteValidationBlocked(AccountRecord $account, int $leadId, array $reasons): void {}
            public function noteCurrentInvoice(AccountRecord $account, InvoiceRevision $revision): void { ++$this->currentNotes; if ($this->effect === 'note') { throw new \RuntimeException('ambiguous note'); } }
        };
        $service = new InvoiceGenerationService($accounts, $source, new InvoiceSourceValidator(), new InMemoryInvoiceRevisionRepository(), $renderer, InvoiceOfferProfile::sonicIpV1(), $publisher);

        try {
            $service->generate($this->job($account->id));
            self::fail('Expected the simulated external-effect failure.');
        } catch (\RuntimeException) {
        }
        $retry = $service->generate($this->job($account->id));

        self::assertSame('manual_reconciliation_required', $retry->status);
        self::assertSame('ЛЦ-АМ-28457194-000001', $retry->invoiceNumber);
        self::assertSame($expectedUploads, $publisher->uploads);
        self::assertSame($expectedAttachments, $publisher->attachments);
        self::assertSame($expectedNotes, $publisher->currentNotes);
    }

    /** @return iterable<string, array{string, int, int, int}> */
    public static function ambiguousEffects(): iterable
    {
        yield 'upload' => ['upload', 1, 0, 0];
        yield 'attachment' => ['attach', 1, 1, 0];
        yield 'current note' => ['note', 1, 1, 1];
    }

    private function job(int $accountId): InvoiceJob
    {
        return new InvoiceJob('6f4045e5-01d0-4a31-aaf4-69a907f0975f', '0f4045e5-01d0-4a31-aaf4-69a907f0975f', $accountId, 28457194, 'automatic', hash('sha256', 'payload'));
    }

    private function source(int $quantity): InvoiceSource
    {
        return new InvoiceSource(10, 28457194, new BuyerRequisites('ООО Покупатель', '7701000000', '123456789', '1027700000000', 'г. Москва, ул. Пример, 1', '40702810000000000001', 'АО Банк', '30101810000000000001', '044525000'), [new DealProduct('Лицензия amoCRM', Money::fromDecimal('1990'), $quantity, true)], InvoiceOfferProfile::sonicIpV1()->version);
    }
}
