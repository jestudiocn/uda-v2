-- 派送业务 / 派送操作 / 生成派送单：为面单增加派送单字段

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dispatch_waybills' AND COLUMN_NAME='delivery_doc_no'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN delivery_doc_no VARCHAR(64) NOT NULL DEFAULT '''' AFTER inbound_batch'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dispatch_waybills' AND COLUMN_NAME='dispatch_line'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN dispatch_line VARCHAR(8) NOT NULL DEFAULT '''' AFTER delivery_doc_no'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dispatch_waybills' AND COLUMN_NAME='planned_delivery_date'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN planned_delivery_date DATE NULL AFTER dispatch_line'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema=@db AND table_name='dispatch_waybills' AND index_name='idx_dispatch_waybills_delivery_doc_no'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD KEY idx_dispatch_waybills_delivery_doc_no (delivery_doc_no)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

