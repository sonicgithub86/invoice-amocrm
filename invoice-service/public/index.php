<?php

declare(strict_types=1);

use InvoiceService\AmoCRM\OAuthAccountService;
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
use InvoiceService\Services\PdoWebhookEndpointRepository;
use InvoiceService\Services\WebhookEndpointService;

$config = require dirname(__DIR__) . '/config/bootstrap.php';
$connection = ConnectionFactory::fromConfig($config);
$stateService = new OAuthStateService(new PdoOAuthStateRepository($connection));
$gateway = new OfficialOAuthGateway($config->amoClientId(), $config->amoClientSecret(), $config->baseUrl() . '/oauth/callback');
$webhookEndpoints = new WebhookEndpointService(new PdoWebhookEndpointRepository($connection), $config->baseUrl());
$accountService = new OAuthAccountService($gateway, new PdoAccountRepository($connection, new CredentialCipher([1 => $config->credentialKey()])), $webhookEndpoints);
$oauth = new OAuthController(new OperatorAuthenticator($config->operatorAccessToken()), $stateService, $gateway, $accountService);

$response = (new Application($oauth))->handle(Request::fromGlobals());
http_response_code($response['status']);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response['body'], JSON_THROW_ON_ERROR);
