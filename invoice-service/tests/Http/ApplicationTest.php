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
}
