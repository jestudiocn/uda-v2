-- 绑带列表：与扫描写入的 delivered_at 解耦，用单独时间标记「绑带完成」
SET @db = DATABASE();
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @db AND table_name = 'dispatch_waybills' AND column_name = 'binding_completed_at'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN binding_completed_at DATETIME NULL DEFAULT NULL COMMENT ''绑带列表完成'' AFTER delivered_at'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
