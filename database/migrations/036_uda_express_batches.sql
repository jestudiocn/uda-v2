-- UDA 快件 / 仓内操作：提单 + 集包（自动序号）+ 面单扫码（独立功能，不关联 express_uda）
-- 若曾执行旧版 036，先删除旧表再建新表；可重复执行

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS uda_express_batch_items;
DROP TABLE IF EXISTS uda_express_batches;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS uda_manifest_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_code VARCHAR(100) NOT NULL COMMENT '历史字段（兼容）',
    date_no VARCHAR(100) NOT NULL DEFAULT '' COMMENT '日期号',
    bill_no VARCHAR(100) NOT NULL DEFAULT '' COMMENT '提单号',
    status ENUM('open', 'completed') NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL DEFAULT NULL,
    KEY idx_uda_manifest_batches_code_status (batch_code, status),
    KEY idx_uda_manifest_date_no_status (date_no, status),
    KEY idx_uda_manifest_batches_created (created_at),
    CONSTRAINT fk_uda_manifest_batches_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uda_manifest_bundles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    bundle_seq INT UNSIGNED NOT NULL COMMENT '集包序号，从 1 起对应展示 001',
    weight_kg DECIMAL(12, 3) NOT NULL,
    length_cm DECIMAL(12, 2) NOT NULL,
    width_cm DECIMAL(12, 2) NOT NULL,
    height_cm DECIMAL(12, 2) NOT NULL,
    volume_m3 DECIMAL(16, 6) NOT NULL COMMENT '立方米，由长宽高(cm)换算',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uda_manifest_bundle_seq (batch_id, bundle_seq),
    KEY idx_uda_manifest_bundles_batch (batch_id),
    CONSTRAINT fk_uda_manifest_bundles_batch FOREIGN KEY (batch_id) REFERENCES uda_manifest_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uda_manifest_bundle_waybills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    bundle_id INT UNSIGNED NOT NULL,
    tracking_no VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_uda_manifest_waybill_batch_id (batch_id),
    UNIQUE KEY uk_uda_manifest_waybill_batch (batch_id, tracking_no),
    KEY idx_uda_manifest_waybill_bundle (bundle_id),
    CONSTRAINT fk_uda_manifest_waybill_batch FOREIGN KEY (batch_id) REFERENCES uda_manifest_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_uda_manifest_waybill_bundle FOREIGN KEY (bundle_id) REFERENCES uda_manifest_bundles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
