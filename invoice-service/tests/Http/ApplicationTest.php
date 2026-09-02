<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Http;

use InvoiceService\Http\Application;
use InvoiceService\Http\Request;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testHealthCheckDoesNotRequireOAuthOrDatabaseConfiguration(): void
    {
        $response = (new Application())->handle(new Request('GET', '/healthz'));

        self::assertSame(200, $response->status);
        self::assertSame(['status' => 'ok'], $response->body);
    }

    public function testRejectsOversizedRequestBeforeItCanReachAnyRoute(): void
    {
        $response = (new Application())->handle(new Request('POST', '/unknown', bodyTooLarge: true));

        self::assertSame(413, $response->status);
        self::assertSame(['error' => 'payload_too_large'], $response->body);
    }

    public function testBoundedJsonReaderRejectsChunkedPayloadAboveLimit(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertNotFalse($stream);
        fwrite($stream, str_repeat('x', 65537));
        rewind($stream);

        try {
            self::assertNull(Request::readJsonStream($stream));
        } finally {
            fclose($stream);
        }
    }
}
