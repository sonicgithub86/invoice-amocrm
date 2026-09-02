<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

final readonly class ConnectedAccount
{
    public function __construct(
        public int $amoAccountId,
        public string $baseDomain,
        public OAuthToken $token,
    ) {
    }
}
