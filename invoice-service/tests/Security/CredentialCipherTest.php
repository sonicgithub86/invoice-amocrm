<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Security;

use InvoiceService\Security\CredentialCipher;
use InvoiceService\Security\UnknownCredentialKeyException;
use PHPUnit\Framework\TestCase;

final class CredentialCipherTest extends TestCase
{
    public function testEncryptsTokensWithAccountBoundAssociatedData(): void
    {
        $cipher = new CredentialCipher([1 => str_repeat('a', 32)]);
        $encrypted = $cipher->encrypt('access-token', 42);

        self::assertStringStartsWith('v1:', $encrypted);
        self::assertSame('access-token', $cipher->decrypt($encrypted, 42));
    }

    public function testRejectsCiphertextForAnotherAccount(): void
    {
        $cipher = new CredentialCipher([1 => str_repeat('a', 32)]);
        $encrypted = $cipher->encrypt('access-token', 42);

        $this->expectException(UnknownCredentialKeyException::class);
        $cipher->decrypt($encrypted, 43);
    }
}
