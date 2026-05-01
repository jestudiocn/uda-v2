-- 订单（dispatch_waybills）扩展字段 + 派送照片子表（多图，订单详情展示；上传逻辑后续接）
-- 可重复执行：已存在的列会跳过（避免 Duplicate column name）

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'order_status'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN order_status VARCHAR(32) NOT NULL DEFAULT ''待入库'' COMMENT ''待入库/已入库/待自取/已出库/已自取/已转发/已派送/问题件'' AFTER match_status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'import_date'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN import_date DATE NULL COMMENT ''导入日期'' AFTER order_status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'scanned_at'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN scanned_at DATETIME NULL COMMENT ''扫描时间'' AFTER import_date'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'delivered_at'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN delivered_at DATETIME NULL COMMENT ''派送时间'' AFTER scanned_at'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE dispatch_waybills
SET import_date = DATE(created_at)
WHERE import_date IS NULL;

CREATE TABLE IF NOT EXISTS dispatch_waybill_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waybill_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(512) NOT NULL COMMENT '相对存储根路径，如 dispatch/2026/04/xxx.jpg',
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    KEY idx_dispatch_photo_waybill (waybill_id),
    CONSTRAINT fk_dispatch_photo_waybill FOREIGN KEY (waybill_id) REFERENCES dispatch_waybills(id) ON DELETE CASCADE,
    CONSTRAINT fk_dispatch_photo_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
