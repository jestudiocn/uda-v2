-- UDA快件 / 仓内操作 / 批次录入与批次列表
-- 日期号主表 + 面单明细表

CREATE TABLE IF NOT EXISTS uda_warehouse_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_no VARCHAR(100) NOT NULL COMMENT '日期号（唯一）',
    bill_no VARCHAR(100) NOT NULL DEFAULT '' COMMENT '提单号',
    uda_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UDA件数',
    jd_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'JD件数',
    total_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总件数',
    flight_date DATE NULL COMMENT '航班日期',
    customs_pickup_date DATE NULL COMMENT '清关完成提货日期',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uda_wh_batches_date_no (date_no),
    KEY idx_uda_wh_batches_created (created_at),
    CONSTRAINT fk_uda_wh_batches_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uda_warehouse_batch_waybills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    date_no VARCHAR(100) NOT NULL DEFAULT '',
    tracking_no VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uda_wh_waybills_tracking_no (tracking_no),
    UNIQUE KEY uk_uda_wh_waybills_batch_tracking (batch_id, tracking_no),
    KEY idx_uda_wh_waybills_batch (batch_id),
    KEY idx_uda_wh_waybills_date_no (date_no),
    CONSTRAINT fk_uda_wh_waybills_batch FOREIGN KEY (batch_id) REFERENCES uda_warehouse_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

