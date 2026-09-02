<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

use AmoCRM\Collections\FileLinksCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\FileLinkModel;
use AmoCRM\Models\Files\FileUploadModel;
use AmoCRM\Models\NoteType\CommonNote;
use InvoiceService\Revisions\InvoiceRevision;

final readonly class OfficialInvoicePublisher implements InvoicePublisher
{
    public function __construct(private AmoClientFactory $clients, private AmoRequestPacer $pacer)
    {
    }

    public function upload(AccountRecord $account, InvoiceRevision $revision, string $pdfPath): string
    {
        $client = $this->clients->create($account);
        $this->pacer->beforeRequest();
        $file = $client->files()->uploadOne((new FileUploadModel())
            ->setName($revision->invoiceNumber . '.pdf')
            ->setLocalPath($pdfPath));
        $fileUuid = $file->getUuid();
        if ($fileUuid === null) {
            throw new \RuntimeException('amoCRM did not return a UUID for the uploaded invoice PDF.');
        }

        return $fileUuid;
    }

    public function attach(AccountRecord $account, InvoiceRevision $revision): void
    {
        if ($revision->fileUuid === null) {
            throw new \LogicException('An uploaded invoice must have a file UUID before attachment.');
        }
        $client = $this->clients->create($account);
        $this->pacer->beforeRequest();
        $client->entityFiles(EntityTypesInterface::LEADS, $revision->leadId)->add(
            (new FileLinksCollection())->add((new FileLinkModel())->setFileUuid($revision->fileUuid)),
        );
    }

    public function noteValidationBlocked(AccountRecord $account, int $leadId, array $reasons): void
    {
        $this->note($account, $leadId, "Счёт по лицензиям amoCRM не сформирован.\n" . implode("\n", $reasons));
    }

    public function noteCurrentInvoice(AccountRecord $account, InvoiceRevision $revision): void
    {
        $this->note($account, $revision->leadId, sprintf('Актуальный счёт по лицензиям amoCRM: № %s. PDF прикреплён во вкладке Media. Ревизия: %s.', $revision->invoiceNumber, $revision->id));
    }

    private function note(AccountRecord $account, int $leadId, string $text): void
    {
        $note = new CommonNote();
        $note->setEntityId($leadId);
        $note->setText($text);
        $this->pacer->beforeRequest();
        $this->clients->create($account)->notes(EntityTypesInterface::LEADS)->addOne($note);
    }
}
