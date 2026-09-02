<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final readonly class OperatorAuthenticator
{
    public function __construct(private string $token)
    {
    }

    public function allows(Request $request): bool
    {
        $authorization = $request->header('authorization');
        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            return false;
        }

        return hash_equals($this->token, substr($authorization, 7));
    }
}
