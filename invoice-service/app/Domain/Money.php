<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(public int $kopecks)
    {
    }

    public static function fromDecimal(string $value): self
    {
        $normalized = trim(str_replace(',', '.', $value));
        if (preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException('Money must be a non-negative decimal amount with at most two decimal places.');
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $whole = (int) $matches[1];
        if ($whole > intdiv(PHP_INT_MAX - (int) $fraction, 100)) {
            throw new InvalidArgumentException('Money amount is too large.');
        }

        return new self($whole * 100 + (int) $fraction);
    }

    public static function fromKopecks(int $kopecks): self
    {
        if ($kopecks < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        return new self($kopecks);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0 || ($multiplier > 0 && $this->kopecks > intdiv(PHP_INT_MAX, $multiplier))) {
            throw new InvalidArgumentException('Money multiplication is invalid.');
        }

        return new self($this->kopecks * $multiplier);
    }

    public function add(self $other): self
    {
        if ($other->kopecks > PHP_INT_MAX - $this->kopecks) {
            throw new InvalidArgumentException('Money total is too large.');
        }

        return new self($this->kopecks + $other->kopecks);
    }

    public function decimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->kopecks, 100), $this->kopecks % 100);
    }
}
