<?php

declare(strict_types=1);

use InvoiceService\Config\AppConfig;

require dirname(__DIR__) . '/vendor/autoload.php';

return AppConfig::fromEnvironment();
