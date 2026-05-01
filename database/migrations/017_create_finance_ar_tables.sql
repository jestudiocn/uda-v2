CREATE TABLE IF NOT EXISTS ar_customer_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_id INT UNSIGNED NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'THB',
    tax_mode VARCHAR(30) NOT NULL DEFAULT 'excluded',
    billing_cycle VARCHAR(30) NOT NULL DEFAULT 'monthly',
    formula_config_json JSON DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ar_customer_profiles_party (party_id),
    KEY idx_ar_customer_profiles_status (status),
    CONSTRAINT fk_ar_customer_profiles_party FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_customer_profiles_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ar_charge_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_id INT UNSIGNED NOT NULL,
    billing_date DATE NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
    unit_name VARCHAR(40) NOT NULL DEFAULT '',
    formula_inputs_json JSON DEFAULT NULL,
    calculated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    source_ref VARCHAR(120) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ar_charge_items_party_date (party_id, billing_date),
    KEY idx_ar_charge_items_status (status),
    CONSTRAINT fk_ar_charge_items_party FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ar_charge_items_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ar_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) NOT NULL,
    party_id INT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    issue_date DATE NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    receivable_id INT UNSIGNED DEFAULT NULL,
    exported_at DATETIME DEFAULT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ar_invoices_invoice_no (invoice_no),
    KEY idx_ar_invoices_party_status (party_id, status),
    KEY idx_ar_invoices_receivable_id (receivable_id),
    CONSTRAINT fk_ar_invoices_party FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ar_invoices_receivable FOREIGN KEY (receivable_id) REFERENCES receivables(id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_invoices_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ar_invoice_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    charge_item_id INT UNSIGNED NOT NULL,
    line_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_detail_json JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ar_invoice_lines_charge (invoice_id, charge_item_id),
    KEY idx_ar_invoice_lines_invoice (invoice_id),
    KEY idx_ar_invoice_lines_charge_item (charge_item_id),
    CONSTRAINT fk_ar_invoice_lines_invoice FOREIGN KEY (invoice_id) REFERENCES ar_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_invoice_lines_charge_item FOREIGN KEY (charge_item_id) REFERENCES ar_charge_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ar_receivable_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED DEFAULT NULL,
    receivable_id INT UNSIGNED DEFAULT NULL,
    transaction_id INT UNSIGNED DEFAULT NULL,
    entry_type VARCHAR(20) NOT NULL DEFAULT 'debit',
    debit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_after DECIMAL(14,2) NOT NULL DEFAULT 0,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ar_receivable_ledger_party (party_id, id),
    KEY idx_ar_receivable_ledger_invoice (invoice_id),
    KEY idx_ar_receivable_ledger_receivable (receivable_id),
    KEY idx_ar_receivable_ledger_transaction (transaction_id),
    CONSTRAINT fk_ar_ledger_party FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ar_ledger_invoice FOREIGN KEY (invoice_id) REFERENCES ar_invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_ledger_receivable FOREIGN KEY (receivable_id) REFERENCES receivables(id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_ledger_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_ledger_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE receivables
    ADD COLUMN ar_invoice_id INT UNSIGNED DEFAULT NULL AFTER party_id,
    ADD KEY idx_receivables_ar_invoice_id (ar_invoice_id),
    ADD CONSTRAINT fk_receivables_ar_invoice FOREIGN KEY (ar_invoice_id) REFERENCES ar_invoices(id) ON DELETE SET NULL;
