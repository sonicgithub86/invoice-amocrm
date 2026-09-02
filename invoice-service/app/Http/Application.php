<?php

declare(strict_types=1);

namespace InvoiceService\Http;

final class Application
{
    public function __construct(
        private readonly ?OAuthController $oauth = null,
        private readonly ?WebhookController $webhooks = null,
    )
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

        if ($this->webhooks !== null && $request->method === 'POST' && preg_match('#^/webhooks/([0-9a-f-]{36})/([A-Za-z0-9_-]{16,})$#', $request->path, $matches) === 1) {
            return $this->webhooks->receive($request, $matches[1], $matches[2]);
        }

        return Response::json(404, ['error' => 'not_found']);
    }
}
