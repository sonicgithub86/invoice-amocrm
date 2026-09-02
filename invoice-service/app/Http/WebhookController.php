<?php

declare(strict_types=1);

namespace InvoiceService\Http;

use InvoiceService\Jobs\InvoiceJobRepository;
use InvoiceService\Services\WebhookCapabilityValidator;

final readonly class WebhookController
{
    public function __construct(
        private WebhookCapabilityValidator $capabilities,
        private WebhookPayloadExtractor $payloads,
        private InvoiceJobRepository $jobs,
    ) {
    }

    public function receive(Request $request, string $endpointId, string $secret): Response
    {
        $endpoint = $this->capabilities->validate($endpointId, $secret);
        if ($endpoint === null) {
            return Response::json(404, ['error' => 'not_found']);
        }

        try {
            $leadId = $this->payloads->leadId($request->body);
        } catch (WebhookPayloadInvalid) {
            return Response::json(400, ['error' => 'lead_id_missing']);
        }

        $payloadHash = hash('sha256', json_encode($this->sortPayload($request->body), JSON_THROW_ON_ERROR));
        $result = $this->jobs->enqueue($endpoint, $leadId, $payloadHash);

        return Response::json(202, ['status' => $result->created ? 'accepted' : 'already_queued']);
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array<int|string, mixed>
     */
    private function sortPayload(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $value[$key] = $this->sortPayload($item);
            }
        }
        ksort($value);

        return $value;
    }
}
