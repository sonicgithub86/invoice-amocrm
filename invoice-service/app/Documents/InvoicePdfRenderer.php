<?php

declare(strict_types=1);

namespace InvoiceService\Documents;

use InvoiceService\Revisions\InvoiceRevision;

final readonly class InvoicePdfRenderer implements InvoiceDocumentRenderer
{
    private const CONVERSION_TIMEOUT_SECONDS = 60;

    public function __construct(
        private InvoiceOfferDocumentBuilder $builder,
        private string $directory,
    ) {
    }

    public function render(InvoiceOfferDocumentData $data, InvoiceRevision $revision): RenderedInvoiceDocument
    {
        $jobDirectory = rtrim($this->directory, '/') . '/' . $revision->id;
        if (!is_dir($jobDirectory) && !mkdir($jobDirectory, 0775, true) && !is_dir($jobDirectory)) {
            throw new \RuntimeException('Invoice render directory could not be created.');
        }
        $docxPath = $jobDirectory . '/' . $revision->invoiceNumber . '.docx';
        $pdfPath = $jobDirectory . '/' . $revision->invoiceNumber . '.pdf';
        $this->builder->build($data, $docxPath);
        $profileDirectory = $jobDirectory . '/libreoffice-profile';
        if (!is_dir($profileDirectory) && !mkdir($profileDirectory, 0700, true) && !is_dir($profileDirectory)) {
            throw new \RuntimeException('LibreOffice profile directory could not be created.');
        }
        $stderrPath = $jobDirectory . '/libreoffice.stderr';
        $process = proc_open([
            'soffice', '--headless', '-env:UserInstallation=file://' . $profileDirectory,
            '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $jobDirectory, $docxPath,
        ], [1 => ['file', '/dev/null', 'a'], 2 => ['file', $stderrPath, 'a']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('LibreOffice PDF conversion could not start.');
        }
        $deadline = microtime(true) + self::CONVERSION_TIMEOUT_SECONDS;
        $status = proc_get_status($process);
        while ($status['running']) {
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                usleep(1_000_000);
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                proc_close($process);

                throw new \RuntimeException('LibreOffice PDF conversion timed out.');
            }
            usleep(100_000);
            $status = proc_get_status($process);
        }
        proc_close($process);
        if ($status['exitcode'] !== 0 || !is_file($pdfPath) || filesize($pdfPath) === 0) {
            throw new \RuntimeException('LibreOffice PDF conversion failed.');
        }
        if (is_file($stderrPath)) {
            unlink($stderrPath);
        }

        return new RenderedInvoiceDocument($docxPath, $pdfPath, hash_file('sha256', $pdfPath));
    }
}
