<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use DateTimeImmutable;

final readonly class OAuthTokenRefresher
{
    public function __construct(
        private AccountRepository $accounts,
        private OAuthGateway $gateway,
    ) {
    }

    /** @return list<AccountRecord> */
    public function refreshDue(DateTimeImmutable $before): array
    {
        $updated = [];
        foreach ($this->accounts->findDueForRefresh($before) as $account) {
            try {
                $updated[] = $this->accounts->saveToken($account, $this->gateway->refresh($account->token, $account->baseDomain));
            } catch (\Throwable) {
                $this->accounts->markReauthorizationRequired($account->id);
            }
        }

        return $updated;
    }
}
