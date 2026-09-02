<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

use InvalidArgumentException;

final readonly class DealProduct
{
    public function __construct(
        public string $name,
        public Money $unitPrice,
        public int $quantity,
        public bool $isAmoCrmLicense,
    ) {
        if (trim($name) === '' || $quantity < 1) {
            throw new InvalidArgumentException('A deal product must have a name and positive integer quantity.');
        }
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
