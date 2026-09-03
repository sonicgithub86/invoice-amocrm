<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Document;

use DateTimeImmutable;
use InvoiceService\Documents\InvoiceOfferDocumentBuilder;
use InvoiceService\Documents\InvoiceOfferDocumentData;
use InvoiceService\Documents\InvoiceOfferProfile;
use InvoiceService\Domain\BuyerRequisites;
use InvoiceService\Domain\DealProduct;
use InvoiceService\Domain\InvoiceNumber;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Domain\InvoiceSource;
use InvoiceService\Domain\InvoiceSourceValidator;
use InvoiceService\Domain\Money;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class InvoiceOfferDocumentBuilderTest extends TestCase
{
    public function testBuildsOfferWithStaticSublicensorDataAndOnlyLicenceProducts(): void
    {
        $profile = InvoiceOfferProfile::sonicIpV1();
        self::assertSame('sonic-ip-v2', $profile->version);
        $source = new InvoiceSource(1, 28457194, new BuyerRequisites(
            'ООО Покупатель', '7701000000', '123456789', '1027700000000', 'г. Москва, ул. Пример, 1',
            '40702810000000000001', 'АО Банк', '30101810000000000001', '044525000',
        ), [
            new DealProduct('Лицензия amoCRM Расширенный', Money::fromDecimal('1990.00'), 2, true),
            new DealProduct('Настройка CRM', Money::fromDecimal('10000.00'), 1, false),
        ], $profile->version);
        $snapshot = InvoiceSnapshot::fromSource($source, new InvoiceSourceValidator());
        $target = sys_get_temp_dir() . '/invoice-offer-' . bin2hex(random_bytes(4)) . '.docx';

        try {
            (new InvoiceOfferDocumentBuilder())->build(new InvoiceOfferDocumentData(
                $snapshot,
                InvoiceNumber::from(28457194, 123),
                new DateTimeImmutable('2026-09-02T00:00:00+00:00'),
                $profile,
            ), $target);

            $zip = new ZipArchive();
            self::assertTrue($zip->open($target) === true);
            $documentXml = $zip->getFromName('word/document.xml');
            $zip->close();

            self::assertIsString($documentXml);
            self::assertStringContainsString('ЛЦ-АМ-28457194-000123', $documentXml);
            self::assertStringContainsString('ИП Сон Роман Валентинович', $documentXml);
            self::assertStringContainsString('910604143588', $documentXml);
            self::assertStringContainsString('Партнёрское соглашение № 28457194', $documentXml);
            self::assertStringContainsString(
                'Все неурегулированные споры подлежат рассмотрению Арбитражным судом г. Санкт-Петербург.',
                $documentXml,
            );
            self::assertStringNotContainsString('Арбитражным судом г. Челябинска.', $documentXml);
            self::assertStringContainsString('ООО Покупатель', $documentXml);
            self::assertStringContainsString('Лицензия amoCRM Расширенный', $documentXml);
            self::assertStringNotContainsString('Настройка CRM', $documentXml);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
