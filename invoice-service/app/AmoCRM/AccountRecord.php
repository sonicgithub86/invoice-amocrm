<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

final readonly class AccountRecord
{
    public function __construct(
        public int $id,
        public int $amoAccountId,
        public string $baseDomain,
        public OAuthToken $token,
        public string $connectionState,
    ) {
    }
}
