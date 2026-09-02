<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Domain;

use InvoiceService\Domain\InvoiceNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvoiceNumberTest extends TestCase
{
    public function testFormatsTheGlobalSequenceWithSixDigits(): void
    {
        self::assertSame('ЛЦ-АМ-28457194-000123', InvoiceNumber::from(28457194, 123)->value());
    }

    public function testRejectsNonPositiveSequenceValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        InvoiceNumber::from(28457194, 0);
    }
}
