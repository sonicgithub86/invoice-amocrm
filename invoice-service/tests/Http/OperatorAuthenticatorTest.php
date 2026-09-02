<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Http;

use InvoiceService\Http\OperatorAuthenticator;
use InvoiceService\Http\Request;
use PHPUnit\Framework\TestCase;

final class OperatorAuthenticatorTest extends TestCase
{
    public function testAcceptsOnlyTheConfiguredBearerToken(): void
    {
        $authenticator = new OperatorAuthenticator('secret-for-tests');

        self::assertTrue($authenticator->allows(new Request('GET', '/', [], ['authorization' => 'Bearer secret-for-tests'])));
        self::assertFalse($authenticator->allows(new Request('GET', '/', [], ['authorization' => 'Bearer wrong'])));
        self::assertFalse($authenticator->allows(new Request('GET', '/')));
    }
}
