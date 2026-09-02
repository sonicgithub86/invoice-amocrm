<?php

declare(strict_types=1);

namespace InvoiceService\Documents;

use InvoiceService\Domain\Money;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Cell;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table;

final class InvoiceOfferDocumentBuilder
{
    public function build(InvoiceOfferDocumentData $data, string $targetPath): void
    {
        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new Language(Language::RU_RU));
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(10);
        $section = $word->addSection([
            'paperSize' => 'A4',
            'marginTop' => Converter::cmToTwip(1.4),
            'marginRight' => Converter::cmToTwip(1.7),
            'marginBottom' => Converter::cmToTwip(1.4),
            'marginLeft' => Converter::cmToTwip(1.7),
        ]);
        $this->addBankDetails($word, $section, $data->profile);
        $section->addTextBreak(1);
        $section->addText(
            sprintf('Счёт-оферта № %s от %s г.', $data->invoiceNumber->value(), $data->issuedAt->format('d.m.Y')),
            ['bold' => true, 'size' => 14],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 220],
        );
        $this->addParty($section, 'Сублицензиар:', $this->sublicensorDetails($data->profile));
        $this->addParty($section, 'Сублицензиат:', $this->buyerDetails($data));
        $section->addTextBreak(1);
        $section->addText('Внимание! Назначение платежа! Просто скопируйте!', ['bold' => true, 'color' => 'C00000'], ['spaceAfter' => 80]);
        $section->addText(
            sprintf('Оплата по Счёту-оферте № %s от %s г. НДС не облагается', $data->invoiceNumber->value(), $data->issuedAt->format('d.m.Y')),
            ['bold' => true],
            ['borderSize' => 6, 'borderColor' => '444444', 'spaceAfter' => 180, 'spaceBefore' => 80],
        );
        $this->addProducts($word, $section, $data);
        $this->addTotals($section, $data);
        $section->addTextBreak(2);
        $section->addText('Сублицензиар:' . PHP_EOL . 'ИП Сон Р.В.', [], ['alignment' => Jc::RIGHT]);
        $section->addPageBreak();
        $section->addText('Условия оферты:', ['bold' => true, 'size' => 14], ['spaceAfter' => 120]);
        $word->addNumberingStyle('offer-terms', [
            'type' => 'multilevel',
            'levels' => [[
                'format' => 'decimal',
                'text' => '%1.',
                'alignment' => Jc::LEFT,
                'left' => 720,
                'hanging' => 360,
                'tabPos' => 720,
            ]],
        ]);
        foreach ($data->profile->offerTerms as $term) {
            $section->addListItem($term, 0, [], 'offer-terms', ['spaceAfter' => 100, 'lineHeight' => 1.1]);
        }
        $section->addTextBreak(1);
        $section->addText('Сублицензиар:' . PHP_EOL . 'ИП Сон Р.В.', [], ['alignment' => Jc::RIGHT]);
        $footer = $section->addFooter();
        $footer->addPreserveText('Страница {PAGE} из {NUMPAGES}', [], ['alignment' => Jc::LEFT]);
        $footer->addPreserveText(sprintf('Счёт-оферта № %s от %s г.', $data->invoiceNumber->value(), $data->issuedAt->format('d.m.Y')), [], ['alignment' => Jc::CENTER]);

        $directory = dirname($targetPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Invoice document output directory could not be created.');
        }

        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($targetPath);
    }

    private function addBankDetails(PhpWord $word, \PhpOffice\PhpWord\Element\Section $section, InvoiceOfferProfile $profile): void
    {
        $style = ['borderSize' => 5, 'borderColor' => '333333', 'cellMargin' => 80, 'alignment' => JcTable::CENTER];
        $word->addTableStyle('bank-details', $style);
        $table = $section->addTable('bank-details');
        $table->addRow();
        $logo = $table->addCell(1750, ['vMerge' => 'restart', 'valign' => 'center']);
        $logo->addText('[ЛОГОТИП]', ['color' => '999999'], ['alignment' => Jc::CENTER]);
        $table->addCell(2800)->addText($profile->bankName);
        $table->addCell(900)->addText('БИК');
        $table->addCell(3000)->addText($profile->bik);
        $table->addRow();
        $table->addCell(1750, ['vMerge' => 'continue']);
        $table->addCell(2800)->addText('Банк получателя');
        $table->addCell(900)->addText('Сч. №');
        $table->addCell(3000)->addText($profile->correspondentAccount);
        $table->addRow();
        $table->addCell(1750, ['vMerge' => 'continue']);
        $table->addCell(2800)->addText('ИНН ' . $profile->inn);
        $table->addCell(900)->addText('Сч. №');
        $table->addCell(3000)->addText($profile->settlementAccount);
        $table->addRow();
        $table->addCell(1750, ['vMerge' => 'continue']);
        $table->addCell(6700, ['gridSpan' => 3])->addText('ИП Сон Р.В.');
        $table->addRow();
        $table->addCell(1750, ['vMerge' => 'continue']);
        $table->addCell(6700, ['gridSpan' => 3])->addText('Получатель');
    }

    private function addParty(\PhpOffice\PhpWord\Element\Section $section, string $label, string $details): void
    {
        $table = $section->addTable(['cellMargin' => 0, 'alignment' => JcTable::START]);
        $table->addRow();
        $table->addCell(1750, ['valign' => 'top'])->addText($label, ['bold' => true]);
        $table->addCell(7600, ['valign' => 'top'])->addText($details, [], ['alignment' => Jc::BOTH, 'spaceAfter' => 110]);
    }

    private function addProducts(PhpWord $word, \PhpOffice\PhpWord\Element\Section $section, InvoiceOfferDocumentData $data): void
    {
        $tableStyle = ['borderSize' => 5, 'borderColor' => '333333', 'cellMargin' => 70, 'alignment' => JcTable::CENTER];
        $word->addTableStyle('invoice-products', $tableStyle);
        $table = $section->addTable('invoice-products');
        $header = ['bold' => true];
        $table->addRow();
        foreach ([['№', 400], ['Наименование', 4050], ['Кол-во', 800], ['Ед. изм.', 900], ['Цена', 1350], ['Сумма', 1500]] as [$caption, $width]) {
            $table->addCell($width, ['valign' => 'center', 'bgColor' => 'F2F2F2'])->addText($caption, $header, ['alignment' => Jc::CENTER]);
        }
        foreach ($data->snapshot->licenseProducts as $index => $product) {
            $table->addRow();
            $table->addCell(400)->addText((string) ($index + 1), [], ['alignment' => Jc::CENTER]);
            $table->addCell(4050)->addText($product->name);
            $table->addCell(800)->addText((string) $product->quantity, [], ['alignment' => Jc::CENTER]);
            $table->addCell(900)->addText('шт.', [], ['alignment' => Jc::CENTER]);
            $table->addCell(1350)->addText($this->formatMoney($product->unitPrice), [], ['alignment' => Jc::RIGHT]);
            $table->addCell(1500)->addText($this->formatMoney($product->lineTotal()), [], ['alignment' => Jc::RIGHT]);
        }
    }

    private function addTotals(\PhpOffice\PhpWord\Element\Section $section, InvoiceOfferDocumentData $data): void
    {
        $formatted = $this->formatMoney($data->snapshot->total);
        $table = $section->addTable(['alignment' => JcTable::END]);
        foreach ([['Итого:', $formatted], ['Без налога (НДС)', '—'], ['Всего к оплате:', $formatted]] as [$label, $value]) {
            $table->addRow();
            $table->addCell(2800)->addText($label, ['bold' => true], ['alignment' => Jc::RIGHT]);
            $table->addCell(1800)->addText($value, ['bold' => true], ['alignment' => Jc::RIGHT]);
        }
        $section->addTextBreak(1);
        $section->addText(sprintf('Всего наименований %d, на сумму: %s руб.', count($data->snapshot->licenseProducts), $formatted));
    }

    private function sublicensorDetails(InvoiceOfferProfile $profile): string
    {
        return sprintf('%s, юридический адрес: %s, ИНН: %s, ОГРНИП: %s, Р/сч: %s, Банк: %s, К/сч: %s, БИК: %s. Действует на основании: %s.', $profile->sublicensorName, $profile->legalAddress, $profile->inn, $profile->ogrnIp, $profile->settlementAccount, $profile->bankName, $profile->correspondentAccount, $profile->bik, $profile->partnerAgreement);
    }

    private function buyerDetails(InvoiceOfferDocumentData $data): string
    {
        $buyer = $data->snapshot->buyer;
        $kpp = trim($buyer->kpp) === '' ? '' : ', КПП: ' . $buyer->kpp;

        return sprintf('%s, юридический адрес: %s, ИНН: %s%s, ОГРН/ОГРНИП: %s, Р/сч: %s, Банк: %s, К/сч: %s, БИК: %s.', $buyer->legalName, $buyer->legalAddress, $buyer->inn, $kpp, $buyer->ogrn, $buyer->settlementAccount, $buyer->bankName, $buyer->correspondentAccount, $buyer->bik);
    }

    private function formatMoney(Money $money): string
    {
        $whole = (string) intdiv($money->kopecks, 100);
        $groups = [];
        while ($whole !== '') {
            $groups[] = substr($whole, -3);
            $whole = substr($whole, 0, -3);
        }

        return implode(' ', array_reverse($groups)) . ',' . str_pad((string) ($money->kopecks % 100), 2, '0', STR_PAD_LEFT);
    }
}
