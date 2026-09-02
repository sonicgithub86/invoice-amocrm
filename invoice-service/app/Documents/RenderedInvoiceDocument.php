<?php

declare(strict_types=1);

namespace InvoiceService\Documents;

final readonly class RenderedInvoiceDocument
{
    public function __construct(
        public string $docxPath,
        public string $pdfPath,
        public string $pdfSha256,
    ) {
    }
}
