<?php

declare(strict_types=1);

namespace InvoiceService\Services;

use InvoiceService\Jobs\InvoiceJob;

interface InvoiceJobProcessor
{
    public function generate(InvoiceJob $job): InvoiceGenerationResult;
}
