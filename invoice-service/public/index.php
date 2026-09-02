<?php

declare(strict_types=1);

use InvoiceService\Http\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$response = (new Application())->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
http_response_code($response['status']);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response['body'], JSON_THROW_ON_ERROR);
