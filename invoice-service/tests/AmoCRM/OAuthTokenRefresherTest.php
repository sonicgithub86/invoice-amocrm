<?php

declare(strict_types=1);

namespace InvoiceService\Tests\AmoCRM;

use DateTimeImmutable;
use InvoiceService\AmoCRM\ConnectedAccount;
use InvoiceService\AmoCRM\InMemoryAccountRepository;
use InvoiceService\AmoCRM\OAuthGateway;
use InvoiceService\AmoCRM\OAuthToken;
use InvoiceService\AmoCRM\OAuthTokenRefresher;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use PHPUnit\Framework\TestCase;

final class OAuthTokenRefresherTest extends TestCase
{
    public function testRefreshesOnlyAccountsWhoseTokensAreNearExpiry(): void
    {
        $accounts = new InMemoryAccountRepository();
        $due = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('old', 'refresh', new DateTimeImmutable('2026-09-02T12:02:00+00:00'))));
        $accounts->upsert(new ConnectedAccount(11, 'other.amocrm.ru', new OAuthToken('later', 'refresh', new DateTimeImmutable('2026-09-02T13:00:00+00:00'))));
        $gateway = new class implements OAuthGateway {
            public function authorizationUrl(string $state): string { return 'https://example.test/' . $state; }
            public function exchangeAuthorizationCode(string $code, string $baseDomain): ConnectedAccount { throw new \LogicException('not used'); }
            public function refresh(OAuthToken $token, string $baseDomain): OAuthToken { return new OAuthToken('new-access', 'new-refresh', new DateTimeImmutable('2026-09-02T13:00:00+00:00')); }
        };

        $updated = (new OAuthTokenRefresher($accounts, $gateway))->refreshDue(new DateTimeImmutable('2026-09-02T12:05:00+00:00'));

        self::assertCount(1, $updated);
        self::assertSame($due->id, $updated[0]->id);
        self::assertSame('new-access', $accounts->findById($due->id)?->token->accessToken);
    }

    public function testKeepsAccountConnectedWhenRefreshFailsTransiently(): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('old', 'refresh', new DateTimeImmutable('2026-09-02T12:02:00+00:00'))));
        $gateway = $this->failingGateway(new \RuntimeException('timeout'));

        (new OAuthTokenRefresher($accounts, $gateway))->refreshDue(new DateTimeImmutable('2026-09-02T12:05:00+00:00'));

        self::assertSame('connected', $accounts->findById($account->id)?->connectionState);
    }

    public function testRequiresReauthorizationOnlyWhenRefreshTokenIsRejected(): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('old', 'refresh', new DateTimeImmutable('2026-09-02T12:02:00+00:00'))));
        $gateway = $this->failingGateway(new IdentityProviderException('invalid grant', 400, ['error' => 'invalid_grant']));

        (new OAuthTokenRefresher($accounts, $gateway))->refreshDue(new DateTimeImmutable('2026-09-02T12:05:00+00:00'));

        self::assertSame('reauthorization_required', $accounts->findById($account->id)?->connectionState);
    }

    public function testDoesNotDisableAccountWhenAnotherRefreshAlreadyRotatedTheToken(): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = $accounts->upsert(new ConnectedAccount(10, 'tenant.amocrm.ru', new OAuthToken('old', 'refresh', new DateTimeImmutable('2026-09-02T12:02:00+00:00'))));
        $gateway = new class($accounts, $account) implements OAuthGateway {
            public function __construct(private InMemoryAccountRepository $accounts, private \InvoiceService\AmoCRM\AccountRecord $account) {}
            public function authorizationUrl(string $state): string { return 'https://example.test/' . $state; }
            public function exchangeAuthorizationCode(string $code, string $baseDomain): ConnectedAccount { throw new \LogicException('not used'); }
            public function refresh(OAuthToken $token, string $baseDomain): OAuthToken
            {
                $this->accounts->saveToken($this->account, new OAuthToken('new-access', 'new-refresh', new DateTimeImmutable('2026-09-02T13:00:00+00:00')));
                throw new IdentityProviderException('invalid grant', 400, ['error' => 'invalid_grant']);
            }
        };

        (new OAuthTokenRefresher($accounts, $gateway))->refreshDue(new DateTimeImmutable('2026-09-02T12:05:00+00:00'));

        self::assertSame('connected', $accounts->findById($account->id)?->connectionState);
        self::assertSame('new-refresh', $accounts->findById($account->id)?->token->refreshToken);
    }

    private function failingGateway(\Throwable $exception): OAuthGateway
    {
        return new class($exception) implements OAuthGateway {
            public function __construct(private \Throwable $exception) {}
            public function authorizationUrl(string $state): string { return 'https://example.test/' . $state; }
            public function exchangeAuthorizationCode(string $code, string $baseDomain): ConnectedAccount { throw new \LogicException('not used'); }
            public function refresh(OAuthToken $token, string $baseDomain): OAuthToken { throw $this->exception; }
        };
    }
}
