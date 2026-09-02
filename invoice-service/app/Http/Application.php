<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final class Application
{
    public function __construct(private readonly ?OAuthController $oauth = null)
    {
    }

    public function handle(Request $request): Response
    {
        if ($request->method === 'GET' && $request->path === '/healthz') {
            return Response::json(200, ['status' => 'ok']);
        }

        if ($this->oauth !== null && $request->method === 'GET' && $request->path === '/operator/oauth/start') {
            return $this->oauth->start($request);
        }

        if ($this->oauth !== null && $request->method === 'GET' && $request->path === '/oauth/callback') {
            return $this->oauth->callback($request);
        }

        return Response::json(404, ['error' => 'not_found']);
    }
}
