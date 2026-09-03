<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use AmoCRM\Models\LeadModel;
use InvoiceService\Config\InvoiceSourceFieldMap;
use InvoiceService\Documents\InvoiceOfferProfile;
use InvoiceService\Domain\BuyerRequisites;
use InvoiceService\Domain\DealProduct;
use InvoiceService\Domain\InvoiceSource;
use InvoiceService\Domain\Money;

final readonly class OfficialInvoiceSourceReader implements InvoiceSourceReader
{
    public function __construct(
        private AmoClientFactory $clients,
        private InvoiceSourceFieldMap $fields,
        private InvoiceOfferProfile $profile,
        private AmoRequestPacer $pacer,
    ) {
    }

    public function read(AccountRecord $account, int $leadId): InvoiceSource
    {
        $client = $this->clients->create($account);
        $this->pacer->beforeRequest();
        $lead = $client->leads()->getOne($leadId, [LeadModel::CATALOG_ELEMENTS]);
        if ($lead === null) {
            throw new InvoiceSourceReadException('Сделка не найдена или недоступна интеграции.');
        }

        $buyer = null;
        $companyLink = $lead->getCompany();
        if ($companyLink !== null && $companyLink->getId() !== null) {
            $this->pacer->beforeRequest();
            $company = $client->companies()->getOne($companyLink->getId());
            if ($company !== null) {
                $buyer = $this->buyerFromCompany($company->toArray());
            }
        }

        $products = [];
        foreach ($lead->getCatalogElementsLinks() ?? [] as $link) {
            $catalogId = $link->getCatalogId();
            $productId = $link->getId();
            if ($catalogId === null || $productId === null) {
                continue;
            }
            $this->pacer->beforeRequest();
            $product = $client->catalogElements($catalogId)->getOne($productId);
            if ($product === null) {
                continue;
            }
            $productValues = $product->toArray();
            if (!AmoCustomFieldValue::isChecked($productValues, $this->fields->licenseFlag)) {
                continue;
            }
            $price = AmoCustomFieldValue::first($productValues, $this->fields->price);
            $quantity = $this->wholeQuantity($link->getQuantity());
            if ($price === '' || $quantity === null) {
                throw new InvoiceSourceReadException('У лицензии в сделке не указаны корректные цена или количество.');
            }
            $name = trim((string) $product->getName());
            if ($name === '') {
                throw new InvoiceSourceReadException('У лицензии в сделке отсутствует наименование товара.');
            }
            $products[] = new DealProduct($name, Money::fromDecimal($price), $quantity, true);
        }

        return new InvoiceSource($account->amoAccountId, $leadId, $buyer, $products, $this->profile->version);
    }

    /** @param array<string, mixed> $company */
    private function buyerFromCompany(array $company): BuyerRequisites
    {
        return new BuyerRequisites(
            AmoCustomFieldValue::first($company, $this->fields->legalName),
            AmoCustomFieldValue::first($company, $this->fields->inn),
            AmoCustomFieldValue::first($company, $this->fields->kpp),
            AmoCustomFieldValue::first($company, $this->fields->ogrn),
            AmoCustomFieldValue::first($company, $this->fields->legalAddress),
            AmoCustomFieldValue::first($company, $this->fields->settlementAccount),
            AmoCustomFieldValue::first($company, $this->fields->bankName),
            AmoCustomFieldValue::first($company, $this->fields->correspondentAccount),
            AmoCustomFieldValue::first($company, $this->fields->bik),
        );
    }

    private function wholeQuantity(int|float|null $quantity): ?int
    {
        if ($quantity === null || $quantity < 1 || floor((float) $quantity) !== (float) $quantity || $quantity > PHP_INT_MAX) {
            return null;
        }

        return (int) $quantity;
    }
}
