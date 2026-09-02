<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

interface OAuthGateway
{
    public function authorizationUrl(string $state): string;

    public function exchangeAuthorizationCode(string $code, string $baseDomain): ConnectedAccount;

    public function refresh(OAuthToken $token, string $baseDomain): OAuthToken;
}
