<?php

declare(strict_types=1);

use InvoiceService\AmoCRM\OAuthAccountService;
use InvoiceService\AmoCRM\AmoRequestPacer;
use InvoiceService\AmoCRM\OfficialOAuthGateway;
use InvoiceService\AmoCRM\PdoAccountRepository;
use InvoiceService\Database\ConnectionFactory;
use InvoiceService\Http\Application;
use InvoiceService\Http\OAuthController;
use InvoiceService\Http\OperatorAuthenticator;
use InvoiceService\Http\Request;
use InvoiceService\OAuth\OAuthStateService;
use InvoiceService\OAuth\PdoOAuthStateRepository;
use InvoiceService\Security\CredentialCipher;
use InvoiceService\Jobs\PdoInvoiceJobRepository;
use InvoiceService\Services\PdoWebhookEndpointRepository;
use InvoiceService\Services\WebhookCapabilityValidator;
use InvoiceService\Services\WebhookEndpointService;
use InvoiceService\Http\WebhookController;
use InvoiceService\Http\WebhookPayloadExtractor;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/healthz') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"status":"ok"}';
    exit;
}

$config = require dirname(__DIR__) . '/config/bootstrap.php';
$connection = ConnectionFactory::fromConfig($config);
$stateService = new OAuthStateService(new PdoOAuthStateRepository($connection));
$gateway = new OfficialOAuthGateway($config->amoClientId(), $config->amoClientSecret(), $config->baseUrl() . '/oauth/callback', new AmoRequestPacer());
$webhookEndpointRepository = new PdoWebhookEndpointRepository($connection);
$webhookEndpoints = new WebhookEndpointService($webhookEndpointRepository, $config->baseUrl());
$accountService = new OAuthAccountService($gateway, new PdoAccountRepository($connection, new CredentialCipher([1 => $config->credentialKey()])), $webhookEndpoints);
$oauth = new OAuthController(new OperatorAuthenticator($config->operatorAccessToken()), $stateService, $gateway, $accountService);

$webhooks = new WebhookController(new WebhookCapabilityValidator($webhookEndpointRepository), new WebhookPayloadExtractor(), new PdoInvoiceJobRepository($connection));
$response = (new Application($oauth, $webhooks))->handle(Request::fromGlobals());
http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response->body, JSON_THROW_ON_ERROR);
