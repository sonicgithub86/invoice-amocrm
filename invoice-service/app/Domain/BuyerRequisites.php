<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

final readonly class BuyerRequisites
{
    public function __construct(
        public string $legalName,
        public string $inn,
        public string $kpp,
        public string $ogrn,
        public string $legalAddress,
        public string $settlementAccount,
        public string $bankName,
        public string $correspondentAccount,
        public string $bik,
    ) {
    }

    /** @return list<string> */
    public function missingFields(): array
    {
        $fields = [
            'Наименование организации' => $this->legalName,
            'ИНН' => $this->inn,
            'ОГРН/ОГРНИП' => $this->ogrn,
            'Юридический адрес' => $this->legalAddress,
            'Расчётный счёт' => $this->settlementAccount,
            'Банк' => $this->bankName,
            'Корреспондентский счёт' => $this->correspondentAccount,
            'БИК' => $this->bik,
        ];

        return array_keys(array_filter($fields, static fn (string $value): bool => trim($value) === ''));
    }

    /** @return array<string, string> */
    public function canonical(): array
    {
        return [
            'legal_name' => $this->legalName,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'ogrn' => $this->ogrn,
            'legal_address' => $this->legalAddress,
            'settlement_account' => $this->settlementAccount,
            'bank_name' => $this->bankName,
            'correspondent_account' => $this->correspondentAccount,
            'bik' => $this->bik,
        ];
    }
}
