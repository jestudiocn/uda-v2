-- 转发合包：转发费用（必填业务字段，兼容旧 MySQL）

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_packages' AND COLUMN_NAME = 'forward_fee'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_packages ADD COLUMN forward_fee DECIMAL(12, 2) NOT NULL DEFAULT 0 COMMENT ''转发费用'' AFTER send_at'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
