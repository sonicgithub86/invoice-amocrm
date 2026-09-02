<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Support;

use InvoiceService\Config\AppConfig;
use InvoiceService\Config\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testRejectsMissingRequiredServiceSecrets(): void
    {
        $this->expectException(ConfigurationException::class);

        AppConfig::fromArray([]);
    }

    public function testAcceptsOnlyAnExplicitServiceConfiguration(): void
    {
        $config = AppConfig::fromArray([
            'APP_BASE_URL' => 'https://invoice.example.test',
            'DATABASE_URL' => 'pgsql:host=db;port=5432;dbname=invoices',
            'AMO_CLIENT_ID' => 'client-id',
            'AMO_CLIENT_SECRET' => 'client-secret',
            'AMO_CREDENTIAL_KEY_V1' => base64_encode(str_repeat('a', 32)),
            'OPERATOR_ACCESS_TOKEN' => 'operator-token',
        ]);

        self::assertSame('https://invoice.example.test', $config->baseUrl());
    }
}
