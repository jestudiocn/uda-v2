-- 派送客户：泰国地址结构字段 + 完整泰文地址 + 定位状态
-- 可重复执行

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'addr_house_no'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers
            ADD COLUMN addr_house_no VARCHAR(120) NOT NULL DEFAULT '''' COMMENT ''门牌号/House Number'' AFTER phone,
            ADD COLUMN addr_road_soi VARCHAR(160) NOT NULL DEFAULT '''' COMMENT ''路（巷）/Road(Soi)'' AFTER addr_house_no,
            ADD COLUMN addr_moo_village VARCHAR(160) NOT NULL DEFAULT '''' COMMENT ''村/Moo(Village)'' AFTER addr_road_soi,
            ADD COLUMN addr_tambon VARCHAR(160) NOT NULL DEFAULT '''' COMMENT ''镇（街道）（乡）/Tambon'' AFTER addr_moo_village,
            ADD COLUMN addr_amphoe VARCHAR(160) NOT NULL DEFAULT '''' COMMENT ''县（区）/Amphoe'' AFTER addr_tambon,
            ADD COLUMN addr_province VARCHAR(160) NOT NULL DEFAULT '''' COMMENT ''府/Province'' AFTER addr_amphoe,
            ADD COLUMN addr_zipcode VARCHAR(20) NOT NULL DEFAULT '''' COMMENT ''Zipcode'' AFTER addr_province,
            ADD COLUMN addr_th_full VARCHAR(1000) NOT NULL DEFAULT '''' COMMENT ''完整泰文地址'' AFTER addr_zipcode,
            ADD COLUMN geo_status VARCHAR(40) NOT NULL DEFAULT '''' COMMENT ''定位状态'' AFTER longitude'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx1 := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND INDEX_NAME = 'idx_dispatch_delivery_amphoe'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD KEY idx_dispatch_delivery_amphoe (addr_amphoe)'
    )
);
PREPARE stmt_idx1 FROM @sql_idx1; EXECUTE stmt_idx1; DEALLOCATE PREPARE stmt_idx1;

SET @sql_idx2 := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND INDEX_NAME = 'idx_dispatch_delivery_geo_status'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD KEY idx_dispatch_delivery_geo_status (geo_status)'
    )
);
PREPARE stmt_idx2 FROM @sql_idx2; EXECUTE stmt_idx2; DEALLOCATE PREPARE stmt_idx2;
