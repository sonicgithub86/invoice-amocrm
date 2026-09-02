<?php

declare(strict_types=1);

namespace InvoiceService\OAuth;

use DateTimeImmutable;

final class InMemoryOAuthStateRepository implements OAuthStateRepository
{
    /** @var array<string, OAuthStateRecord> */
    private array $records = [];

    public function store(OAuthStateRecord $record): void
    {
        $this->records[$record->stateHash] = $record;
    }

    public function consume(string $stateHash, DateTimeImmutable $now): bool
    {
        $record = $this->records[$stateHash] ?? null;
        if ($record === null || $record->expiresAt <= $now) {
            return false;
        }

        unset($this->records[$stateHash]);

        return true;
    }
}
