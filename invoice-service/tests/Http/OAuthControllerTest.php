<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Http;

use InvoiceService\AmoCRM\ConnectedAccount;
use InvoiceService\AmoCRM\InMemoryAccountRepository;
use InvoiceService\AmoCRM\OAuthAccountService;
use InvoiceService\AmoCRM\OAuthGateway;
use InvoiceService\AmoCRM\OAuthToken;
use InvoiceService\Http\OAuthController;
use InvoiceService\Http\OperatorAuthenticator;
use InvoiceService\Http\Request;
use InvoiceService\OAuth\InMemoryOAuthStateRepository;
use InvoiceService\OAuth\OAuthStateService;
use InvoiceService\Services\InMemoryWebhookEndpointRepository;
use InvoiceService\Services\WebhookEndpointService;
use LogicException;
use PHPUnit\Framework\TestCase;

final class OAuthControllerTest extends TestCase
{
    public function testBareCallbackIsReachableForRedirectUriValidation(): void
    {
        $gateway = new class implements OAuthGateway {
            public function authorizationUrl(string $state): string
            {
                throw new LogicException('Not expected.');
            }

            public function exchangeAuthorizationCode(string $code, string $baseDomain): ConnectedAccount
            {
                throw new LogicException('Not expected.');
            }

            public function refresh(OAuthToken $token, string $baseDomain): OAuthToken
            {
                throw new LogicException('Not expected.');
            }
        };
        $controller = new OAuthController(
            new OperatorAuthenticator('operator-token'),
            new OAuthStateService(new InMemoryOAuthStateRepository()),
            $gateway,
            new OAuthAccountService(
                $gateway,
                new InMemoryAccountRepository(),
                new WebhookEndpointService(new InMemoryWebhookEndpointRepository(), 'https://invoice.sonic.expert'),
            ),
        );

        $response = $controller->callback(new Request('GET', '/oauth/callback'));

        self::assertSame(200, $response->status);
        self::assertSame(['status' => 'oauth_callback_ready'], $response->body);

        $partialCallback = $controller->callback(new Request('GET', '/oauth/callback', ['code' => 'incomplete']));
        self::assertSame(400, $partialCallback->status);
        self::assertSame(['error' => 'oauth_callback_parameters_invalid'], $partialCallback->body);
    }
}
