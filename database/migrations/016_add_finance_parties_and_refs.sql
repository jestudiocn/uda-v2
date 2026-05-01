CREATE TABLE IF NOT EXISTS finance_parties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_name VARCHAR(180) NOT NULL,
    party_kind VARCHAR(20) NOT NULL DEFAULT 'both',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_finance_parties_kind_status (party_kind, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE transactions
    ADD COLUMN party_id INT UNSIGNED DEFAULT NULL AFTER client;

ALTER TABLE payables
    ADD COLUMN party_id INT UNSIGNED DEFAULT NULL AFTER vendor_name;

ALTER TABLE receivables
    ADD COLUMN party_id INT UNSIGNED DEFAULT NULL AFTER client_name;

ALTER TABLE transactions
    ADD CONSTRAINT fk_transactions_party
    FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE SET NULL;

ALTER TABLE payables
    ADD CONSTRAINT fk_payables_party
    FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE SET NULL;

ALTER TABLE receivables
    ADD CONSTRAINT fk_receivables_party
    FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE SET NULL;
