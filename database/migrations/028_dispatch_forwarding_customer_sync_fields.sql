-- 派送客户补充收件人姓名；转发客户补充推送与标记字段
-- 兼容旧版本 MySQL（不使用 ADD COLUMN IF NOT EXISTS）

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'recipient_name'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD COLUMN recipient_name VARCHAR(120) NOT NULL DEFAULT '''' AFTER line_id'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'wechat_line'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN wechat_line VARCHAR(260) NOT NULL DEFAULT '''' AFTER customer_name'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'recipient_name'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN recipient_name VARCHAR(120) NOT NULL DEFAULT '''' AFTER wechat_line'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'sync_mark'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN sync_mark VARCHAR(16) NOT NULL DEFAULT '''' COMMENT ''new|modified|'' AFTER status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'source_signature'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN source_signature VARCHAR(80) NOT NULL DEFAULT '''' AFTER sync_mark'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'source_updated_at'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN source_updated_at DATETIME NULL AFTER source_signature'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'auto_pushed_once'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN auto_pushed_once TINYINT(1) NOT NULL DEFAULT 0 AFTER source_updated_at'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'manual_pushed_at'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_customers ADD COLUMN manual_pushed_at DATETIME NULL AFTER auto_pushed_once'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
