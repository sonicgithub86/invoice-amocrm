<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use AmoCRM\Client\AmoCRMApiClient;
use InvoiceService\Config\AppConfig;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

final readonly class AmoClientFactory
{
    public function __construct(
        private AppConfig $config,
        private AccountRepository $accounts,
    ) {
    }

    public function create(AccountRecord $account): AmoCRMApiClient
    {
        $client = new AmoCRMApiClient($this->config->amoClientId(), $this->config->amoClientSecret(), $this->config->baseUrl() . '/oauth/callback');
        $client->setAccountBaseDomain($account->baseDomain);
        $client->setAccessToken(new AccessToken([
            'access_token' => $account->token->accessToken,
            'refresh_token' => $account->token->refreshToken,
            'expires' => $account->token->expiresAt->getTimestamp(),
        ]));
        $client->onAccessTokenRefresh(function (AccessTokenInterface $token) use ($account): void {
            $this->accounts->saveToken($account, new OAuthToken(
                $token->getToken(),
                (string) $token->getRefreshToken(),
                new \DateTimeImmutable('@' . $token->getExpires()),
            ));
        });

        return $client;
    }
}
