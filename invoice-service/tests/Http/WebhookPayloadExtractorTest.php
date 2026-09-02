<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Http;

use InvoiceService\Http\WebhookPayloadExtractor;
use InvoiceService\Http\WebhookPayloadInvalid;
use PHPUnit\Framework\TestCase;

final class WebhookPayloadExtractorTest extends TestCase
{
    public function testRejectsDecimalLeadIdThatDoesNotFitPhpInteger(): void
    {
        $extractor = new WebhookPayloadExtractor();

        $this->expectException(WebhookPayloadInvalid::class);
        $extractor->leadId(['lead_id' => '9223372036854775808']);
    }
}
