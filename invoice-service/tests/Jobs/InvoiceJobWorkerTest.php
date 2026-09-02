<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Jobs;

use DateTimeImmutable;
use InvoiceService\Jobs\InMemoryInvoiceJobRepository;
use InvoiceService\Jobs\InvoiceJob;
use InvoiceService\Jobs\InvoiceJobWorker;
use InvoiceService\Services\InvoiceGenerationResult;
use InvoiceService\Services\InvoiceJobProcessor;
use InvoiceService\Services\WebhookEndpointRecord;
use PHPUnit\Framework\TestCase;

final class InvoiceJobWorkerTest extends TestCase
{
    public function testCompletesLeasedJobAfterProcessorSucceeds(): void
    {
        $jobs = new InMemoryInvoiceJobRepository();
        $endpoint = new WebhookEndpointRecord('6f4045e5-01d0-4a31-aaf4-69a907f0975f', 7, 'automatic', hash('sha256', 'secret'));
        $jobs->enqueue($endpoint, 28457194, hash('sha256', 'payload'));
        $processor = new class implements InvoiceJobProcessor {
            public int $calls = 0;
            public function generate(InvoiceJob $job): InvoiceGenerationResult { ++$this->calls; return new InvoiceGenerationResult('generated', 'ЛЦ-АМ-28457194-000001'); }
        };

        $worked = (new InvoiceJobWorker($jobs, $processor, 'test-worker'))->runOnce();

        self::assertTrue($worked);
        self::assertSame(1, $processor->calls);
        self::assertNull($jobs->leaseNext('second-worker', new DateTimeImmutable(), new \DateInterval('PT1M')));
    }

    public function testStopsRetryingAfterFiveFailedAttemptsAndRecordsTheFailure(): void
    {
        $jobs = new InMemoryInvoiceJobRepository();
        $endpoint = new WebhookEndpointRecord('6f4045e5-01d0-4a31-aaf4-69a907f0975f', 7, 'automatic', hash('sha256', 'secret'));
        $enqueued = $jobs->enqueue($endpoint, 28457194, hash('sha256', 'payload'));
        $now = new DateTimeImmutable('2026-09-02T12:00:00+00:00');
        $processor = new class implements InvoiceJobProcessor {
            public function generate(InvoiceJob $job): InvoiceGenerationResult { throw new \RuntimeException('amoCRM is unavailable'); }
        };
        $worker = new InvoiceJobWorker($jobs, $processor, 'test-worker', static function () use (&$now): DateTimeImmutable { return $now; });

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertTrue($worker->runOnce());
            if ($attempt === 1) {
                $retryAt = $enqueued->job === null ? null : $jobs->job($enqueued->job->id)?->retryAt;
                self::assertNotNull($retryAt);
                self::assertGreaterThanOrEqual($now->add(new \DateInterval('PT2M')), $retryAt);
                self::assertLessThanOrEqual($now->add(new \DateInterval('PT2M30S')), $retryAt);
            }
            $now = $now->add(new \DateInterval('PT35M'));
        }

        $job = $enqueued->job === null ? null : $jobs->job($enqueued->job->id);
        self::assertSame('failed', $job?->status);
        self::assertStringContainsString('amoCRM is unavailable', (string) $job?->failureReason);
        self::assertNull($jobs->leaseNext('next-worker', $now, new \DateInterval('PT1M')));
    }
}
