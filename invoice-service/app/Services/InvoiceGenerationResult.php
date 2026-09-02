<?php

declare(strict_types=1);

namespace InvoiceService\Services;

final readonly class InvoiceGenerationResult
{
    public function __construct(
        public string $status,
        public ?string $invoiceNumber = null,
    ) {
    }
}
