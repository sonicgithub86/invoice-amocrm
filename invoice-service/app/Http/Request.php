<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $headers = [],
        public array $body = [],
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($name) && is_string($value) && str_starts_with($name, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
            }
        }

        $body = $_POST;
        if ($body === []) {
            $rawBody = file_get_contents('php://input');
            if (is_string($rawBody) && $rawBody !== '' && str_contains((string) ($headers['content-type'] ?? ''), 'application/json')) {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        }

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            array_filter($_GET, 'is_string'),
            $headers,
            $body,
        );
    }
}
