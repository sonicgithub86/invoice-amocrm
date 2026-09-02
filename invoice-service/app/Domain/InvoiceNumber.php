<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

use InvalidArgumentException;

final readonly class InvoiceNumber
{
    private function __construct(
        private int $dealId,
        private int $sequence,
    ) {
    }

    public static function from(int $dealId, int $sequence): self
    {
        if ($dealId <= 0 || $sequence <= 0) {
            throw new InvalidArgumentException('Deal ID and invoice sequence must be positive integers.');
        }

        return new self($dealId, $sequence);
    }

    public function value(): string
    {
        return sprintf('ЛЦ-АМ-%d-%06d', $this->dealId, $this->sequence);
    }
}
