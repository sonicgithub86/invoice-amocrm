<?php

declare(strict_types=1);

namespace InvoiceService\Jobs;

final readonly class EnqueueResult
{
    public function __construct(
        public bool $created,
        public ?InvoiceJob $job = null,
    ) {
    }
}
