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

            if (is_string($candidate) && $this->isPositiveIntString($candidate)) {
                return (int) $candidate;
            }
        }

        throw new WebhookPayloadInvalid('Webhook did not contain a valid lead ID.');
    }

    private function isPositiveIntString(string $value): bool
    {
        if (!ctype_digit($value)) {
            return false;
        }
        $normalized = ltrim($value, '0');
        if ($normalized === '') {
            return false;
        }
        $maximum = (string) PHP_INT_MAX;

        return strlen($normalized) < strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) <= 0);
    }
}
