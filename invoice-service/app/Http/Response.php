<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final readonly class Response
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $body = [],
        public array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function json(int $status, array $body): self
    {
        return new self($status, $body);
    }

    public static function redirect(string $location): self
    {
        return new self(302, [], ['Location' => $location]);
    }
}
