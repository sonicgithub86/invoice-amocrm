<?php

declare(strict_types=1);

namespace InvoiceService\Services;

final class InMemoryWebhookEndpointRepository implements WebhookEndpointRepository
{
    /** @var array<string, array{account_id: int, trigger_kind: string, secret_hash: string}> */
    private array $records = [];

    public function replace(int $accountId, string $triggerKind, string $endpointId, string $secretHash): void
    {
        $this->records[$endpointId] = [
            'account_id' => $accountId,
            'trigger_kind' => $triggerKind,
            'secret_hash' => $secretHash,
        ];
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function containsRawSecret(string $secret): bool
    {
        foreach ($this->records as $record) {
            if (hash_equals($record['secret_hash'], $secret)) {
                return true;
            }
        }

        return false;
    }
}
