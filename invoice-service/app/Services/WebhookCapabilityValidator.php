<?php

declare(strict_types=1);

namespace InvoiceService\Services;

final readonly class WebhookCapabilityValidator
{
    public function __construct(private WebhookEndpointRepository $endpoints)
    {
    }

    public function validate(string $endpointId, string $secret): ?WebhookEndpointRecord
    {
        $endpoint = $this->endpoints->findEnabled($endpointId);
        if ($endpoint === null || !hash_equals($endpoint->secretHash, hash('sha256', $secret))) {
            return null;
        }

        return $endpoint;
    }
}
