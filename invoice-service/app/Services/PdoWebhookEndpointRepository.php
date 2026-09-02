<?php

declare(strict_types=1);

namespace InvoiceService\Services;

use PDO;

final class PdoWebhookEndpointRepository implements WebhookEndpointRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function replace(int $accountId, string $triggerKind, string $endpointId, string $secretHash): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO webhook_endpoints (id, account_id, trigger_kind, secret_hash, enabled)
VALUES (:id, :account_id, :trigger_kind, :secret_hash, true)
ON CONFLICT (account_id, trigger_kind) DO UPDATE SET
    id = EXCLUDED.id,
    secret_hash = EXCLUDED.secret_hash,
    enabled = true,
    created_at = now()
SQL);
        $statement->execute([
            'id' => $endpointId,
            'account_id' => $accountId,
            'trigger_kind' => $triggerKind,
            'secret_hash' => $secretHash,
        ]);
    }

    public function findEnabled(string $endpointId): ?WebhookEndpointRecord
    {
        $statement = $this->connection->prepare(<<<'SQL'
SELECT id, account_id, trigger_kind, secret_hash
FROM webhook_endpoints
WHERE id = :id AND enabled = true
SQL);
        $statement->execute(['id' => $endpointId]);
        /** @var array{id: string, account_id: int|string, trigger_kind: string, secret_hash: string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new WebhookEndpointRecord($row['id'], (int) $row['account_id'], $row['trigger_kind'], $row['secret_hash']);
    }
}
