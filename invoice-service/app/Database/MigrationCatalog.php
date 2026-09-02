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
        ];
    }
}
