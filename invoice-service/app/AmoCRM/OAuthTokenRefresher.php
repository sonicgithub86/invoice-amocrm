<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use DateTimeImmutable;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

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
            } catch (IdentityProviderException $exception) {
                if ($this->requiresReauthorization($account, $exception)) {
                    $this->accounts->markReauthorizationRequired($account->id);
                }
            } catch (\Throwable) {
                // A timeout or temporary amoCRM outage remains retryable on the next scheduled refresh.
            }
        }

        return $updated;
    }

    private function requiresReauthorization(AccountRecord $account, IdentityProviderException $exception): bool
    {
        $body = $exception->getResponseBody();
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($body) || ($body['error'] ?? null) !== 'invalid_grant') {
            return false;
        }
        $current = $this->accounts->findById($account->id);

        return $current !== null && hash_equals($account->token->refreshToken, $current->token->refreshToken);
    }
}
