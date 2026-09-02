<?php

declare(strict_types=1);

namespace InvoiceService\Services;

interface WebhookEndpointRepository
{
    public function replace(int $accountId, string $triggerKind, string $endpointId, string $secretHash): void;
}
