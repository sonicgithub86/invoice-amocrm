<?php

declare(strict_types=1);

namespace InvoiceService\OAuth;

use DateInterval;
use DateTimeImmutable;
use InvoiceService\Support\Uuid;

final class OAuthStateService
{
    /** @var \Closure(): DateTimeImmutable */
    private readonly \Closure $clock;

    /** @param callable(): DateTimeImmutable|null $clock */
    public function __construct(private readonly OAuthStateRepository $repository, ?callable $clock = null)
    {
        $this->clock = $clock instanceof \Closure ? $clock : static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    public function issue(?DateInterval $ttl = null): string
    {
        $state = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = ($this->clock)();
        $this->repository->store(new OAuthStateRecord(Uuid::v4(), hash('sha256', $state), $now->add($ttl ?? new DateInterval('PT10M'))));

        return $state;
    }

    public function consume(string $state): void
    {
        if ($state === '' || !$this->repository->consume(hash('sha256', $state), ($this->clock)())) {
            throw new OAuthStateUnavailable('OAuth state is invalid, expired, or already used.');
        }
    }
}
