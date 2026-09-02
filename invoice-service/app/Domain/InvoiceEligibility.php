<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

final readonly class InvoiceEligibility
{
    /** @param list<string> $reasons */
    private function __construct(
        public bool $eligible,
        public array $reasons,
    ) {
    }

    /** @param list<string> $reasons */
    public static function invalid(array $reasons): self
    {
        return new self(false, $reasons);
    }

    public static function valid(): self
    {
        return new self(true, []);
    }
}
