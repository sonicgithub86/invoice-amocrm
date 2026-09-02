<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use DateTimeImmutable;

interface AccountRepository
{
    public function upsert(ConnectedAccount $account): AccountRecord;

    public function markReauthorizationRequired(int $accountId): void;

    public function findById(int $accountId): ?AccountRecord;

    /** @return list<AccountRecord> */
    public function findDueForRefresh(DateTimeImmutable $before): array;

    public function saveToken(AccountRecord $account, OAuthToken $token): AccountRecord;
}
