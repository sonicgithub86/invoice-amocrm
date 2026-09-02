<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final readonly class Request
{
    private const MAX_JSON_BODY_BYTES = 65536;

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
        public bool $bodyTooLarge = false,
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

        $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $bodyTooLarge = $contentLength !== false && $contentLength > self::MAX_JSON_BODY_BYTES;
        $body = $bodyTooLarge ? [] : $_POST;
        if (!$bodyTooLarge && $body === [] && str_contains((string) ($headers['content-type'] ?? ''), 'application/json')) {
            $rawBody = self::readJsonInput();
            if ($rawBody === null) {
                $bodyTooLarge = true;
            } elseif ($rawBody !== '') {
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
            $bodyTooLarge,
        );
    }

    private static function readJsonInput(): ?string
    {
        $input = fopen('php://input', 'rb');
        if ($input === false) {
            return '';
        }
        try {
            return self::readJsonStream($input);
        } finally {
            fclose($input);
        }
    }

    /**
     * @param resource $input
     * @internal Testable bounded decoder for the request input stream.
     */
    public static function readJsonStream($input): ?string
    {
        $body = '';
        while (!feof($input) && strlen($body) <= self::MAX_JSON_BODY_BYTES) {
            $chunk = fread($input, min(8192, self::MAX_JSON_BODY_BYTES + 1 - strlen($body)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return strlen($body) > self::MAX_JSON_BODY_BYTES ? null : $body;
    }
}
