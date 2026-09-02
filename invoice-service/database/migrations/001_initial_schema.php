<?php

declare(strict_types=1);

return [
    'up' => [
        <<<'SQL'
CREATE TABLE amocrm_accounts (
    id bigserial PRIMARY KEY,
    account_id bigint NOT NULL UNIQUE,
    base_domain varchar(255) NOT NULL,
    access_token_ciphertext text NOT NULL,
    refresh_token_ciphertext text NOT NULL,
    credential_key_version smallint NOT NULL DEFAULT 1,
    token_expires_at timestamptz NOT NULL,
    connection_state varchar(64) NOT NULL DEFAULT 'connected',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
)
SQL,
        <<<'SQL'
CREATE TABLE oauth_states (
    id uuid PRIMARY KEY,
    state_hash char(64) NOT NULL UNIQUE,
    redirect_after varchar(255),
    expires_at timestamptz NOT NULL,
    consumed_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
)
SQL,
        <<<'SQL'
CREATE TABLE webhook_endpoints (
    id uuid PRIMARY KEY,
    account_id bigint NOT NULL REFERENCES amocrm_accounts(id),
    trigger_kind varchar(32) NOT NULL CHECK (trigger_kind IN ('automatic', 'rerun')),
    secret_hash char(64) NOT NULL,
    enabled boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(account_id, trigger_kind)
)
SQL,
        <<<'SQL'
CREATE TABLE deal_invoice_states (
    id bigserial PRIMARY KEY,
    account_id bigint NOT NULL REFERENCES amocrm_accounts(id),
    lead_id bigint NOT NULL,
    current_revision_id uuid,
    state varchar(32) NOT NULL DEFAULT 'no_invoice' CHECK (state IN ('no_invoice', 'rendering', 'current', 'validation_blocked', 'manual_reconciliation_required')),
    validation_hash char(64),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(account_id, lead_id)
)
SQL,
        <<<'SQL'
CREATE TABLE invoice_sequences (
    account_id bigint PRIMARY KEY REFERENCES amocrm_accounts(id),
    next_value bigint NOT NULL DEFAULT 1 CHECK (next_value > 0)
)
SQL,
        <<<'SQL'
CREATE TABLE invoice_jobs (
    id uuid PRIMARY KEY,
    webhook_endpoint_id uuid NOT NULL REFERENCES webhook_endpoints(id),
    account_id bigint NOT NULL REFERENCES amocrm_accounts(id),
    lead_id bigint NOT NULL,
    trigger_kind varchar(32) NOT NULL CHECK (trigger_kind IN ('automatic', 'rerun')),
    payload_hash char(64) NOT NULL,
    status varchar(32) NOT NULL DEFAULT 'pending',
    lease_owner varchar(255),
    locked_until timestamptz,
    attempts integer NOT NULL DEFAULT 0,
    retry_at timestamptz,
    failure_reason text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
)
SQL,
        <<<'SQL'
CREATE UNIQUE INDEX invoice_jobs_one_active_per_lead
ON invoice_jobs (account_id, lead_id)
WHERE status IN ('pending', 'leased', 'retryable')
SQL,
        <<<'SQL'
CREATE TABLE invoice_revisions (
    id uuid PRIMARY KEY,
    account_id bigint NOT NULL REFERENCES amocrm_accounts(id),
    lead_id bigint NOT NULL,
    snapshot_hash char(64) NOT NULL,
    snapshot jsonb NOT NULL,
    sequence_value bigint NOT NULL CHECK (sequence_value > 0),
    invoice_number varchar(128) NOT NULL,
    status varchar(64) NOT NULL DEFAULT 'reserved' CHECK (status IN ('reserved', 'rendered', 'uploading', 'uploaded', 'attaching', 'attached', 'noting', 'completed', 'manual_reconciliation_required')),
    file_uuid uuid,
    docx_path text,
    pdf_path text,
    pdf_sha256 char(64),
    attached_at timestamptz,
    note_marker varchar(255),
    noted_at timestamptz,
    rendered_at timestamptz,
    completed_at timestamptz,
    failure_reason text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(account_id, sequence_value),
    UNIQUE(account_id, lead_id, snapshot_hash, status)
)
SQL,
        <<<'SQL'
ALTER TABLE deal_invoice_states
ADD CONSTRAINT deal_invoice_states_current_revision_fk
FOREIGN KEY (current_revision_id) REFERENCES invoice_revisions(id)
SQL,
    ],
];
