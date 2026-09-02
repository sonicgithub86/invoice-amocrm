<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use DateTimeImmutable;

final readonly class OAuthToken
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
