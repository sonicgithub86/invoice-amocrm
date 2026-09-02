<?php

declare(strict_types=1);

namespace InvoiceService\Jobs;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\Services\WebhookEndpointRecord;
use InvoiceService\Support\Uuid;
use PDO;

final class PdoInvoiceJobRepository implements InvoiceJobRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function enqueue(WebhookEndpointRecord $endpoint, int $leadId, string $payloadHash): EnqueueResult
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO invoice_jobs (id, webhook_endpoint_id, account_id, lead_id, trigger_kind, payload_hash)
VALUES (:id, :webhook_endpoint_id, :account_id, :lead_id, :trigger_kind, :payload_hash)
ON CONFLICT DO NOTHING
RETURNING id
SQL);
        $id = Uuid::v4();
        $statement->execute([
            'id' => $id,
            'webhook_endpoint_id' => $endpoint->id,
            'account_id' => $endpoint->accountId,
            'lead_id' => $leadId,
            'trigger_kind' => $endpoint->triggerKind,
            'payload_hash' => $payloadHash,
        ]);
        $created = $statement->fetchColumn() !== false;

        return $created
            ? new EnqueueResult(true, new InvoiceJob($id, $endpoint->id, $endpoint->accountId, $leadId, $endpoint->triggerKind, $payloadHash))
            : new EnqueueResult(false);
    }

    public function leaseNext(string $workerId, DateTimeImmutable $now, DateInterval $leaseDuration): ?InvoiceJob
    {
        $this->connection->prepare(<<<'SQL'
UPDATE invoice_jobs
SET status = 'retryable', lease_owner = NULL, locked_until = NULL, updated_at = now()
WHERE status = 'leased' AND locked_until <= :now
SQL)->execute(['now' => $now->format(DATE_ATOM)]);

        $statement = $this->connection->prepare(<<<'SQL'
WITH candidate AS (
    SELECT id
    FROM invoice_jobs
    WHERE status IN ('pending', 'retryable')
      AND (retry_at IS NULL OR retry_at <= :now)
    ORDER BY created_at
    FOR UPDATE SKIP LOCKED
    LIMIT 1
)
UPDATE invoice_jobs AS job
SET status = 'leased',
    lease_owner = :worker_id,
    locked_until = :locked_until,
    attempts = job.attempts + 1,
    updated_at = now()
FROM candidate
WHERE job.id = candidate.id
RETURNING job.id, job.webhook_endpoint_id, job.account_id, job.lead_id, job.trigger_kind, job.payload_hash, job.status, job.locked_until, job.attempts, job.retry_at, job.lease_owner, job.failure_reason
SQL);
        $statement->execute([
            'now' => $now->format(DATE_ATOM),
            'worker_id' => $workerId,
            'locked_until' => $now->add($leaseDuration)->format(DATE_ATOM),
        ]);
        /** @var array<string, int|string|null>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->toJob($row);
    }

    public function markCompleted(InvoiceJob $job): void
    {
        $this->connection->prepare(<<<'SQL'
UPDATE invoice_jobs
SET status = 'completed', locked_until = NULL, lease_owner = NULL, updated_at = now()
WHERE id = :id AND status = 'leased'
  AND lease_owner = :lease_owner
SQL)->execute(['id' => $job->id, 'lease_owner' => $job->leaseOwner]);
    }

    public function markRetryable(InvoiceJob $job, DateTimeImmutable $retryAt): void
    {
        $this->connection->prepare(<<<'SQL'
UPDATE invoice_jobs
SET status = 'retryable', retry_at = :retry_at, locked_until = NULL, lease_owner = NULL, updated_at = now()
WHERE id = :id AND status = 'leased'
  AND lease_owner = :lease_owner
SQL)->execute([
    'id' => $job->id,
    'retry_at' => $retryAt->format(DATE_ATOM),
    'lease_owner' => $job->leaseOwner,
]);
    }

    public function markFailed(InvoiceJob $job, string $reason): void
    {
        $this->connection->prepare(<<<'SQL'
UPDATE invoice_jobs
SET status = 'failed', failure_reason = :failure_reason, locked_until = NULL, lease_owner = NULL, updated_at = now()
WHERE id = :id AND status = 'leased'
  AND lease_owner = :lease_owner
SQL)->execute([
    'id' => $job->id,
    'failure_reason' => $reason,
    'lease_owner' => $job->leaseOwner,
]);
    }

    /** @param array<string, int|string|null> $row */
    private function toJob(array $row): InvoiceJob
    {
        $lockedUntil = $row['locked_until'];
        if ($lockedUntil === null) {
            throw new \RuntimeException('A leased invoice job must have a lease deadline.');
        }

        return new InvoiceJob(
            (string) $row['id'],
            (string) $row['webhook_endpoint_id'],
            (int) $row['account_id'],
            (int) $row['lead_id'],
            (string) $row['trigger_kind'],
            (string) $row['payload_hash'],
            (string) $row['status'],
            new DateTimeImmutable($lockedUntil),
            (int) $row['attempts'],
            $row['retry_at'] === null ? null : new DateTimeImmutable((string) $row['retry_at']),
            $row['lease_owner'] === null ? null : (string) $row['lease_owner'],
            $row['failure_reason'] === null ? null : (string) $row['failure_reason'],
        );
    }
}
