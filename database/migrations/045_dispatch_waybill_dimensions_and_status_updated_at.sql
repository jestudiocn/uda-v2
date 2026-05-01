-- 派送订单：补充长/宽/高；将 delivered_at 业务语义改为“最后状态更新时间”
-- 可重复执行

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'length_cm'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN length_cm DECIMAL(12, 2) NOT NULL DEFAULT 0 AFTER weight_kg'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'width_cm'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN width_cm DECIMAL(12, 2) NOT NULL DEFAULT 0 AFTER length_cm'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'height_cm'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN height_cm DECIMAL(12, 2) NOT NULL DEFAULT 0 AFTER width_cm'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 将 delivered_at 的历史值尽量回填到 updated_at（若为空）
UPDATE dispatch_waybills
SET delivered_at = updated_at
WHERE delivered_at IS NULL;
