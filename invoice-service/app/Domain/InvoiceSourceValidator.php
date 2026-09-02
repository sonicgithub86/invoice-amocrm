<?php

declare(strict_types=1);

namespace InvoiceService\Domain;

final class InvoiceSourceValidator
{
    /** @return list<DealProduct> */
    public function licenseProducts(InvoiceSource $source): array
    {
        return array_values(array_filter($source->products, static fn (DealProduct $product): bool => $product->isAmoCrmLicense));
    }

    public function validate(InvoiceSource $source): InvoiceEligibility
    {
        $reasons = [];
        if ($source->buyer === null) {
            $reasons[] = 'К сделке не привязано юридическое лицо покупателя.';
        } else {
            foreach ($source->buyer->missingFields() as $field) {
                $reasons[] = 'Не заполнено поле компании: ' . $field . '.';
            }
            foreach ($source->buyer->invalidFields() as $field) {
                $reasons[] = 'Некорректно заполнено поле компании: ' . $field . '.';
            }
        }

        if ($this->licenseProducts($source) === []) {
            $reasons[] = 'В сделке нет товаров, отмеченных «Лицензия amoCRM». Услуги в этот счёт не включаются.';
        }

        return $reasons === [] ? InvoiceEligibility::valid() : InvoiceEligibility::invalid($reasons);
    }
}
