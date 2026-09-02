<?php

declare(strict_types=1);

use InvoiceService\Documents\InvoiceOfferDocumentBuilder;
use InvoiceService\Documents\InvoiceOfferDocumentData;
use InvoiceService\Documents\InvoiceOfferProfile;
use InvoiceService\Documents\InvoicePdfRenderer;
use InvoiceService\Domain\BuyerRequisites;
use InvoiceService\Domain\DealProduct;
use InvoiceService\Domain\InvoiceNumber;
use InvoiceService\Domain\InvoiceSnapshot;
use InvoiceService\Domain\InvoiceSource;
use InvoiceService\Domain\InvoiceSourceValidator;
use InvoiceService\Domain\Money;
use InvoiceService\Revisions\InvoiceRevision;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/invoice-render-smoke-' . bin2hex(random_bytes(6));
$profile = InvoiceOfferProfile::sonicIpV1();
$source = new InvoiceSource(
    1,
    999001,
    new BuyerRequisites(
        'ООО Тестовый покупатель',
        '7701000000',
        '770101001',
        '1027700000000',
        'г. Москва, тестовый адрес, д. 1',
        '40702810000000000001',
        'АО Тестовый банк',
        '30101810000000000001',
        '044525000',
    ),
    [new DealProduct('Лицензия amoCRM Тест', Money::fromDecimal('1990.00'), 2, true)],
    $profile->version,
);
$snapshot = InvoiceSnapshot::fromSource($source, new InvoiceSourceValidator());
$number = InvoiceNumber::from(999001, 1);
$revision = new InvoiceRevision(
    '00000000-0000-4000-8000-000000000001',
    1,
    999001,
    $snapshot->hash(),
    1,
    $number->value(),
    'rendering',
);

try {
    $document = (new InvoicePdfRenderer(new InvoiceOfferDocumentBuilder(), $root))->render(
        new InvoiceOfferDocumentData($snapshot, $number, new DateTimeImmutable('2026-09-02T00:00:00+00:00'), $profile),
        $revision,
    );

    $process = proc_open(
        ['pdfinfo', $document->pdfPath],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true],
    );
    if (!is_resource($process)) {
        throw new RuntimeException('pdfinfo could not start.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || preg_match('/^Pages:\s+2\s*$/m', $stdout) !== 1) {
        throw new RuntimeException('Rendered PDF is not a readable two-page document: ' . trim($stderr));
    }

    fwrite(STDOUT, sprintf("Smoke render: PASS pages=2 sha256=%s\n", $document->pdfSha256));
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
