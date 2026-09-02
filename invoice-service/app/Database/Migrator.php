<?php

declare(strict_types=1);

namespace InvoiceService\Database;

use PDO;

final class Migrator
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function migrate(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version varchar(255) PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())');
            $applied = $this->connection->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

            foreach (MigrationCatalog::all() as $version => $statements) {
                if (in_array($version, $applied, true)) {
                    continue;
                }

                foreach ($statements as $statement) {
                    $this->connection->exec($statement);
                }

                $insert = $this->connection->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
                $insert->execute(['version' => $version]);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
