<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Support;

use InvoiceService\Config\ConfigurationException;
use InvoiceService\Config\InvoiceSourceFieldMap;
use PHPUnit\Framework\TestCase;

final class InvoiceSourceFieldMapTest extends TestCase
{
    public function testKeepsKnownCompanyFieldDefaultsButRequiresTheManualLicenceFlag(): void
    {
        $map = InvoiceSourceFieldMap::fromArray(['AMO_PRODUCT_LICENSE_FIELD_ID' => '2263001']);

        self::assertSame(2262597, $map->legalName);
        self::assertSame(2263001, $map->licenseFlag);
    }

    public function testRejectsMissingLicenceFlagConfiguration(): void
    {
        $this->expectException(ConfigurationException::class);

        InvoiceSourceFieldMap::fromArray([]);
    }
}
