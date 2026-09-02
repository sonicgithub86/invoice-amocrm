<?php

declare(strict_types=1);

namespace InvoiceService\Services;

final class InMemoryWebhookEndpointRepository implements WebhookEndpointRepository
{
    /** @var array<string, WebhookEndpointRecord> */
    private array $records = [];

    public function replace(int $accountId, string $triggerKind, string $endpointId, string $secretHash): void
    {
        $this->records[$endpointId] = new WebhookEndpointRecord($endpointId, $accountId, $triggerKind, $secretHash);
    }

    public function findEnabled(string $endpointId): ?WebhookEndpointRecord
    {
        return $this->records[$endpointId] ?? null;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function containsRawSecret(string $secret): bool
    {
        foreach ($this->records as $record) {
            if (hash_equals($record->secretHash, $secret)) {
                return true;
            }
        }

        return false;
    }
}
