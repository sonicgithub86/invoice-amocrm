<?php

declare(strict_types=1);

namespace InvoiceService\Revisions;

use InvoiceService\Domain\InvoiceSnapshot;

interface InvoiceRevisionRepository
{
    public function reserve(int $accountId, InvoiceSnapshot $snapshot): Reservation;

    public function markRendered(InvoiceRevision $revision, string $docxPath, string $pdfPath, string $pdfSha256): InvoiceRevision;

    public function beginUpload(InvoiceRevision $revision): InvoiceRevision;

    public function markUploaded(InvoiceRevision $revision, string $fileUuid): InvoiceRevision;

    public function beginAttachment(InvoiceRevision $revision): InvoiceRevision;

    public function markAttached(InvoiceRevision $revision): InvoiceRevision;

    public function beginCurrentNote(InvoiceRevision $revision): InvoiceRevision;

    public function markCurrent(InvoiceRevision $revision): InvoiceRevision;

    public function markManualReconciliation(InvoiceRevision $revision): InvoiceRevision;

    /** @param list<string> $reasons */
    public function markValidationBlocked(int $accountId, int $leadId, array $reasons): bool;
}
