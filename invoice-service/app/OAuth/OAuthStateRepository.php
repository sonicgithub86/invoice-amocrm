<?php

declare(strict_types=1);

namespace InvoiceService\OAuth;

use DateTimeImmutable;

interface OAuthStateRepository
{
    public function store(OAuthStateRecord $record): void;

    public function consume(string $stateHash, DateTimeImmutable $now): bool;
}
