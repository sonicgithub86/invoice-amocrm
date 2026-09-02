<?php

declare(strict_types=1);

namespace InvoiceService\Services;

final readonly class WebhookEndpointRecord
{
    public function __construct(
        public string $id,
        public int $accountId,
        public string $triggerKind,
        public string $secretHash,
    ) {
    }
}
