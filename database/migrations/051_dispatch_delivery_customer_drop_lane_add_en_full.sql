-- 派送客户：去掉 lane_or_house_no、address_main；新增 addr_en_full；完整泰/英文地址由 7 段结构化字段整合。
-- 派送单停靠点：新增 addr_th_full / addr_en_full，去掉 lane / address_main。
-- 依赖：048（结构化地址）、021 核心表。可重复执行。

SET @db := DATABASE();

-- ---------- dispatch_delivery_customers ----------
SET @c_en := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'addr_en_full'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_customers ADD COLUMN addr_en_full VARCHAR(1000) NOT NULL DEFAULT '''' COMMENT ''完整英文地址（由结构化地址栏整合）'' AFTER addr_th_full'
    )
);
PREPARE c_en FROM @c_en; EXECUTE c_en; DEALLOCATE PREPARE c_en;

SET @has_lane_dc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'lane_or_house_no'
);

SET @m1 := IF(
    @has_lane_dc > 0,
    'UPDATE dispatch_delivery_customers SET
        addr_house_no = IF(CHAR_LENGTH(TRIM(COALESCE(addr_house_no, ''''))) = 0 AND CHAR_LENGTH(TRIM(COALESCE(lane_or_house_no, ''''))) > 0, TRIM(lane_or_house_no), addr_house_no),
        addr_road_soi = IF(CHAR_LENGTH(TRIM(COALESCE(addr_road_soi, ''''))) = 0 AND CHAR_LENGTH(TRIM(COALESCE(address_main, ''''))) > 0, TRIM(address_main), addr_road_soi)',
    'SELECT 1'
);
PREPARE m1 FROM @m1; EXECUTE m1; DEALLOCATE PREPARE m1;

SET @m2 := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'addr_house_no'
    ),
    'UPDATE dispatch_delivery_customers SET
        addr_th_full = TRIM(CONCAT_WS(CHAR(32),
            NULLIF(TRIM(addr_house_no), ''''),
            NULLIF(TRIM(addr_road_soi), ''''),
            NULLIF(TRIM(addr_moo_village), ''''),
            NULLIF(TRIM(addr_tambon), ''''),
            NULLIF(TRIM(addr_amphoe), ''''),
            NULLIF(TRIM(CONCAT(TRIM(addr_province), IF(TRIM(addr_province) <> '''' AND TRIM(addr_zipcode) <> '''', CHAR(32), ''''), TRIM(addr_zipcode))), '''')
        )),
        addr_en_full = TRIM(CONCAT_WS(CHAR(44, 32),
            NULLIF(TRIM(addr_house_no), ''''),
            NULLIF(TRIM(addr_road_soi), ''''),
            NULLIF(TRIM(addr_moo_village), ''''),
            NULLIF(TRIM(addr_tambon), ''''),
            NULLIF(TRIM(addr_amphoe), ''''),
            NULLIF(TRIM(CONCAT(TRIM(addr_province), IF(TRIM(addr_province) <> '''' AND TRIM(addr_zipcode) <> '''', CHAR(32), ''''), TRIM(addr_zipcode))), '''')
        ))',
    'SELECT 1'
);
PREPARE m2 FROM @m2; EXECUTE m2; DEALLOCATE PREPARE m2;

SET @d_lane := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'lane_or_house_no'
        ),
        'ALTER TABLE dispatch_delivery_customers DROP COLUMN lane_or_house_no',
        'SELECT 1'
    )
);
PREPARE d_lane FROM @d_lane; EXECUTE d_lane; DEALLOCATE PREPARE d_lane;

SET @d_am := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_customers' AND COLUMN_NAME = 'address_main'
        ),
        'ALTER TABLE dispatch_delivery_customers DROP COLUMN address_main',
        'SELECT 1'
    )
);
PREPARE d_am FROM @d_am; EXECUTE d_am; DEALLOCATE PREPARE d_am;

-- ---------- dispatch_delivery_doc_stops ----------
SET @has_stops := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_doc_stops'
);

SET @st_th := (
    SELECT IF(
        @has_stops = 0,
        'SELECT 1',
        IF(
            EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_doc_stops' AND COLUMN_NAME = 'addr_th_full'),
            'SELECT 1',
            'ALTER TABLE dispatch_delivery_doc_stops ADD COLUMN addr_th_full VARCHAR(1000) NOT NULL DEFAULT '''' COMMENT ''完整泰文地址'' AFTER community_name_th'
        )
    )
);
PREPARE st_th FROM @st_th; EXECUTE st_th; DEALLOCATE PREPARE st_th;

SET @st_en := (
    SELECT IF(
        @has_stops = 0,
        'SELECT 1',
        IF(
            EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_doc_stops' AND COLUMN_NAME = 'addr_en_full'),
            'SELECT 1',
            'ALTER TABLE dispatch_delivery_doc_stops ADD COLUMN addr_en_full VARCHAR(1000) NOT NULL DEFAULT '''' COMMENT ''完整英文地址'' AFTER addr_th_full'
        )
    )
);
PREPARE st_en FROM @st_en; EXECUTE st_en; DEALLOCATE PREPARE st_en;

SET @has_lane_st := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_doc_stops' AND COLUMN_NAME = 'lane_or_house_no'
);
SET @st_mig := IF(
    @has_stops = 0 OR @has_lane_st = 0,
    'SELECT 1',
    'UPDATE dispatch_delivery_doc_stops SET
        addr_th_full = TRIM(CONCAT_WS(CHAR(32), NULLIF(TRIM(lane_or_house_no), ''''), NULLIF(TRIM(address_main), ''''))),
        addr_en_full = TRIM(CONCAT_WS(CHAR(44, 32), NULLIF(TRIM(lane_or_house_no), ''''), NULLIF(TRIM(address_main), '''')))'
);
PREPARE st_mig FROM @st_mig; EXECUTE st_mig; DEALLOCATE PREPARE st_mig;

SET @st_drop_lane := (
    SELECT IF(
        @has_stops = 0,
        'SELECT 1',
        IF(
            EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_doc_stops' AND COLUMN_NAME = 'lane_or_house_no'),
            'ALTER TABLE dispatch_delivery_doc_stops DROP COLUMN lane_or_house_no',
            'SELECT 1'
        )
    )
);
PREPARE stdl FROM @st_drop_lane; EXECUTE stdl; DEALLOCATE PREPARE stdl;

SET @st_drop_am := (
    SELECT IF(
        @has_stops = 0,
        'SELECT 1',
        IF(
            EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_delivery_doc_stops' AND COLUMN_NAME = 'address_main'),
            'ALTER TABLE dispatch_delivery_doc_stops DROP COLUMN address_main',
            'SELECT 1'
        )
    )
);
PREPARE stda FROM @st_drop_am; EXECUTE stda; DEALLOCATE PREPARE stda;
