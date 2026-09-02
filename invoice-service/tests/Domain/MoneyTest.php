<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Domain;

use InvoiceService\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testKeepsPricesAndTotalsAsExactKopecks(): void
    {
        $total = Money::fromDecimal('1990.50')->multiply(2)->add(Money::fromDecimal('0.01'));

        self::assertSame('3981.01', $total->decimal());
    }
}
