-- 订单级：回滚后禁止自动推送到可转发列表（仅影响该订单）

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dispatch_waybills' AND COLUMN_NAME='auto_forward_opt_out'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN auto_forward_opt_out TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1=该订单不再自动推送待转发'' AFTER planned_delivery_date'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema=@db AND table_name='dispatch_waybills' AND index_name='idx_dispatch_waybills_auto_forward_opt_out'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD KEY idx_dispatch_waybills_auto_forward_opt_out (auto_forward_opt_out)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

