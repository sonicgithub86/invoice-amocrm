<?php

declare(strict_types=1);

namespace InvoiceService\Services;

use InvoiceService\Support\Uuid;

final class WebhookEndpointService
{
    public function __construct(
        private readonly WebhookEndpointRepository $repository,
        private readonly string $baseUrl,
    ) {
    }

    /** @return array{automatic: string, rerun: string} */
    public function issueForAccount(int $accountId): array
    {
        return [
            'automatic' => $this->issue($accountId, 'automatic'),
            'rerun' => $this->issue($accountId, 'rerun'),
        ];
    }

    private function issue(int $accountId, string $triggerKind): string
    {
        $endpointId = Uuid::v4();
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->repository->replace($accountId, $triggerKind, $endpointId, hash('sha256', $secret));

        return sprintf('%s/webhooks/%s/%s', rtrim($this->baseUrl, '/'), $endpointId, $secret);
    }
}
