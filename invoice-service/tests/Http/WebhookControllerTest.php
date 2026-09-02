<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Http;

use InvoiceService\Http\Request;
use InvoiceService\Http\WebhookController;
use InvoiceService\Http\WebhookPayloadExtractor;
use InvoiceService\Jobs\InMemoryInvoiceJobRepository;
use InvoiceService\Services\InMemoryWebhookEndpointRepository;
use InvoiceService\Services\WebhookCapabilityValidator;
use PHPUnit\Framework\TestCase;

final class WebhookControllerTest extends TestCase
{
    public function testCapabilityWebhookQueuesOneJobAndDeduplicatesRetries(): void
    {
        $endpoints = new InMemoryWebhookEndpointRepository();
        $endpointId = '6f4045e5-01d0-4a31-aaf4-69a907f0975f';
        $secret = 'this-is-a-sufficiently-long-secret';
        $endpoints->replace(7, 'automatic', $endpointId, hash('sha256', $secret));
        $jobs = new InMemoryInvoiceJobRepository();
        $controller = new WebhookController(new WebhookCapabilityValidator($endpoints), new WebhookPayloadExtractor(), $jobs);
        $request = new Request('POST', '/webhooks/' . $endpointId . '/' . $secret, [], [], ['lead_id' => '28457194']);

        $first = $controller->receive($request, $endpointId, $secret);
        $second = $controller->receive($request, $endpointId, $secret);

        self::assertSame(202, $first->status);
        self::assertSame('accepted', $first->body['status']);
        self::assertSame('already_queued', $second->body['status']);
        self::assertSame(1, $jobs->count());
    }

    public function testInvalidCapabilityDoesNotRevealOrQueueAnything(): void
    {
        $endpoints = new InMemoryWebhookEndpointRepository();
        $endpoints->replace(7, 'automatic', '6f4045e5-01d0-4a31-aaf4-69a907f0975f', hash('sha256', 'right-secret'));
        $jobs = new InMemoryInvoiceJobRepository();
        $controller = new WebhookController(new WebhookCapabilityValidator($endpoints), new WebhookPayloadExtractor(), $jobs);

        $response = $controller->receive(new Request('POST', '/', [], [], ['lead_id' => '28457194']), '6f4045e5-01d0-4a31-aaf4-69a907f0975f', 'wrong-secret');

        self::assertSame(404, $response->status);
        self::assertSame(0, $jobs->count());
    }
}
