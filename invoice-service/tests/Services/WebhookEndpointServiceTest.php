<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Services;

use InvoiceService\Services\InMemoryWebhookEndpointRepository;
use InvoiceService\Services\WebhookEndpointService;
use PHPUnit\Framework\TestCase;

final class WebhookEndpointServiceTest extends TestCase
{
    public function testIssuesTwoCapabilityUrlsAndStoresOnlyHashes(): void
    {
        $repository = new InMemoryWebhookEndpointRepository();
        $service = new WebhookEndpointService($repository, 'https://invoices.example.test');

        $urls = $service->issueForAccount(7);

        self::assertArrayHasKey('automatic', $urls);
        self::assertArrayHasKey('rerun', $urls);
        self::assertStringStartsWith('https://invoices.example.test/webhooks/', $urls['automatic']);
        self::assertSame(2, $repository->count());
        self::assertFalse($repository->containsRawSecret(substr($urls['automatic'], strrpos($urls['automatic'], '/') + 1)));
    }
}
