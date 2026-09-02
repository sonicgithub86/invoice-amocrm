<?php

declare(strict_types=1);

namespace InvoiceService\Revisions;

final readonly class Reservation
{
    public function __construct(
        public bool $created,
        public InvoiceRevision $revision,
    ) {
    }
}
