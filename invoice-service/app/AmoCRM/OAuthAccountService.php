<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use InvoiceService\Services\WebhookEndpointService;
use InvalidArgumentException;

final class OAuthAccountService
{
    public function __construct(
        private readonly OAuthGateway $gateway,
        private readonly AccountRepository $accounts,
        private readonly WebhookEndpointService $webhookEndpoints,
    ) {
    }

    /** @return array{account: AccountRecord, endpoints: array{automatic: string, rerun: string}} */
    public function connect(string $code, string $referer): array
    {
        $baseDomain = $this->normalizeBaseDomain($referer);
        $account = $this->accounts->upsert($this->gateway->exchangeAuthorizationCode($code, $baseDomain));

        return [
            'account' => $account,
            'endpoints' => $this->webhookEndpoints->issueForAccount($account->id),
        ];
    }

    private function normalizeBaseDomain(string $referer): string
    {
        $candidate = str_contains($referer, '://') ? (string) parse_url($referer, PHP_URL_HOST) : $referer;
        $candidate = strtolower(trim($candidate));

        if ($candidate === '' || !preg_match('/^[a-z0-9-]+\.amocrm\.(ru|com)$/', $candidate)) {
            throw new InvalidArgumentException('OAuth referer must be a valid amoCRM account domain.');
        }

        return $candidate;
    }
}
