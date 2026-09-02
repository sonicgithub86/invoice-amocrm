<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use DateTimeImmutable;
use InvoiceService\Security\CredentialCipher;
use PDO;

final class PdoAccountRepository implements AccountRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly CredentialCipher $cipher,
    ) {
    }

    public function upsert(ConnectedAccount $account): AccountRecord
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO amocrm_accounts (
    account_id, base_domain, access_token_ciphertext, refresh_token_ciphertext,
    credential_key_version, token_expires_at, connection_state, updated_at
) VALUES (
    :account_id, :base_domain, :access_token_ciphertext, :refresh_token_ciphertext,
    1, :token_expires_at, 'connected', now()
)
ON CONFLICT (account_id) DO UPDATE SET
    base_domain = EXCLUDED.base_domain,
    access_token_ciphertext = EXCLUDED.access_token_ciphertext,
    refresh_token_ciphertext = EXCLUDED.refresh_token_ciphertext,
    credential_key_version = EXCLUDED.credential_key_version,
    token_expires_at = EXCLUDED.token_expires_at,
    connection_state = 'connected',
    updated_at = now()
RETURNING id
SQL);
        $statement->execute([
            'account_id' => $account->amoAccountId,
            'base_domain' => $account->baseDomain,
            'access_token_ciphertext' => $this->cipher->encrypt($account->token->accessToken, $account->amoAccountId),
            'refresh_token_ciphertext' => $this->cipher->encrypt($account->token->refreshToken, $account->amoAccountId),
            'token_expires_at' => $account->token->expiresAt->format(DATE_ATOM),
        ]);

        /** @var array{id: int|string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('amoCRM account could not be saved.');
        }

        return new AccountRecord((int) $row['id'], $account->amoAccountId, $account->baseDomain, $account->token, 'connected');
    }

    public function markReauthorizationRequired(int $accountId): void
    {
        $statement = $this->connection->prepare("UPDATE amocrm_accounts SET connection_state = 'reauthorization_required', updated_at = now() WHERE id = :id");
        $statement->execute(['id' => $accountId]);
    }

    public function findById(int $accountId): ?AccountRecord
    {
        $statement = $this->connection->prepare(<<<'SQL'
SELECT id, account_id, base_domain, access_token_ciphertext, refresh_token_ciphertext, token_expires_at, connection_state
FROM amocrm_accounts
WHERE id = :id
SQL);
        $statement->execute(['id' => $accountId]);
        /** @var array<string, int|string>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->toRecord($row);
    }

    public function findDueForRefresh(DateTimeImmutable $before): array
    {
        $statement = $this->connection->prepare(<<<'SQL'
SELECT id, account_id, base_domain, access_token_ciphertext, refresh_token_ciphertext, token_expires_at, connection_state
FROM amocrm_accounts
WHERE connection_state = 'connected' AND token_expires_at <= :before
ORDER BY id
SQL);
        $statement->execute(['before' => $before->format(DATE_ATOM)]);
        /** @var list<array<string, int|string>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): AccountRecord => $this->toRecord($row), $rows);
    }

    public function saveToken(AccountRecord $account, OAuthToken $token): AccountRecord
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE amocrm_accounts
SET access_token_ciphertext = :access_token_ciphertext,
    refresh_token_ciphertext = :refresh_token_ciphertext,
    credential_key_version = 1,
    token_expires_at = :token_expires_at,
    connection_state = 'connected',
    updated_at = now()
WHERE id = :id
SQL);
        $statement->execute([
            'id' => $account->id,
            'access_token_ciphertext' => $this->cipher->encrypt($token->accessToken, $account->amoAccountId),
            'refresh_token_ciphertext' => $this->cipher->encrypt($token->refreshToken, $account->amoAccountId),
            'token_expires_at' => $token->expiresAt->format(DATE_ATOM),
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('amoCRM account token could not be updated.');
        }

        return new AccountRecord($account->id, $account->amoAccountId, $account->baseDomain, $token, 'connected');
    }

    /** @param array<string, int|string> $row */
    private function toRecord(array $row): AccountRecord
    {
        $amoAccountId = (int) $row['account_id'];

        return new AccountRecord(
            (int) $row['id'],
            $amoAccountId,
            (string) $row['base_domain'],
            new OAuthToken(
                $this->cipher->decrypt((string) $row['access_token_ciphertext'], $amoAccountId),
                $this->cipher->decrypt((string) $row['refresh_token_ciphertext'], $amoAccountId),
                new DateTimeImmutable((string) $row['token_expires_at']),
            ),
            (string) $row['connection_state'],
        );
    }
}
