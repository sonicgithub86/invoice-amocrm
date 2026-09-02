<?php

declare(strict_types=1);

namespace InvoiceService\Tests\OAuth;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\OAuth\InMemoryOAuthStateRepository;
use InvoiceService\OAuth\OAuthStateService;
use InvoiceService\OAuth\OAuthStateUnavailable;
use PHPUnit\Framework\TestCase;

final class OAuthStateServiceTest extends TestCase
{
    public function testStateCanBeConsumedOnlyOnce(): void
    {
        $clock = new DateTimeImmutable('2026-09-02T12:00:00+00:00');
        $service = new OAuthStateService(new InMemoryOAuthStateRepository(), static fn (): DateTimeImmutable => $clock);
        $state = $service->issue();

        $service->consume($state);

        $this->expectException(OAuthStateUnavailable::class);
        $service->consume($state);
    }

    public function testExpiredStateCannotBeConsumed(): void
    {
        $clock = new DateTimeImmutable('2026-09-02T12:00:00+00:00');
        $repository = new InMemoryOAuthStateRepository();
        $service = new OAuthStateService($repository, static fn (): DateTimeImmutable => $clock);
        $state = $service->issue(new DateInterval('PT1S'));
        $later = new OAuthStateService($repository, static fn (): DateTimeImmutable => $clock->add(new DateInterval('PT2S')));

        $this->expectException(OAuthStateUnavailable::class);
        $later->consume($state);
    }
}
