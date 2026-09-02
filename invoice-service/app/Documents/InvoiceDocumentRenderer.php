<?php

declare(strict_types=1);

namespace InvoiceService\Documents;

use InvoiceService\Revisions\InvoiceRevision;

interface InvoiceDocumentRenderer
{
    public function render(InvoiceOfferDocumentData $data, InvoiceRevision $revision): RenderedInvoiceDocument;
}
