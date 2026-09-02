<?php

declare(strict_types=1);

namespace InvoiceService\Jobs;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\Services\WebhookEndpointRecord;

interface InvoiceJobRepository
{
    public function enqueue(WebhookEndpointRecord $endpoint, int $leadId, string $payloadHash): EnqueueResult;

    public function leaseNext(string $workerId, DateTimeImmutable $now, DateInterval $leaseDuration): ?InvoiceJob;

    public function markCompleted(InvoiceJob $job): void;

    public function markRetryable(InvoiceJob $job, DateTimeImmutable $retryAt): void;
}
