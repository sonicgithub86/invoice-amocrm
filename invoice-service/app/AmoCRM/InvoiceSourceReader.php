<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use InvoiceService\Domain\InvoiceSource;

interface InvoiceSourceReader
{
    public function read(AccountRecord $account, int $leadId): InvoiceSource;
}
