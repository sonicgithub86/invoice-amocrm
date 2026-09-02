<?php

declare(strict_types=1);

namespace InvoiceService\Revisions;

use InvoiceService\Domain\InvoiceNumber;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Support\Uuid;

final class InMemoryInvoiceRevisionRepository implements InvoiceRevisionRepository
{
    /** @var array<string, InvoiceRevision> */
    private array $revisions = [];

    /** @var array<int, int> */
    private array $counters = [];

    /** @var array<string, string|null> */
    private array $current = [];

    /** @var array<string, string> */
    private array $validationHashes = [];

    public function reserve(int $accountId, InvoiceSnapshot $snapshot): Reservation
    {
        $currentRevisionId = $this->current[$accountId . ':' . $snapshot->leadId] ?? null;
        foreach ($this->revisions as $revision) {
            if (
                $revision->accountId === $accountId
                && $revision->leadId === $snapshot->leadId
                && (
                    $revision->status === 'manual_reconciliation_required'
                    || ($revision->snapshotHash === $snapshot->hash() && ($revision->status !== 'completed' || $revision->id === $currentRevisionId))
                )
            ) {
                return new Reservation(false, $revision);
            }
        }
        $next = $this->counters[$accountId] ?? 1;
        $this->counters[$accountId] = $next + 1;
        $revision = new InvoiceRevision(Uuid::v4(), $accountId, $snapshot->leadId, $snapshot->hash(), $next, InvoiceNumber::from($snapshot->leadId, $next)->value(), 'reserved');
        $this->revisions[$revision->id] = $revision;

        return new Reservation(true, $revision);
    }

    public function markRendered(InvoiceRevision $revision, string $docxPath, string $pdfPath, string $pdfSha256): InvoiceRevision
    {
        return $this->transition($revision, 'reserved', $revision->withStatus('rendered', docxPath: $docxPath, pdfPath: $pdfPath, pdfSha256: $pdfSha256));
    }

    public function beginUpload(InvoiceRevision $revision): InvoiceRevision
    {
        return $this->transition($revision, 'rendered', $revision->withStatus('uploading'));
    }

    public function markUploaded(InvoiceRevision $revision, string $fileUuid): InvoiceRevision
    {
        return $this->transition($revision, 'uploading', $revision->withStatus('uploaded', fileUuid: $fileUuid));
    }

    public function beginAttachment(InvoiceRevision $revision): InvoiceRevision
    {
        return $this->transition($revision, 'uploaded', $revision->withStatus('attaching'));
    }

    public function markAttached(InvoiceRevision $revision): InvoiceRevision
    {
        return $this->transition($revision, 'attaching', $revision->withStatus('attached'));
    }

    public function beginCurrentNote(InvoiceRevision $revision): InvoiceRevision
    {
        return $this->transition($revision, 'attached', $revision->withStatus('noting'));
    }

    public function markCurrent(InvoiceRevision $revision): InvoiceRevision
    {
        $current = $this->transition($revision, 'noting', $revision->withStatus('completed'));
        $key = $revision->accountId . ':' . $revision->leadId;
        $this->current[$key] = $revision->id;
        unset($this->validationHashes[$key]);

        return $current;
    }

    public function markManualReconciliation(InvoiceRevision $revision): InvoiceRevision
    {
        if (!in_array($revision->status, ['uploading', 'attaching', 'noting'], true)) {
            throw new \LogicException('Only an ambiguous external effect can require manual reconciliation.');
        }

        return $this->transition($revision, $revision->status, $revision->withStatus('manual_reconciliation_required'));
    }

    public function markValidationBlocked(int $accountId, int $leadId, array $reasons): bool
    {
        $key = $accountId . ':' . $leadId;
        $hash = hash('sha256', json_encode($reasons, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $shouldNote = ($this->validationHashes[$key] ?? null) !== $hash || ($this->current[$key] ?? null) !== null;
        $this->current[$key] = null;
        $this->validationHashes[$key] = $hash;

        return $shouldNote;
    }

    public function currentId(int $accountId, int $leadId): ?string
    {
        return $this->current[$accountId . ':' . $leadId] ?? null;
    }

    private function transition(InvoiceRevision $current, string $expectedStatus, InvoiceRevision $next): InvoiceRevision
    {
        $stored = $this->revisions[$current->id] ?? null;
        if ($stored === null || $stored->status !== $expectedStatus) {
            throw new \LogicException('Invoice revision transition is no longer valid.');
        }
        $this->revisions[$next->id] = $next;

        return $next;
    }
}
