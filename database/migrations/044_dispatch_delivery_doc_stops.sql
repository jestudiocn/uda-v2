-- 派送单客户停靠点（支持草稿排序/优化排序/最终发布）
SET @db = DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = @db AND table_name = 'dispatch_delivery_doc_stops'
        ),
        'SELECT 1',
        'CREATE TABLE dispatch_delivery_doc_stops (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            delivery_doc_no VARCHAR(64) NOT NULL,
            customer_code VARCHAR(120) NOT NULL,
            wx_or_line VARCHAR(160) NOT NULL DEFAULT '''',
            route_primary VARCHAR(64) NOT NULL DEFAULT '''',
            route_secondary VARCHAR(64) NOT NULL DEFAULT '''',
            community_name_th VARCHAR(255) NOT NULL DEFAULT '''',
            lane_or_house_no VARCHAR(255) NOT NULL DEFAULT '''',
            address_main VARCHAR(500) NOT NULL DEFAULT '''',
            latitude DECIMAL(10,7) NULL DEFAULT NULL,
            longitude DECIMAL(10,7) NULL DEFAULT NULL,
            piece_count INT NOT NULL DEFAULT 0,
            stop_order INT NOT NULL DEFAULT 0,
            segment_index INT NOT NULL DEFAULT 0,
            is_final TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_dispatch_doc_stop_doc_customer (delivery_doc_no, customer_code),
            KEY idx_dispatch_doc_stop_doc_order (delivery_doc_no, stop_order),
            KEY idx_dispatch_doc_stop_doc_segment (delivery_doc_no, segment_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
