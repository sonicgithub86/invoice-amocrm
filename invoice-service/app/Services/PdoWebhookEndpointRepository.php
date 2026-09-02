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
}
