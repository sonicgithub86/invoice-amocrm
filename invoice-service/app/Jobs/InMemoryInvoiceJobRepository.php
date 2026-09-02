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
            if (!in_array($job->status, ['pending', 'retryable'], true)) {
                continue;
            }

            if ($job->retryAt !== null && $job->retryAt > $now) {
                continue;
            }

            $leased = new InvoiceJob($job->id, $job->webhookEndpointId, $job->accountId, $job->leadId, $job->triggerKind, $job->payloadHash, 'leased', $now->add($leaseDuration), $job->attempts + 1);
            $this->jobs[$id] = $leased;

            return $leased;
        }

        return null;
    }

    public function markCompleted(InvoiceJob $job): void
    {
        $this->jobs[$job->id] = new InvoiceJob($job->id, $job->webhookEndpointId, $job->accountId, $job->leadId, $job->triggerKind, $job->payloadHash, 'completed', null, $job->attempts);
        unset($this->activeJobIds[$job->accountId . ':' . $job->leadId]);
    }

    public function markRetryable(InvoiceJob $job, DateTimeImmutable $retryAt): void
    {
        $this->jobs[$job->id] = new InvoiceJob($job->id, $job->webhookEndpointId, $job->accountId, $job->leadId, $job->triggerKind, $job->payloadHash, 'retryable', null, $job->attempts, $retryAt);
    }

    public function count(): int
    {
        return count($this->jobs);
    }
}
