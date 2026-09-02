<?php

declare(strict_types=1);

namespace InvoiceService\Jobs;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\Services\InvoiceJobProcessor;
use InvoiceService\Support\Uuid;

final readonly class InvoiceJobWorker
{
    private const MAX_ATTEMPTS = 5;

    private \Closure $clock;

    public function __construct(
        private InvoiceJobRepository $jobs,
        private InvoiceJobProcessor $processor,
        private string $workerId,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    public static function withGeneratedId(InvoiceJobRepository $jobs, InvoiceJobProcessor $processor): self
    {
        return new self($jobs, $processor, 'worker-' . Uuid::v4());
    }

    public function runOnce(): bool
    {
        $now = ($this->clock)();
        $job = $this->jobs->leaseNext($this->workerId, $now, new DateInterval('PT5M'));
        if ($job === null) {
            return false;
        }
        try {
            $this->processor->generate($job);
            $this->jobs->markCompleted($job);
        } catch (\Throwable $exception) {
            if ($job->attempts >= self::MAX_ATTEMPTS) {
                $this->jobs->markFailed($job, $exception::class . ': ' . $exception->getMessage());
            } else {
                $this->jobs->markRetryable($job, ($this->clock)()->add($this->retryDelay($job)));
            }
        }

        return true;
    }

    private function retryDelay(InvoiceJob $job): DateInterval
    {
        $baseSeconds = min(1800, 120 * (2 ** max(0, $job->attempts - 1)));
        $jitterSeconds = hexdec(substr(hash('sha256', $job->id . ':' . $job->attempts), 0, 4)) % 31;

        return new DateInterval('PT' . ($baseSeconds + $jitterSeconds) . 'S');
    }
}
