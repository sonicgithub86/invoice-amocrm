<?php

declare(strict_types=1);

namespace InvoiceService\Revisions;

final readonly class InvoiceRevision
{
    public function __construct(
        public string $id,
        public int $accountId,
        public int $leadId,
        public string $snapshotHash,
        public int $sequenceValue,
        public string $invoiceNumber,
        public string $status,
        public ?string $fileUuid = null,
        public ?string $docxPath = null,
        public ?string $pdfPath = null,
        public ?string $pdfSha256 = null,
    ) {
    }

    public function withStatus(
        string $status,
        ?string $fileUuid = null,
        ?string $docxPath = null,
        ?string $pdfPath = null,
        ?string $pdfSha256 = null,
    ): self {
        return new self(
            $this->id,
            $this->accountId,
            $this->leadId,
            $this->snapshotHash,
            $this->sequenceValue,
            $this->invoiceNumber,
            $status,
            $fileUuid ?? $this->fileUuid,
            $docxPath ?? $this->docxPath,
            $pdfPath ?? $this->pdfPath,
            $pdfSha256 ?? $this->pdfSha256,
        );
    }
}
