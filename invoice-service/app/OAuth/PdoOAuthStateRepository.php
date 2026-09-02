<?php

declare(strict_types=1);

namespace InvoiceService\OAuth;

use DateTimeImmutable;
use PDO;

final class PdoOAuthStateRepository implements OAuthStateRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function store(OAuthStateRecord $record): void
    {
        $statement = $this->connection->prepare('INSERT INTO oauth_states (id, state_hash, expires_at) VALUES (:id, :state_hash, :expires_at)');
        $statement->execute([
            'id' => $record->id,
            'state_hash' => $record->stateHash,
            'expires_at' => $record->expiresAt->format(DATE_ATOM),
        ]);
    }

    public function consume(string $stateHash, DateTimeImmutable $now): bool
    {
        $statement = $this->connection->prepare('UPDATE oauth_states SET consumed_at = :now WHERE state_hash = :state_hash AND consumed_at IS NULL AND expires_at > :now');
        $statement->execute([
            'state_hash' => $stateHash,
            'now' => $now->format(DATE_ATOM),
        ]);

        return $statement->rowCount() === 1;
    }
}
