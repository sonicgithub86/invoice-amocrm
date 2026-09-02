<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final class WebhookPayloadExtractor
{
    /** @param array<string, mixed> $payload */
    public function leadId(array $payload): int
    {
        $candidates = [
            $payload['lead_id'] ?? null,
            $payload['lead'] ?? null,
            $payload['leads']['status'][0]['id'] ?? null,
            $payload['leads']['add'][0]['id'] ?? null,
            $payload['leads']['update'][0]['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_int($candidate) && $candidate > 0) {
                return $candidate;
            }

            if (is_string($candidate) && ctype_digit($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        throw new WebhookPayloadInvalid('Webhook did not contain a valid lead ID.');
    }
}
