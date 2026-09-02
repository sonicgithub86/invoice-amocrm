<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DeploymentReadinessTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testPreflightAcceptsOwnerOnlyNonPlaceholderEnvironmentFiles(): void
    {
        [$invoiceEnv, $postgresEnv] = $this->writeValidEnvironmentFiles();

        [$exitCode, $output] = $this->runPreflight($invoiceEnv, $postgresEnv);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Environment files: PASS', $output);
        self::assertStringNotContainsString('super-secret-do-not-print', $output);
    }

    public function testPreflightNamesAMissingKeyWithoutPrintingOtherSecretValues(): void
    {
        [$invoiceEnv, $postgresEnv] = $this->writeValidEnvironmentFiles([
            'AMO_CLIENT_SECRET' => null,
        ]);

        [$exitCode, $output] = $this->runPreflight($invoiceEnv, $postgresEnv);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('AMO_CLIENT_SECRET', $output);
        self::assertStringNotContainsString('super-secret-do-not-print', $output);
    }

    public function testPreflightRejectsAPlaceholderWithoutPrintingIt(): void
    {
        [$invoiceEnv, $postgresEnv] = $this->writeValidEnvironmentFiles([
            'OPERATOR_ACCESS_TOKEN' => 'replace-with-a-real-secret',
        ]);

        [$exitCode, $output] = $this->runPreflight($invoiceEnv, $postgresEnv);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('OPERATOR_ACCESS_TOKEN', $output);
        self::assertStringNotContainsString('replace-with-a-real-secret', $output);
    }

    public function testPreflightRejectsGroupReadableSecretFiles(): void
    {
        [$invoiceEnv, $postgresEnv] = $this->writeValidEnvironmentFiles();
        chmod($invoiceEnv, 0640);

        [$exitCode, $output] = $this->runPreflight($invoiceEnv, $postgresEnv);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('permissions', strtolower($output));
        self::assertStringNotContainsString('super-secret-do-not-print', $output);
    }

    public function testDeploymentScriptsAreProjectScopedAndAvoidGlobalDockerCleanup(): void
    {
        $root = dirname(__DIR__, 2);
        $scripts = [
            'build-release.sh',
            'preflight-vps.sh',
            'smoke-render.sh',
            'verify-deploy.sh',
            'backup.sh',
            'restore-drill.sh',
            'rollback.sh',
        ];

        foreach ($scripts as $script) {
            $path = $root . '/scripts/' . $script;
            self::assertFileExists($path);
            self::assertTrue(is_executable($path), $script . ' must be executable');
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringNotContainsString('docker system prune', $contents);
            self::assertStringNotContainsString('docker volume prune', $contents);
            self::assertStringNotContainsString('docker compose down', $contents);
        }

        $common = file_get_contents($root . '/scripts/lib/deploy-common.sh');
        self::assertIsString($common);
        self::assertStringContainsString('COMPOSE_PROJECT_NAME="invoice-service"', $common);
        self::assertStringContainsString('assert_invoice_scope', $common);

        $rollback = file_get_contents($root . '/scripts/rollback.sh');
        self::assertIsString($rollback);
        self::assertStringContainsString('INVOICE_TRIGGER_DISABLED', $rollback);
        self::assertStringContainsString('docker volume inspect invoice-service-postgres invoice-service-documents', $rollback);

        $restoreDrill = file_get_contents($root . '/scripts/restore-drill.sh');
        self::assertIsString($restoreDrill);
        self::assertStringContainsString('stable_ready_count', $restoreDrill);
        self::assertStringContainsString('SELECT 1', $restoreDrill);
    }

    public function testReadinessCommandChecksDatabaseMigrationsStorageAndRendererBinaries(): void
    {
        $console = file_get_contents(dirname(__DIR__, 2) . '/bin/console');
        self::assertIsString($console);

        self::assertStringContainsString("if (\$command === 'readiness')", $console);
        self::assertStringContainsString("SELECT version FROM schema_migrations", $console);
        self::assertStringContainsString('is_writable($documentsDirectory)', $console);
        self::assertStringContainsString("'/usr/bin/soffice'", $console);
        self::assertStringContainsString("'/usr/bin/pdfinfo'", $console);
    }

    /**
     * @param array<string, string|null> $overrides
     * @return array{string, string}
     */
    private function writeValidEnvironmentFiles(array $overrides = []): array
    {
        $directory = sys_get_temp_dir() . '/invoice-deploy-test-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $this->temporaryDirectories[] = $directory;

        $invoice = [
            'APP_BASE_URL' => 'https://invoices.example.test',
            'DATABASE_URL' => 'pgsql:host=db;port=5432;dbname=invoice_service',
            'DATABASE_USER' => 'invoice_service',
            'DATABASE_PASSWORD' => 'super-secret-do-not-print',
            'AMO_CLIENT_ID' => 'client-id',
            'AMO_CLIENT_SECRET' => 'super-secret-do-not-print',
            'AMO_CREDENTIAL_KEY_V1' => base64_encode(str_repeat('k', 32)),
            'OPERATOR_ACCESS_TOKEN' => 'super-secret-do-not-print',
            'AMO_REDIRECT_PATH' => '/oauth/callback',
            'AMO_PRODUCT_LICENSE_FIELD_ID' => '123456',
            'INVOICE_DOCUMENTS_DIRECTORY' => '/var/lib/invoice-service/documents',
        ];
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($invoice[$key]);
            } else {
                $invoice[$key] = $value;
            }
        }

        $invoicePath = $directory . '/invoice.env';
        $postgresPath = $directory . '/postgres.env';
        file_put_contents($invoicePath, $this->formatEnvironment($invoice));
        file_put_contents($postgresPath, $this->formatEnvironment([
            'POSTGRES_DB' => 'invoice_service',
            'POSTGRES_USER' => 'invoice_service',
            'POSTGRES_PASSWORD' => 'super-secret-do-not-print',
        ]));
        chmod($invoicePath, 0600);
        chmod($postgresPath, 0600);

        return [$invoicePath, $postgresPath];
    }

    /** @param array<string, string> $values */
    private function formatEnvironment(array $values): string
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines) . "\n";
    }

    /** @return array{int, string} */
    private function runPreflight(string $invoiceEnv, string $postgresEnv): array
    {
        $root = dirname(__DIR__, 2);
        $command = [
            'bash', $root . '/scripts/preflight-vps.sh',
            '--check-env-only',
            '--invoice-env', $invoiceEnv,
            '--postgres-env', $postgresEnv,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, null, ['bypass_shell' => true]);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout . $stderr];
    }
}
