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

    /** @return list<string> */
    public function invalidFields(): array
    {
        $invalid = [];
        if (!preg_match('/^\d{10}(\d{2})?$/', trim($this->inn))) {
            $invalid[] = 'ИНН (требуется 10 или 12 цифр)';
        }
        if (preg_match('/^\d{10}$/', trim($this->inn)) && trim($this->kpp) === '') {
            $invalid[] = 'КПП (обязательно для юридического лица)';
        } elseif (trim($this->kpp) !== '' && !preg_match('/^\d{9}$/', trim($this->kpp))) {
            $invalid[] = 'КПП (требуется 9 цифр)';
        }
        if (!preg_match('/^\d{13}(\d{2})?$/', trim($this->ogrn))) {
            $invalid[] = 'ОГРН/ОГРНИП (требуется 13 или 15 цифр)';
        }
        foreach ([
            'Расчётный счёт' => $this->settlementAccount,
            'Корреспондентский счёт' => $this->correspondentAccount,
        ] as $field => $value) {
            if (!preg_match('/^\d{20}$/', trim($value))) {
                $invalid[] = $field . ' (требуется 20 цифр)';
            }
        }
        if (!preg_match('/^\d{9}$/', trim($this->bik))) {
            $invalid[] = 'БИК (требуется 9 цифр)';
        }

        return $invalid;
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
