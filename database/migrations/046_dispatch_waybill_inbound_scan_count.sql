-- 到件扫描计数：用于按扫描次数与订单件数比较，判定“部分入库/已入库”
-- 可重复执行

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_waybills' AND COLUMN_NAME = 'inbound_scan_count'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN inbound_scan_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
