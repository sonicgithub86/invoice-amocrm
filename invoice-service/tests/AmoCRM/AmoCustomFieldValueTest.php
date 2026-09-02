<?php

declare(strict_types=1);

namespace InvoiceService\Tests\AmoCRM;

use InvoiceService\AmoCRM\AmoCustomFieldValue;
use PHPUnit\Framework\TestCase;

final class AmoCustomFieldValueTest extends TestCase
{
    public function testExtractsTextAndCheckboxesFromAmoCrmApiShape(): void
    {
        $entity = ['custom_fields_values' => [
            ['field_id' => 10, 'values' => [['value' => '  ООО Покупатель ']]],
            ['field_id' => 11, 'values' => [['value' => '1']]],
        ]];

        self::assertSame('ООО Покупатель', AmoCustomFieldValue::first($entity, 10));
        self::assertTrue(AmoCustomFieldValue::isChecked($entity, 11));
        self::assertSame('', AmoCustomFieldValue::first($entity, 99));
    }
}
