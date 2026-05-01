CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(120) NOT NULL,
    account_type VARCHAR(80) DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transaction_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(20) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trx_cat_type_status (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    client VARCHAR(160) DEFAULT NULL,
    category_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_transactions_type_time (type, created_at),
    KEY idx_transactions_category (category_id),
    KEY idx_transactions_account (account_id),
    KEY idx_transactions_created_by (created_by),
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES transaction_categories(id),
    CONSTRAINT fk_transactions_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_transactions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_name VARCHAR(160) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_pay_date DATE NOT NULL,
    remark VARCHAR(500) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    paid_transaction_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payables_status_date (status, expected_pay_date),
    KEY idx_payables_created_by (created_by),
    KEY idx_payables_paid_trx (paid_transaction_id),
    CONSTRAINT fk_payables_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payables_paid_transaction FOREIGN KEY (paid_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS receivables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(160) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_receive_date DATE NOT NULL,
    remark VARCHAR(500) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED DEFAULT NULL,
    received_at TIMESTAMP NULL DEFAULT NULL,
    received_transaction_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receivables_status_date (status, expected_receive_date),
    KEY idx_receivables_created_by (created_by),
    KEY idx_receivables_received_trx (received_transaction_id),
    CONSTRAINT fk_receivables_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_receivables_received_transaction FOREIGN KEY (received_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
