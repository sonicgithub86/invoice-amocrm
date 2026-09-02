<?php

declare(strict_types=1);

namespace InvoiceService\Documents;

use DateTimeImmutable;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Domain\InvoiceNumber;

final readonly class InvoiceOfferDocumentData
{
    public function __construct(
        public InvoiceSnapshot $snapshot,
        public InvoiceNumber $invoiceNumber,
        public DateTimeImmutable $issuedAt,
        public InvoiceOfferProfile $profile,
    ) {
    }
}
