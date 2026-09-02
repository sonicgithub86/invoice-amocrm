<?php

declare(strict_types=1);

namespace InvoiceService\Security;

use InvalidArgumentException;

final class CredentialCipher
{
    /** @param array<int, string> $keys */
    public function __construct(private readonly array $keys)
    {
        foreach ($keys as $version => $key) {
            if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new InvalidArgumentException('Credential keys must use numeric versions and 32-byte material.');
            }
        }
    }

    public function encrypt(string $value, int $accountId, int $version = 1): string
    {
        $key = $this->keys[$version] ?? throw new UnknownCredentialKeyException('No key is available for this credential version.');
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, $this->associatedData($accountId), $nonce, $key);

        return sprintf('v%d:%s:%s', $version, base64_encode($nonce), base64_encode($ciphertext));
    }

    public function decrypt(string $serialized, int $accountId): string
    {
        $parts = explode(':', $serialized, 3);
        if (count($parts) !== 3 || preg_match('/^v([1-9][0-9]*)$/', $parts[0], $matches) !== 1) {
            throw new UnknownCredentialKeyException('Credential ciphertext format is invalid.');
        }

        $key = $this->keys[(int) $matches[1]] ?? throw new UnknownCredentialKeyException('Credential key version is unavailable.');
        $nonce = base64_decode($parts[1], true);
        $ciphertext = base64_decode($parts[2], true);
        if ($nonce === false || $ciphertext === false) {
            throw new UnknownCredentialKeyException('Credential ciphertext encoding is invalid.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $this->associatedData($accountId), $nonce, $key);
        if ($plaintext === false) {
            throw new UnknownCredentialKeyException('Credential ciphertext cannot be decrypted for this account.');
        }

        return $plaintext;
    }

    private function associatedData(int $accountId): string
    {
        return sprintf('amocrm-license-invoice:account:%d', $accountId);
    }
}
