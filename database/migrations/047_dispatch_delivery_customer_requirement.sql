-- 派送客户：新增客户要求（备注，可断行）
-- 可重复执行

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'customer_requirements'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD COLUMN customer_requirements TEXT NULL COMMENT ''客户要求（备注，可断行）'' AFTER customer_state'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
