<?php

declare(strict_types=1);

return [
    'up' => [
        <<<'SQL'
ALTER TABLE invoice_revisions
DROP CONSTRAINT invoice_revisions_account_id_lead_id_snapshot_hash_status_key
SQL,
        <<<'SQL'
CREATE UNIQUE INDEX invoice_revisions_one_unfinished_snapshot
ON invoice_revisions (account_id, lead_id, snapshot_hash)
WHERE status IN ('reserved', 'rendered', 'uploading', 'uploaded', 'attaching', 'attached', 'noting', 'manual_reconciliation_required')
SQL,
    ],
];
