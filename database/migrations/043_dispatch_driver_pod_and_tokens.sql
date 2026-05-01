-- 司机端派送段 token（免登录）与签收照片（每客户一单内唯一）
SET @db = DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = @db AND table_name = 'dispatch_driver_run_tokens'
        ),
        'SELECT 1',
        'CREATE TABLE dispatch_driver_run_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            delivery_doc_no VARCHAR(64) NOT NULL,
            segment_index INT NOT NULL DEFAULT 0,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by INT UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_dispatch_driver_run_tokens_token (token),
            KEY idx_dispatch_driver_run_tokens_doc (delivery_doc_no, segment_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = @db AND table_name = 'dispatch_delivery_pod'
        ),
        'SELECT 1',
        'CREATE TABLE dispatch_delivery_pod (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            delivery_doc_no VARCHAR(64) NOT NULL,
            customer_code VARCHAR(120) NOT NULL,
            photo_1 VARCHAR(255) NOT NULL,
            photo_2 VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_dispatch_delivery_pod_doc_customer (delivery_doc_no, customer_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
