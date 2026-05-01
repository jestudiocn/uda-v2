-- 泰国行政区主数据（府 / 县（区）/ 镇（乡））+ 派送客户外键引用
-- 数据来源：kongvut/thai-province-data（JSON 种子见 database/seeds/th_geo/，需运行 database/scripts/seed_thailand_geography.php）
-- 可重复执行

SET @db := DATABASE();

-- ---------- th_geo_provinces ----------
SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'th_geo_provinces'),
        'SELECT 1',
        'CREATE TABLE th_geo_provinces (
            id INT UNSIGNED NOT NULL PRIMARY KEY COMMENT ''数据源 province id（1-77）'',
            name_th VARCHAR(160) NOT NULL DEFAULT '''',
            name_en VARCHAR(160) NOT NULL DEFAULT '''',
            KEY idx_th_geo_province_name_en (name_en(32)),
            KEY idx_th_geo_province_name_th (name_th(32))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''泰国：府/Province'''
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- th_geo_districts ----------
SET @sql2 := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'th_geo_districts'),
        'SELECT 1',
        'CREATE TABLE th_geo_districts (
            id INT UNSIGNED NOT NULL PRIMARY KEY COMMENT ''数据源 district id'',
            province_id INT UNSIGNED NOT NULL,
            name_th VARCHAR(160) NOT NULL DEFAULT '''',
            name_en VARCHAR(160) NOT NULL DEFAULT '''',
            KEY idx_th_geo_district_province (province_id),
            KEY idx_th_geo_district_name_en (name_en(32)),
            CONSTRAINT fk_th_geo_district_province FOREIGN KEY (province_id) REFERENCES th_geo_provinces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''泰国：县/区（Amphoe / Khet）'''
    )
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ---------- th_geo_subdistricts ----------
SET @sql3 := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'th_geo_subdistricts'),
        'SELECT 1',
        'CREATE TABLE th_geo_subdistricts (
            id INT UNSIGNED NOT NULL PRIMARY KEY COMMENT ''数据源 sub_district id'',
            district_id INT UNSIGNED NOT NULL,
            zipcode CHAR(5) NOT NULL DEFAULT '''',
            name_th VARCHAR(160) NOT NULL DEFAULT '''',
            name_en VARCHAR(160) NOT NULL DEFAULT '''',
            KEY idx_th_geo_subdistrict_district (district_id),
            KEY idx_th_geo_subdistrict_zip (zipcode),
            CONSTRAINT fk_th_geo_subdistrict_district FOREIGN KEY (district_id) REFERENCES th_geo_districts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''泰国：镇/乡（Tambon）+ 邮编'''
    )
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- ---------- dispatch_delivery_customers：行政区外键 ----------
SET @c1 := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'th_geo_province_id'),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD COLUMN th_geo_province_id INT UNSIGNED NULL COMMENT ''泰国府ID'' AFTER addr_zipcode'
    )
);
PREPARE c1 FROM @c1; EXECUTE c1; DEALLOCATE PREPARE c1;

SET @c2 := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'th_geo_district_id'),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD COLUMN th_geo_district_id INT UNSIGNED NULL COMMENT ''泰国县/区ID'' AFTER th_geo_province_id'
    )
);
PREPARE c2 FROM @c2; EXECUTE c2; DEALLOCATE PREPARE c2;

SET @c3 := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'th_geo_subdistrict_id'),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD COLUMN th_geo_subdistrict_id INT UNSIGNED NULL COMMENT ''泰国镇/乡ID'' AFTER th_geo_district_id'
    )
);
PREPARE c3 FROM @c3; EXECUTE c3; DEALLOCATE PREPARE c3;

SET @k1 := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND INDEX_NAME = 'idx_dispatch_delivery_th_geo_sub'),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD KEY idx_dispatch_delivery_th_geo_sub (th_geo_subdistrict_id)'
    )
);
PREPARE k1 FROM @k1; EXECUTE k1; DEALLOCATE PREPARE k1;
