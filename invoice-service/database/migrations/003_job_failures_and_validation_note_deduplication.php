<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE deal_invoice_states ADD COLUMN IF NOT EXISTS validation_hash char(64)',
        'ALTER TABLE invoice_jobs ADD COLUMN IF NOT EXISTS failure_reason text',
    ],
];
