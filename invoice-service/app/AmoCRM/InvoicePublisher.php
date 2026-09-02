<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use InvoiceService\Revisions\InvoiceRevision;

interface InvoicePublisher
{
    public function upload(AccountRecord $account, InvoiceRevision $revision, string $pdfPath): string;

    public function attach(AccountRecord $account, InvoiceRevision $revision): void;

    /** @param list<string> $reasons */
    public function noteValidationBlocked(AccountRecord $account, int $leadId, array $reasons): void;

    public function noteCurrentInvoice(AccountRecord $account, InvoiceRevision $revision): void;
}
