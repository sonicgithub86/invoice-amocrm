<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Domain;

use InvoiceService\Domain\BuyerRequisites;
use InvoiceService\Domain\DealProduct;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Domain\InvoiceSource;
use InvoiceService\Domain\InvoiceSourceValidator;
use InvoiceService\Domain\Money;
use PHPUnit\Framework\TestCase;

final class InvoiceSourceValidatorTest extends TestCase
{
    public function testKppIsOptionalButAllOtherCompanyRequisitesAndLicenceRowsAreRequired(): void
    {
        $validator = new InvoiceSourceValidator();
        $eligible = new InvoiceSource(1, 28457194, $this->buyer(kpp: ''), [
            new DealProduct('Лицензия amoCRM', Money::fromDecimal('1990.00'), 2, true),
            new DealProduct('Настройка CRM', Money::fromDecimal('10000.00'), 1, false),
        ], 'seller-v1');

        $result = $validator->validate($eligible);
        $snapshot = InvoiceSnapshot::fromSource($eligible, $validator);

        self::assertTrue($result->eligible);
        self::assertSame('3980.00', $snapshot->total->decimal());
        self::assertCount(1, $snapshot->licenseProducts);
    }

    public function testMissingCompanyOrLicenceProductsProducesActionableReasons(): void
    {
        $source = new InvoiceSource(1, 28457194, null, [
            new DealProduct('Настройка CRM', Money::fromDecimal('10000'), 1, false),
        ], 'seller-v1');

        $result = (new InvoiceSourceValidator())->validate($source);

        self::assertFalse($result->eligible);
        self::assertContains('К сделке не привязано юридическое лицо покупателя.', $result->reasons);
        self::assertStringContainsString('Лицензия amoCRM', $result->reasons[1]);
    }

    public function testCanonicalSnapshotChangesOnlyWhenInvoiceSourceChanges(): void
    {
        $validator = new InvoiceSourceValidator();
        $first = new InvoiceSource(1, 28457194, $this->buyer(), [
            new DealProduct('Лицензия amoCRM Расширенный', Money::fromDecimal('1990.00'), 2, true),
        ], 'seller-v1');
        $sameButProductReturnedAgain = new InvoiceSource(1, 28457194, $this->buyer(), [
            new DealProduct('Лицензия amoCRM Расширенный', Money::fromDecimal('1990.00'), 2, true),
        ], 'seller-v1');
        $changed = new InvoiceSource(1, 28457194, $this->buyer(), [
            new DealProduct('Лицензия amoCRM Расширенный', Money::fromDecimal('1990.00'), 3, true),
        ], 'seller-v1');

        self::assertSame(InvoiceSnapshot::fromSource($first, $validator)->hash(), InvoiceSnapshot::fromSource($sameButProductReturnedAgain, $validator)->hash());
        self::assertNotSame(InvoiceSnapshot::fromSource($first, $validator)->hash(), InvoiceSnapshot::fromSource($changed, $validator)->hash());
    }

    private function buyer(string $kpp = '123456789'): BuyerRequisites
    {
        return new BuyerRequisites(
            'ООО Покупатель',
            '7701000000',
            $kpp,
            '1027700000000',
            'г. Москва, ул. Пример, 1',
            '40702810000000000001',
            'АО Банк',
            '30101810000000000001',
            '044525000',
        );
    }
}
