<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Integration;

use InvoiceService\Database\MigrationCatalog;
use PHPUnit\Framework\TestCase;

final class MigrationDefinitionTest extends TestCase
{
    public function testInitialMigrationDefinesTheInvoiceSafetyTables(): void
    {
        $sql = implode("\n", array_merge(...array_values(MigrationCatalog::all())));

        self::assertStringContainsString('amocrm_accounts', $sql);
        self::assertStringContainsString('webhook_endpoints', $sql);
        self::assertStringContainsString('invoice_revisions', $sql);
        self::assertStringContainsString('invoice_revisions_one_unfinished_snapshot', $sql);
        self::assertStringContainsString('invoice_sequences', $sql);
        self::assertStringContainsString('ADD COLUMN IF NOT EXISTS snapshot jsonb', $sql);
    }
}
