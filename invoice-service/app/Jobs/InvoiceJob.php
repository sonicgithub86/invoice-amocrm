<?php

declare(strict_types=1);

namespace InvoiceService\Jobs;

use DateTimeImmutable;

final readonly class InvoiceJob
{
    public function __construct(
        public string $id,
        public string $webhookEndpointId,
        public int $accountId,
        public int $leadId,
        public string $triggerKind,
        public string $payloadHash,
        public string $status = 'pending',
        public ?DateTimeImmutable $lockedUntil = null,
        public int $attempts = 0,
        public ?DateTimeImmutable $retryAt = null,
    ) {
    }
}
