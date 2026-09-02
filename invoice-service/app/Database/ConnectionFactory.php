<?php

declare(strict_types=1);

namespace InvoiceService\Database;

use InvoiceService\Config\AppConfig;
use PDO;

final class ConnectionFactory
{
    public static function fromConfig(AppConfig $config): PDO
    {
        return new PDO(
            $config->databaseUrl(),
            getenv('DATABASE_USER') ?: null,
            getenv('DATABASE_PASSWORD') ?: null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
