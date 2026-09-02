<?php

declare(strict_types=1);

namespace InvoiceService\Config;

final readonly class AppConfig
{
    private function __construct(
        private string $baseUrl,
        private string $databaseUrl,
        private string $amoClientId,
        private string $amoClientSecret,
        private string $credentialKey,
        private string $operatorAccessToken,
    ) {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        $required = [
            'APP_BASE_URL',
            'DATABASE_URL',
            'AMO_CLIENT_ID',
            'AMO_CLIENT_SECRET',
            'AMO_CREDENTIAL_KEY_V1',
            'OPERATOR_ACCESS_TOKEN',
        ];

        foreach ($required as $key) {
            if (!isset($values[$key]) || !is_string($values[$key]) || trim($values[$key]) === '') {
                throw new ConfigurationException(sprintf('Missing required configuration value: %s', $key));
            }
        }

        $baseUrl = rtrim((string) $values['APP_BASE_URL'], '/');
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($baseUrl, 'https://')) {
            throw new ConfigurationException('APP_BASE_URL must be a public HTTPS URL.');
        }

        $credentialKey = base64_decode((string) $values['AMO_CREDENTIAL_KEY_V1'], true);
        if ($credentialKey === false || strlen($credentialKey) !== 32) {
            throw new ConfigurationException('AMO_CREDENTIAL_KEY_V1 must be base64-encoded 32-byte key material.');
        }

        return new self(
            $baseUrl,
            (string) $values['DATABASE_URL'],
            (string) $values['AMO_CLIENT_ID'],
            (string) $values['AMO_CLIENT_SECRET'],
            $credentialKey,
            (string) $values['OPERATOR_ACCESS_TOKEN'],
        );
    }

    public static function fromEnvironment(): self
    {
        $keys = [
            'APP_BASE_URL',
            'DATABASE_URL',
            'AMO_CLIENT_ID',
            'AMO_CLIENT_SECRET',
            'AMO_CREDENTIAL_KEY_V1',
            'OPERATOR_ACCESS_TOKEN',
        ];
        $values = [];

        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $values[$key] = $value;
            }
        }

        return self::fromArray($values);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function databaseUrl(): string
    {
        return $this->databaseUrl;
    }

    public function amoClientId(): string
    {
        return $this->amoClientId;
    }

    public function amoClientSecret(): string
    {
        return $this->amoClientSecret;
    }

    public function credentialKey(): string
    {
        return $this->credentialKey;
    }

    public function operatorAccessToken(): string
    {
        return $this->operatorAccessToken;
    }
}
