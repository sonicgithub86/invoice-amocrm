<?php

declare(strict_types=1);

namespace InvoiceService\Services;

use DateTimeImmutable;
use InvoiceService\AmoCRM\AccountRepository;
use InvoiceService\AmoCRM\InvoicePublisher;
use InvoiceService\AmoCRM\InvoiceSourceReadException;
use InvoiceService\AmoCRM\InvoiceSourceReader;
use InvoiceService\Documents\InvoiceOfferDocumentData;
use InvoiceService\Documents\InvoiceOfferProfile;
use InvoiceService\Documents\InvoiceDocumentRenderer;
use InvoiceService\Jobs\InvoiceJob;
use InvoiceService\Revisions\InvoiceRevisionRepository;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Domain\InvoiceSourceValidator;

final readonly class InvoiceGenerationService implements InvoiceJobProcessor
{
    public function __construct(
        private AccountRepository $accounts,
        private InvoiceSourceReader $sources,
        private InvoiceSourceValidator $validator,
        private InvoiceRevisionRepository $revisions,
        private InvoiceDocumentRenderer $renderer,
        private InvoiceOfferProfile $profile,
        private InvoicePublisher $publisher,
    ) {
    }

    public function generate(InvoiceJob $job): InvoiceGenerationResult
    {
        $account = $this->accounts->findById($job->accountId);
        if ($account === null || $account->connectionState !== 'connected') {
            throw new \RuntimeException('amoCRM account is not connected.');
        }
        try {
            $source = $this->sources->read($account, $job->leadId);
        } catch (InvoiceSourceReadException $exception) {
            $reasons = [$exception->getMessage()];
            if ($this->revisions->markValidationBlocked($account->id, $job->leadId, $reasons)) {
                $this->publisher->noteValidationBlocked($this->connectedAccount($job->accountId), $job->leadId, $reasons);
            }

            return new InvoiceGenerationResult('validation_blocked');
        }
        $eligibility = $this->validator->validate($source);
        if (!$eligibility->eligible) {
            if ($this->revisions->markValidationBlocked($account->id, $job->leadId, $eligibility->reasons)) {
                $this->publisher->noteValidationBlocked($this->connectedAccount($job->accountId), $job->leadId, $eligibility->reasons);
            }

            return new InvoiceGenerationResult('validation_blocked');
        }
        $snapshot = InvoiceSnapshot::fromSource($source, $this->validator);
        $reservation = $this->revisions->reserve($account->id, $snapshot);
        $revision = $reservation->revision;
        if (!$reservation->created && $revision->status === 'completed') {
            return new InvoiceGenerationResult('unchanged', $revision->invoiceNumber);
        }
        if (in_array($revision->status, ['uploading', 'attaching', 'noting'], true)) {
            $revision = $this->revisions->markManualReconciliation($revision);

            return new InvoiceGenerationResult($revision->status, $revision->invoiceNumber);
        }
        if ($revision->status === 'manual_reconciliation_required') {
            return new InvoiceGenerationResult($revision->status, $revision->invoiceNumber);
        }
        if ($revision->status === 'reserved') {
            $rendered = $this->renderer->render(new InvoiceOfferDocumentData($snapshot, \InvoiceService\Domain\InvoiceNumber::from($job->leadId, $revision->sequenceValue), new DateTimeImmutable(), $this->profile), $revision);
            $revision = $this->revisions->markRendered($revision, $rendered->docxPath, $rendered->pdfPath, $rendered->pdfSha256);
        }
        if ($revision->status === 'rendered') {
            if ($revision->pdfPath === null) {
                throw new \RuntimeException('Rendered invoice does not have a PDF path.');
            }
            $revision = $this->revisions->beginUpload($revision);
            $revision = $this->revisions->markUploaded($revision, $this->publisher->upload($this->connectedAccount($job->accountId), $revision, $revision->pdfPath));
        }
        if ($revision->status === 'uploaded') {
            $revision = $this->revisions->beginAttachment($revision);
            $this->publisher->attach($this->connectedAccount($job->accountId), $revision);
            $revision = $this->revisions->markAttached($revision);
        }
        if ($revision->status === 'attached') {
            $revision = $this->revisions->beginCurrentNote($revision);
            $this->publisher->noteCurrentInvoice($this->connectedAccount($job->accountId), $revision);
            $revision = $this->revisions->markCurrent($revision);
        }

        return new InvoiceGenerationResult('generated', $revision->invoiceNumber);
    }

    private function connectedAccount(int $accountId): \InvoiceService\AmoCRM\AccountRecord
    {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->connectionState !== 'connected') {
            throw new \RuntimeException('amoCRM account is not connected.');
        }

        return $account;
    }
}
