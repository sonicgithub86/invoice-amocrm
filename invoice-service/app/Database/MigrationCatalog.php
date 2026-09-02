<?php

declare(strict_types=1);

namespace InvoiceService\Database;

final class MigrationCatalog
{
    /** @return list<string> */
    public static function initialSql(): array
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.php';

        return $migration['up'];
    }

    /** @return array<string, list<string>> */
    public static function all(): array
    {
        return [
            '001_initial_schema' => self::initialSql(),
            '002_allow_historical_invoice_snapshots' => self::historicalInvoiceSnapshotsSql(),
            '003_job_failures_and_validation_note_deduplication' => self::jobFailuresAndValidationNoteDeduplicationSql(),
            '004_invoice_revision_rendering_payload' => self::invoiceRevisionRenderingPayloadSql(),
        ];
    }

    /** @return list<string> */
    private static function historicalInvoiceSnapshotsSql(): array
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/002_allow_historical_invoice_snapshots.php';

        return $migration['up'];
    }

    /** @return list<string> */
    private static function jobFailuresAndValidationNoteDeduplicationSql(): array
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/003_job_failures_and_validation_note_deduplication.php';

        return $migration['up'];
    }

    /** @return list<string> */
    private static function invoiceRevisionRenderingPayloadSql(): array
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/004_invoice_revision_rendering_payload.php';

        return $migration['up'];
    }
}
