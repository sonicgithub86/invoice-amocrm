<?php

declare(strict_types=1);

namespace InvoiceService\Revisions;

use InvoiceService\Domain\InvoiceNumber;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Support\Uuid;
use PDO;

final class PdoInvoiceRevisionRepository implements InvoiceRevisionRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function reserve(int $accountId, InvoiceSnapshot $snapshot): Reservation
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->prepare(<<<'SQL'
INSERT INTO deal_invoice_states (account_id, lead_id)
VALUES (:account_id, :lead_id)
ON CONFLICT (account_id, lead_id) DO NOTHING
SQL)->execute(['account_id' => $accountId, 'lead_id' => $snapshot->leadId]);
            $lock = $this->connection->prepare('SELECT id FROM deal_invoice_states WHERE account_id = :account_id AND lead_id = :lead_id FOR UPDATE');
            $lock->execute(['account_id' => $accountId, 'lead_id' => $snapshot->leadId]);
            $existing = $this->connection->prepare(<<<'SQL'
SELECT revision.id, revision.account_id, revision.lead_id, revision.snapshot_hash, revision.sequence_value,
       revision.invoice_number, revision.status, revision.file_uuid, revision.docx_path, revision.pdf_path,
       revision.pdf_sha256
FROM invoice_revisions AS revision
JOIN deal_invoice_states AS state
  ON state.account_id = revision.account_id AND state.lead_id = revision.lead_id
WHERE revision.account_id = :account_id AND revision.lead_id = :lead_id
  AND (
      revision.status = 'manual_reconciliation_required'
      OR (
          revision.snapshot_hash = :snapshot_hash
          AND (
              revision.status IN ('reserved', 'rendered', 'uploading', 'uploaded', 'attaching', 'attached', 'noting')
              OR (revision.status = 'completed' AND state.current_revision_id = revision.id)
          )
      )
  )
ORDER BY (revision.status = 'manual_reconciliation_required') DESC, revision.created_at DESC
LIMIT 1
SQL);
            $existing->execute(['account_id' => $accountId, 'lead_id' => $snapshot->leadId, 'snapshot_hash' => $snapshot->hash()]);
            /** @var array<string, int|string|null>|false $row */
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $this->connection->commit();

                return new Reservation(false, $this->toRevision($row));
            }
            $this->connection->prepare('INSERT INTO invoice_sequences (account_id) VALUES (:account_id) ON CONFLICT (account_id) DO NOTHING')->execute(['account_id' => $accountId]);
            $sequence = $this->connection->prepare('UPDATE invoice_sequences SET next_value = next_value + 1 WHERE account_id = :account_id RETURNING next_value - 1');
            $sequence->execute(['account_id' => $accountId]);
            $sequenceValue = (int) $sequence->fetchColumn();
            $revision = new InvoiceRevision(Uuid::v4(), $accountId, $snapshot->leadId, $snapshot->hash(), $sequenceValue, InvoiceNumber::from($snapshot->leadId, $sequenceValue)->value(), 'reserved');
            $this->connection->prepare(<<<'SQL'
INSERT INTO invoice_revisions (id, account_id, lead_id, snapshot_hash, snapshot, sequence_value, invoice_number, status)
VALUES (:id, :account_id, :lead_id, :snapshot_hash, CAST(:snapshot AS jsonb), :sequence_value, :invoice_number, 'reserved')
SQL)->execute([
                'id' => $revision->id, 'account_id' => $accountId, 'lead_id' => $snapshot->leadId,
                'snapshot_hash' => $revision->snapshotHash, 'snapshot' => json_encode($snapshot->canonical(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'sequence_value' => $sequenceValue, 'invoice_number' => $revision->invoiceNumber,
            ]);
            $this->connection->prepare("UPDATE deal_invoice_states SET state = 'rendering', validation_hash = NULL, updated_at = now() WHERE account_id = :account_id AND lead_id = :lead_id")->execute(['account_id' => $accountId, 'lead_id' => $snapshot->leadId]);
            $this->connection->commit();

            return new Reservation(true, $revision);
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function markRendered(InvoiceRevision $revision, string $docxPath, string $pdfPath, string $pdfSha256): InvoiceRevision
    {
        $this->transition($revision->id, 'reserved', 'rendered', ['docx_path' => $docxPath, 'pdf_path' => $pdfPath, 'pdf_sha256' => $pdfSha256]);

        return $revision->withStatus('rendered', docxPath: $docxPath, pdfPath: $pdfPath, pdfSha256: $pdfSha256);
    }

    public function beginUpload(InvoiceRevision $revision): InvoiceRevision
    {
        $this->transition($revision->id, 'rendered', 'uploading', []);

        return $revision->withStatus('uploading');
    }

    public function markUploaded(InvoiceRevision $revision, string $fileUuid): InvoiceRevision
    {
        $this->transition($revision->id, 'uploading', 'uploaded', ['file_uuid' => $fileUuid]);

        return $revision->withStatus('uploaded', fileUuid: $fileUuid);
    }

    public function beginAttachment(InvoiceRevision $revision): InvoiceRevision
    {
        $this->transition($revision->id, 'uploaded', 'attaching', []);

        return $revision->withStatus('attaching');
    }

    public function markAttached(InvoiceRevision $revision): InvoiceRevision
    {
        $this->transition($revision->id, 'attaching', 'attached', []);

        return $revision->withStatus('attached');
    }

    public function beginCurrentNote(InvoiceRevision $revision): InvoiceRevision
    {
        $this->transition($revision->id, 'attached', 'noting', []);

        return $revision->withStatus('noting');
    }

    public function markCurrent(InvoiceRevision $revision): InvoiceRevision
    {
        $this->connection->beginTransaction();
        try {
            $this->transition($revision->id, 'noting', 'completed', []);
            $this->connection->prepare(<<<'SQL'
UPDATE deal_invoice_states
SET current_revision_id = :revision_id, state = 'current', validation_hash = NULL, updated_at = now()
WHERE account_id = :account_id AND lead_id = :lead_id
SQL)->execute(['revision_id' => $revision->id, 'account_id' => $revision->accountId, 'lead_id' => $revision->leadId]);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        return $revision->withStatus('completed');
    }

    public function markManualReconciliation(InvoiceRevision $revision): InvoiceRevision
    {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(<<<'SQL'
UPDATE invoice_revisions
SET status = 'manual_reconciliation_required',
    failure_reason = :failure_reason,
    updated_at = now()
WHERE id = :id AND status IN ('uploading', 'attaching', 'noting')
SQL);
            $statement->execute([
                'id' => $revision->id,
                'failure_reason' => 'External effect outcome requires manual reconciliation: ' . $revision->status,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('Invoice revision could not be marked for manual reconciliation.');
            }
            $this->connection->prepare(<<<'SQL'
UPDATE deal_invoice_states
SET state = 'manual_reconciliation_required', updated_at = now()
WHERE account_id = :account_id AND lead_id = :lead_id
SQL)->execute(['account_id' => $revision->accountId, 'lead_id' => $revision->leadId]);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        return $revision->withStatus('manual_reconciliation_required');
    }

    public function markValidationBlocked(int $accountId, int $leadId, array $reasons): bool
    {
        $hash = hash('sha256', json_encode($reasons, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO deal_invoice_states (account_id, lead_id, current_revision_id, state, validation_hash)
VALUES (:account_id, :lead_id, NULL, 'validation_blocked', :validation_hash)
ON CONFLICT (account_id, lead_id) DO UPDATE
SET current_revision_id = NULL,
    state = 'validation_blocked',
    validation_hash = EXCLUDED.validation_hash,
    updated_at = now()
WHERE deal_invoice_states.current_revision_id IS NOT NULL
   OR deal_invoice_states.state <> 'validation_blocked'
   OR deal_invoice_states.validation_hash IS DISTINCT FROM EXCLUDED.validation_hash
RETURNING id
SQL);
        $statement->execute(['account_id' => $accountId, 'lead_id' => $leadId, 'validation_hash' => $hash]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, string> $values */
    private function transition(string $revisionId, string $expectedStatus, string $status, array $values): void
    {
        $columns = ['status = :status', 'updated_at = now()'];
        $parameters = ['id' => $revisionId, 'expected_status' => $expectedStatus, 'status' => $status];
        foreach ($values as $column => $value) {
            $columns[] = $column . ' = :' . $column;
            $parameters[$column] = $value;
        }
        $statement = $this->connection->prepare('UPDATE invoice_revisions SET ' . implode(', ', $columns) . ' WHERE id = :id AND status = :expected_status');
        $statement->execute($parameters);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Invoice revision could not be updated.');
        }
    }

    /** @param array<string, int|string|null> $row */
    private function toRevision(array $row): InvoiceRevision
    {
        return new InvoiceRevision((string) $row['id'], (int) $row['account_id'], (int) $row['lead_id'], (string) $row['snapshot_hash'], (int) $row['sequence_value'], (string) $row['invoice_number'], (string) $row['status'], $row['file_uuid'] === null ? null : (string) $row['file_uuid'], $row['docx_path'] === null ? null : (string) $row['docx_path'], $row['pdf_path'] === null ? null : (string) $row['pdf_path'], $row['pdf_sha256'] === null ? null : (string) $row['pdf_sha256']);
    }
}
