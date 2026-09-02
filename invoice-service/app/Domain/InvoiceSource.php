<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

final readonly class InvoiceSource
{
    /** @param list<DealProduct> $products */
    public function __construct(
        public int $amoAccountId,
        public int $leadId,
        public ?BuyerRequisites $buyer,
        public array $products,
        public string $documentProfileVersion,
    ) {
    }
}
