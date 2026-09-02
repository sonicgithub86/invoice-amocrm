<?php

declare(strict_types=1);

namespace InvoiceService\Jobs;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\Services\WebhookEndpointRecord;
use InvoiceService\Support\Uuid;

final class InMemoryInvoiceJobRepository implements InvoiceJobRepository
{
    /** @var array<string, InvoiceJob> */
    private array $jobs = [];

    /** @var array<string, string> */
    private array $activeJobIds = [];

    public function enqueue(WebhookEndpointRecord $endpoint, int $leadId, string $payloadHash): EnqueueResult
    {
        $activeKey = $endpoint->accountId . ':' . $leadId;
        if (isset($this->activeJobIds[$activeKey])) {
            return new EnqueueResult(false);
        }

        $job = new InvoiceJob(Uuid::v4(), $endpoint->id, $endpoint->accountId, $leadId, $endpoint->triggerKind, $payloadHash);
        $this->jobs[$job->id] = $job;
        $this->activeJobIds[$activeKey] = $job->id;

        return new EnqueueResult(true, $job);
    }

    public function leaseNext(string $workerId, DateTimeImmutable $now, DateInterval $leaseDuration): ?InvoiceJob
    {
        foreach ($this->jobs as $id => $job) {
            if ($job->status === 'leased' && $job->lockedUntil !== null && $job->lockedUntil <= $now) {
                $this->jobs[$id] = new InvoiceJob(
                    $job->id,
                    $job->webhookEndpointId,
                    $job->accountId,
                    $job->leadId,
                    $job->triggerKind,
                    $job->payloadHash,
                    'retryable',
                    null,
                    $job->attempts,
                    $job->retryAt,
                    null,
                    $job->failureReason,
                );
            }
        }

        foreach ($this->jobs as $id => $job) {
            if (!in_array($job->status, ['pending', 'retryable'], true)) {
                continue;
            }

            if ($job->retryAt !== null && $job->retryAt > $now) {
                continue;
            }

            $leased = new InvoiceJob(
                $job->id,
                $job->webhookEndpointId,
                $job->accountId,
                $job->leadId,
                $job->triggerKind,
                $job->payloadHash,
                'leased',
                $now->add($leaseDuration),
                $job->attempts + 1,
                $job->retryAt,
                $workerId,
            );
            $this->jobs[$id] = $leased;

            return $leased;
        }

        return null;
    }

    public function markCompleted(InvoiceJob $job): void
    {
        if (!$this->isCurrentLease($job)) {
            return;
        }

        $this->jobs[$job->id] = new InvoiceJob($job->id, $job->webhookEndpointId, $job->accountId, $job->leadId, $job->triggerKind, $job->payloadHash, 'completed', null, $job->attempts);
        unset($this->activeJobIds[$job->accountId . ':' . $job->leadId]);
    }

    public function markRetryable(InvoiceJob $job, DateTimeImmutable $retryAt): void
    {
        if (!$this->isCurrentLease($job)) {
            return;
        }

        $this->jobs[$job->id] = new InvoiceJob($job->id, $job->webhookEndpointId, $job->accountId, $job->leadId, $job->triggerKind, $job->payloadHash, 'retryable', null, $job->attempts, $retryAt);
    }

    public function markFailed(InvoiceJob $job, string $reason): void
    {
        if (!$this->isCurrentLease($job)) {
            return;
        }

        $this->jobs[$job->id] = new InvoiceJob($job->id, $job->webhookEndpointId, $job->accountId, $job->leadId, $job->triggerKind, $job->payloadHash, 'failed', null, $job->attempts, null, null, $reason);
        unset($this->activeJobIds[$job->accountId . ':' . $job->leadId]);
    }

    private function isCurrentLease(InvoiceJob $job): bool
    {
        $current = $this->jobs[$job->id] ?? null;

        return $job->leaseOwner !== null
            && $current !== null
            && $current->status === 'leased'
            && $current->leaseOwner === $job->leaseOwner;
    }

    public function count(): int
    {
        return count($this->jobs);
    }

    public function job(string $id): ?InvoiceJob
    {
        return $this->jobs[$id] ?? null;
    }
}
