<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Revisions;

use InvoiceService\Documents\InvoiceOfferProfile;
use InvoiceService\Domain\BuyerRequisites;
use InvoiceService\Domain\DealProduct;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Domain\InvoiceSource;
use InvoiceService\Domain\InvoiceSourceValidator;
use InvoiceService\Domain\Money;
use InvoiceService\Revisions\InMemoryInvoiceRevisionRepository;
use InvoiceService\Revisions\InvoiceRevision;
use PHPUnit\Framework\TestCase;

final class InvoiceRevisionRepositoryTest extends TestCase
{
    public function testSameSnapshotKeepsNumberAndChangedSnapshotConsumesExactlyOneNewGlobalNumber(): void
    {
        $repository = new InMemoryInvoiceRevisionRepository();
        $first = $repository->reserve(7, $this->snapshot(1));
        $repeat = $repository->reserve(7, $this->snapshot(1));
        $changed = $repository->reserve(7, $this->snapshot(2));

        self::assertTrue($first->created);
        self::assertFalse($repeat->created);
        self::assertSame('ЛЦ-АМ-28457194-000001', $first->revision->invoiceNumber);
        self::assertSame($first->revision->invoiceNumber, $repeat->revision->invoiceNumber);
        self::assertSame('ЛЦ-АМ-28457194-000002', $changed->revision->invoiceNumber);
    }

    public function testHistoricalCompletedSnapshotGetsNewNumberButCurrentCompletedSnapshotIsReused(): void
    {
        $repository = new InMemoryInvoiceRevisionRepository();
        $first = $this->complete($repository, $repository->reserve(7, $this->snapshot(1))->revision);

        $sameCurrent = $repository->reserve(7, $this->snapshot(1));
        self::assertFalse($sameCurrent->created);
        self::assertSame($first->invoiceNumber, $sameCurrent->revision->invoiceNumber);

        $second = $this->complete($repository, $repository->reserve(7, $this->snapshot(2))->revision);
        $historicalSnapshot = $repository->reserve(7, $this->snapshot(1));

        self::assertTrue($historicalSnapshot->created);
        self::assertSame('ЛЦ-АМ-28457194-000003', $historicalSnapshot->revision->invoiceNumber);
        self::assertSame($second->id, $repository->currentId(7, 28457194));
    }

    public function testValidationBlockRemovesCurrentPointerWithoutDeletingHistory(): void
    {
        $repository = new InMemoryInvoiceRevisionRepository();
        $reserved = $repository->reserve(7, $this->snapshot(1));
        $current = $this->complete($repository, $reserved->revision);
        self::assertSame($current->id, $repository->currentId(7, 28457194));

        $repository->markValidationBlocked(7, 28457194, ['Не заполнено поле компании: ИНН.']);

        self::assertNull($repository->currentId(7, 28457194));
    }

    public function testAmbiguousEffectKeepsExistingCurrentRevisionAndCannotReserveAgain(): void
    {
        $repository = new InMemoryInvoiceRevisionRepository();
        $current = $this->complete($repository, $repository->reserve(7, $this->snapshot(1))->revision);
        $next = $repository->reserve(7, $this->snapshot(2))->revision;
        $rendered = $repository->markRendered($next, '/tmp/invoice.docx', '/tmp/invoice.pdf', hash('sha256', 'invoice'));
        $uploading = $repository->beginUpload($rendered);
        $uploaded = $repository->markUploaded($uploading, '6f4045e5-01d0-4a31-aaf4-69a907f0975f');
        $manual = $repository->markManualReconciliation($repository->beginAttachment($uploaded));

        $retry = $repository->reserve(7, $this->snapshot(3));

        self::assertSame('manual_reconciliation_required', $manual->status);
        self::assertFalse($retry->created);
        self::assertSame($manual->id, $retry->revision->id);
        self::assertSame($current->id, $repository->currentId(7, 28457194));
    }

    private function snapshot(int $quantity): InvoiceSnapshot
    {
        $profile = InvoiceOfferProfile::sonicIpV1();
        return InvoiceSnapshot::fromSource(new InvoiceSource(7, 28457194, new BuyerRequisites(
            'ООО Покупатель', '7701000000', '123456789', '1027700000000', 'г. Москва, ул. Пример, 1',
            '40702810000000000001', 'АО Банк', '30101810000000000001', '044525000',
        ), [new DealProduct('Лицензия amoCRM', Money::fromDecimal('1990'), $quantity, true)], $profile->version), new InvoiceSourceValidator());
    }

    private function complete(InMemoryInvoiceRevisionRepository $repository, InvoiceRevision $revision): InvoiceRevision
    {
        $rendered = $repository->markRendered($revision, '/tmp/invoice.docx', '/tmp/invoice.pdf', hash('sha256', $revision->id));
        $uploading = $repository->beginUpload($rendered);
        $uploaded = $repository->markUploaded($uploading, '6f4045e5-01d0-4a31-aaf4-69a907f0975f');
        $attaching = $repository->beginAttachment($uploaded);
        $attached = $repository->markAttached($attaching);
        $noting = $repository->beginCurrentNote($attached);

        return $repository->markCurrent($noting);
    }
}
