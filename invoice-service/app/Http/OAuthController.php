<?php

declare(strict_types=1);

namespace InvoiceService\Http;

use InvoiceService\AmoCRM\OAuthAccountService;
use InvoiceService\AmoCRM\OAuthGateway;
use InvoiceService\OAuth\OAuthStateService;
use InvoiceService\OAuth\OAuthStateUnavailable;

final readonly class OAuthController
{
    public function __construct(
        private OperatorAuthenticator $operatorAuthenticator,
        private OAuthStateService $stateService,
        private OAuthGateway $gateway,
        private OAuthAccountService $accountService,
    ) {
    }

    public function start(Request $request): Response
    {
        if (!$this->operatorAuthenticator->allows($request)) {
            return Response::json(401, ['error' => 'unauthorized']);
        }

        return Response::redirect($this->gateway->authorizationUrl($this->stateService->issue()));
    }

    public function callback(Request $request): Response
    {
        $code = $request->query['code'] ?? '';
        $state = $request->query['state'] ?? '';
        $referer = $request->query['referer'] ?? '';
        if ($code === '' && $state === '' && $referer === '') {
            return Response::json(200, ['status' => 'oauth_callback_ready']);
        }

        if ($code === '' || $state === '' || $referer === '') {
            return Response::json(400, ['error' => 'oauth_callback_parameters_invalid']);
        }

        try {
            $this->stateService->consume($state);
            $result = $this->accountService->connect($code, $referer);
        } catch (OAuthStateUnavailable) {
            return Response::json(400, ['error' => 'oauth_state_invalid']);
        } catch (\InvalidArgumentException) {
            return Response::json(400, ['error' => 'oauth_account_invalid']);
        } catch (\Throwable) {
            return Response::json(502, ['error' => 'oauth_connection_failed']);
        }

        return Response::json(201, [
            'account_id' => $result['account']->amoAccountId,
            'webhook_urls' => $result['endpoints'],
        ]);
    }
}
