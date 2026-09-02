<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use AmoCRM\Client\AmoCRMApiClient;
use DateTimeImmutable;
use League\OAuth2\Client\Token\AccessToken;

final class OfficialOAuthGateway implements OAuthGateway
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly AmoRequestPacer $pacer,
    ) {
    }

    public function authorizationUrl(string $state): string
    {
        return $this->client()->getOAuthClient()->getAuthorizeUrl(['state' => $state]);
    }

    public function exchangeAuthorizationCode(string $code, string $baseDomain): ConnectedAccount
    {
        $client = $this->client($baseDomain);
        $this->pacer->beforeRequest();
        $accessToken = $client->getOAuthClient()->getAccessTokenByCode($code);
        if (!$accessToken instanceof AccessToken) {
            throw new \RuntimeException('amoCRM OAuth client returned an unsupported access-token type.');
        }
        $client->setAccessToken($accessToken);
        $this->pacer->beforeRequest();
        $account = $client->account()->getCurrent();

        return new ConnectedAccount(
            $account->getId(),
            $baseDomain,
            $this->toToken($accessToken),
        );
    }

    public function refresh(OAuthToken $token, string $baseDomain): OAuthToken
    {
        $client = $this->client($baseDomain);
        $this->pacer->beforeRequest();
        $refreshed = $client->getOAuthClient()->getAccessTokenByRefreshToken(new AccessToken([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires' => $token->expiresAt->getTimestamp(),
        ]));

        return $this->toToken($refreshed);
    }

    private function client(?string $baseDomain = null): AmoCRMApiClient
    {
        $client = new AmoCRMApiClient($this->clientId, $this->clientSecret, $this->redirectUri);
        if ($baseDomain !== null) {
            $client->setAccountBaseDomain($baseDomain);
        }

        return $client;
    }

    private function toToken(\League\OAuth2\Client\Token\AccessTokenInterface $token): OAuthToken
    {
        return new OAuthToken($token->getToken(), (string) $token->getRefreshToken(), new DateTimeImmutable('@' . $token->getExpires()));
    }
}
