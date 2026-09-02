<?php

declare(strict_types=1);

namespace InvoiceService\OAuth;

use DateTimeImmutable;

final readonly class OAuthStateRecord
{
    public function __construct(
        public string $id,
        public string $stateHash,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
