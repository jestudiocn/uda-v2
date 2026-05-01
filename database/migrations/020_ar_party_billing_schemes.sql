-- 客户计费方式（含固定单位/单价；首续重规则存 JSON）；费用行可选关联方案
CREATE TABLE IF NOT EXISTS ar_party_billing_schemes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_id INT UNSIGNED NOT NULL,
    scheme_label VARCHAR(120) NOT NULL,
    algorithm VARCHAR(40) NOT NULL DEFAULT 'qty_unit_price',
    unit_name VARCHAR(40) NOT NULL DEFAULT '',
    unit_price DECIMAL(14, 4) NOT NULL DEFAULT 0,
    base_fee DECIMAL(14, 2) NOT NULL DEFAULT 0,
    weight_config_json JSON DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ar_party_scheme_label (party_id, scheme_label),
    KEY idx_ar_party_scheme_party_status (party_id, status),
    CONSTRAINT fk_ar_party_billing_schemes_party FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ar_charge_items
    ADD COLUMN billing_scheme_id INT UNSIGNED NULL DEFAULT NULL AFTER pricing_mode,
    ADD KEY idx_ar_charge_items_billing_scheme (billing_scheme_id),
    ADD CONSTRAINT fk_ar_charge_items_billing_scheme FOREIGN KEY (billing_scheme_id) REFERENCES ar_party_billing_schemes(id) ON DELETE SET NULL;
