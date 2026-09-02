<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use DateTimeImmutable;

final class InMemoryAccountRepository implements AccountRepository
{
    /** @var array<int, AccountRecord> */
    private array $accounts = [];

    public function upsert(ConnectedAccount $account): AccountRecord
    {
        $existing = $this->accounts[$account->amoAccountId] ?? null;
        $id = $existing !== null ? $existing->id : count($this->accounts) + 1;
        $record = new AccountRecord($id, $account->amoAccountId, $account->baseDomain, $account->token, 'connected');
        $this->accounts[$account->amoAccountId] = $record;

        return $record;
    }

    public function markReauthorizationRequired(int $accountId): void
    {
        $record = $this->accounts[$accountId] ?? null;
        if ($record !== null) {
            $this->accounts[$accountId] = new AccountRecord($record->id, $record->amoAccountId, $record->baseDomain, $record->token, 'reauthorization_required');
        }
    }

    public function findById(int $accountId): ?AccountRecord
    {
        foreach ($this->accounts as $record) {
            if ($record->id === $accountId) {
                return $record;
            }
        }

        return null;
    }

    public function findDueForRefresh(DateTimeImmutable $before): array
    {
        return array_values(array_filter(
            $this->accounts,
            static fn (AccountRecord $record): bool => $record->connectionState === 'connected' && $record->token->expiresAt <= $before,
        ));
    }

    public function saveToken(AccountRecord $account, OAuthToken $token): AccountRecord
    {
        $updated = new AccountRecord($account->id, $account->amoAccountId, $account->baseDomain, $token, 'connected');
        $this->accounts[$account->amoAccountId] = $updated;

        return $updated;
    }
}
