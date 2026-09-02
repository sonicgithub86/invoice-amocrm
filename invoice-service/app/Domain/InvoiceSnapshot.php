<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

use LogicException;

final readonly class InvoiceSnapshot
{
    /** @param list<DealProduct> $licenseProducts */
    private function __construct(
        public int $amoAccountId,
        public int $leadId,
        public BuyerRequisites $buyer,
        public array $licenseProducts,
        public Money $total,
        public string $documentProfileVersion,
    ) {
    }

    public static function fromSource(InvoiceSource $source, InvoiceSourceValidator $validator): self
    {
        $eligibility = $validator->validate($source);
        if (!$eligibility->eligible || $source->buyer === null) {
            throw new LogicException('Cannot create an invoice snapshot for an ineligible deal.');
        }

        $products = $validator->licenseProducts($source);
        $total = Money::fromKopecks(0);
        foreach ($products as $product) {
            $total = $total->add($product->lineTotal());
        }

        return new self($source->amoAccountId, $source->leadId, $source->buyer, $products, $total, $source->documentProfileVersion);
    }

    /** @return array<string, mixed> */
    public function canonical(): array
    {
        $products = array_map(static fn (DealProduct $product): array => [
            'name' => $product->name,
            'quantity' => $product->quantity,
            'unit_price' => $product->unitPrice->decimal(),
            'line_total' => $product->lineTotal()->decimal(),
        ], $this->licenseProducts);
        usort($products, static fn (array $left, array $right): int => [$left['name'], $left['unit_price'], $left['quantity']] <=> [$right['name'], $right['unit_price'], $right['quantity']]);

        return [
            'amo_account_id' => $this->amoAccountId,
            'buyer' => $this->buyer->canonical(),
            'document_profile_version' => $this->documentProfileVersion,
            'lead_id' => $this->leadId,
            'license_products' => $products,
            'total' => $this->total->decimal(),
        ];
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->canonical(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
