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
        $eligible = new InvoiceSource(1, 28457194, $this->buyer(kpp: '', inn: '500100732259', ogrn: '304500116000157'), [
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

    public function testRequiresKppForLegalEntityAndRejectsMalformedBankRequisites(): void
    {
        $buyer = $this->buyer(kpp: '', settlementAccount: '4070281000000000000X');
        $source = new InvoiceSource(1, 28457194, $buyer, [
            new DealProduct('Лицензия amoCRM', Money::fromDecimal('1990.00'), 1, true),
        ], 'seller-v1');

        $result = (new InvoiceSourceValidator())->validate($source);

        self::assertFalse($result->eligible);
        self::assertContains('Некорректно заполнено поле компании: КПП (обязательно для юридического лица).', $result->reasons);
        self::assertContains('Некорректно заполнено поле компании: Расчётный счёт (требуется 20 цифр).', $result->reasons);
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

    private function buyer(
        string $kpp = '123456789',
        string $inn = '7701000000',
        string $ogrn = '1027700000000',
        string $settlementAccount = '40702810000000000001',
    ): BuyerRequisites
    {
        return new BuyerRequisites(
            'ООО Покупатель',
            $inn,
            $kpp,
            $ogrn,
            'г. Москва, ул. Пример, 1',
            $settlementAccount,
            'АО Банк',
            '30101810000000000001',
            '044525000',
        );
    }
}
