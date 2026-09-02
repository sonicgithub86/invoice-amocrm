<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Integration;

use InvoiceService\Database\MigrationCatalog;
use PHPUnit\Framework\TestCase;

final class MigrationDefinitionTest extends TestCase
{
    public function testInitialMigrationDefinesTheInvoiceSafetyTables(): void
    {
        $sql = implode("\n", MigrationCatalog::initialSql());

        self::assertStringContainsString('amocrm_accounts', $sql);
        self::assertStringContainsString('webhook_endpoints', $sql);
        self::assertStringContainsString('invoice_revisions', $sql);
        self::assertStringContainsString('invoice_sequences', $sql);
    }
}
