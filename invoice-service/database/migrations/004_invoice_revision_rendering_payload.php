<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE invoice_revisions ADD COLUMN IF NOT EXISTS snapshot jsonb',
        'ALTER TABLE invoice_revisions ADD COLUMN IF NOT EXISTS docx_path text',
        'ALTER TABLE invoice_revisions ADD COLUMN IF NOT EXISTS pdf_path text',
        'ALTER TABLE invoice_revisions ADD COLUMN IF NOT EXISTS pdf_sha256 char(64)',
    ],
];
