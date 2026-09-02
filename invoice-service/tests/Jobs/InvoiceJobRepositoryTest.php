<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Jobs;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\Jobs\InMemoryInvoiceJobRepository;
use InvoiceService\Services\WebhookEndpointRecord;
use PHPUnit\Framework\TestCase;

final class InvoiceJobRepositoryTest extends TestCase
{
    public function testLeasedJobIsNotLeasedTwiceAndCanBeReleasedAfterCompletion(): void
    {
        $repository = new InMemoryInvoiceJobRepository();
        $endpoint = new WebhookEndpointRecord('6f4045e5-01d0-4a31-aaf4-69a907f0975f', 7, 'automatic', hash('sha256', 'secret'));
        $repository->enqueue($endpoint, 28457194, hash('sha256', 'payload'));
        $now = new DateTimeImmutable('2026-09-02T12:00:00+00:00');

        $leased = $repository->leaseNext('worker-1', $now, new DateInterval('PT2M'));

        self::assertNotNull($leased);
        self::assertSame(1, $leased->attempts);
        self::assertNull($repository->leaseNext('worker-2', $now, new DateInterval('PT2M')));
        $repository->markCompleted($leased);
        self::assertTrue($repository->enqueue($endpoint, 28457194, hash('sha256', 'new-payload'))->created);
    }
}
