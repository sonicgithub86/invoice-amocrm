<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final class Application
{
    /** @return array{status: int, body: array<string, string>} */
    public function handle(string $method, string $path): array
    {
        if ($method === 'GET' && $path === '/healthz') {
            return ['status' => 200, 'body' => ['status' => 'ok']];
        }

        return ['status' => 404, 'body' => ['error' => 'not_found']];
    }
}
