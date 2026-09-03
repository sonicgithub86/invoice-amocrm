<?php

declare(strict_types=1);

namespace InvoiceService\Tests\AmoCRM;

use InvoiceService\AmoCRM\OfficialInvoiceSourceReader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OfficialInvoiceSourceReaderTest extends TestCase
{
    public function testAcceptsWholeIntegerAndFloatQuantitiesFromAmoCrm(): void
    {
        self::assertSame(1, $this->normalizeQuantity(1));
        self::assertSame(2, $this->normalizeQuantity(2.0));
    }

    public function testRejectsMissingFractionalAndNonPositiveQuantities(): void
    {
        self::assertNull($this->normalizeQuantity(null));
        self::assertNull($this->normalizeQuantity(0));
        self::assertNull($this->normalizeQuantity(-1));
        self::assertNull($this->normalizeQuantity(1.5));
    }

    private function normalizeQuantity(int|float|null $quantity): ?int
    {
        $reflection = new ReflectionClass(OfficialInvoiceSourceReader::class);
        $reader = $reflection->newInstanceWithoutConstructor();
        $result = $reflection->getMethod('wholeQuantity')->invoke($reader, $quantity);

        self::assertTrue($result === null || is_int($result));

        return $result;
    }
}
