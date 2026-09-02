<?php

declare(strict_types=1);

namespace InvoiceService\Config;

final readonly class InvoiceSourceFieldMap
{
    public function __construct(
        public int $legalName,
        public int $inn,
        public int $kpp,
        public int $ogrn,
        public int $legalAddress,
        public int $settlementAccount,
        public int $bankName,
        public int $correspondentAccount,
        public int $bik,
        public int $price,
        public int $licenseFlag,
    ) {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        $defaults = [
            'AMO_COMPANY_FIELD_LEGAL_NAME' => 2262597,
            'AMO_COMPANY_FIELD_INN' => 2250368,
            'AMO_COMPANY_FIELD_KPP' => 2250370,
            'AMO_COMPANY_FIELD_OGRN' => 2250374,
            'AMO_COMPANY_FIELD_LEGAL_ADDRESS' => 2262599,
            'AMO_COMPANY_FIELD_SETTLEMENT_ACCOUNT' => 2197556,
            'AMO_COMPANY_FIELD_BANK_NAME' => 2250380,
            'AMO_COMPANY_FIELD_CORRESPONDENT_ACCOUNT' => 2250378,
            'AMO_COMPANY_FIELD_BIK' => 2250372,
            'AMO_PRODUCT_FIELD_PRICE' => 2022409,
        ];
        $resolved = [];
        foreach ($defaults as $key => $default) {
            $resolved[$key] = self::positiveInt($values[$key] ?? $default, $key);
        }
        $licenseFlag = self::positiveInt($values['AMO_PRODUCT_LICENSE_FIELD_ID'] ?? null, 'AMO_PRODUCT_LICENSE_FIELD_ID');

        return new self(
            $resolved['AMO_COMPANY_FIELD_LEGAL_NAME'],
            $resolved['AMO_COMPANY_FIELD_INN'],
            $resolved['AMO_COMPANY_FIELD_KPP'],
            $resolved['AMO_COMPANY_FIELD_OGRN'],
            $resolved['AMO_COMPANY_FIELD_LEGAL_ADDRESS'],
            $resolved['AMO_COMPANY_FIELD_SETTLEMENT_ACCOUNT'],
            $resolved['AMO_COMPANY_FIELD_BANK_NAME'],
            $resolved['AMO_COMPANY_FIELD_CORRESPONDENT_ACCOUNT'],
            $resolved['AMO_COMPANY_FIELD_BIK'],
            $resolved['AMO_PRODUCT_FIELD_PRICE'],
            $licenseFlag,
        );
    }

    public static function fromEnvironment(): self
    {
        $keys = [
            'AMO_COMPANY_FIELD_LEGAL_NAME', 'AMO_COMPANY_FIELD_INN', 'AMO_COMPANY_FIELD_KPP',
            'AMO_COMPANY_FIELD_OGRN', 'AMO_COMPANY_FIELD_LEGAL_ADDRESS', 'AMO_COMPANY_FIELD_SETTLEMENT_ACCOUNT',
            'AMO_COMPANY_FIELD_BANK_NAME', 'AMO_COMPANY_FIELD_CORRESPONDENT_ACCOUNT', 'AMO_COMPANY_FIELD_BIK',
            'AMO_PRODUCT_FIELD_PRICE', 'AMO_PRODUCT_LICENSE_FIELD_ID',
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

    private static function positiveInt(mixed $value, string $name): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new ConfigurationException(sprintf('%s must be a positive amoCRM field ID.', $name));
        }

        return (int) $value;
    }
}
