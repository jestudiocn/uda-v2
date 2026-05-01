-- UDA快件收发 + 问题订单 相关表
-- 可重复执行：表/字段存在则跳过

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS express_uda (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receive_time DATETIME NOT NULL,
    tracking_no VARCHAR(120) NOT NULL,
    receiver_name VARCHAR(160) NOT NULL DEFAULT '',
    remark VARCHAR(500) NOT NULL DEFAULT '',
    is_forwarded TINYINT(1) NOT NULL DEFAULT 0,
    forward_time DATETIME NULL,
    forward_tracking_no VARCHAR(120) NOT NULL DEFAULT '',
    forward_receiver VARCHAR(160) NOT NULL DEFAULT '',
    forward_fee DECIMAL(12,2) NULL,
    forward_remark VARCHAR(500) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_express_uda_tracking_no (tracking_no),
    KEY idx_express_uda_receive_time (receive_time),
    KEY idx_express_uda_forwarded (is_forwarded),
    CONSTRAINT fk_express_uda_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='express_uda' AND COLUMN_NAME='forward_receiver'),
        'SELECT 1',
        'ALTER TABLE express_uda ADD COLUMN forward_receiver VARCHAR(160) NOT NULL DEFAULT '''' AFTER forward_tracking_no'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='express_uda' AND COLUMN_NAME='forward_remark'),
        'SELECT 1',
        'ALTER TABLE express_uda ADD COLUMN forward_remark VARCHAR(500) NOT NULL DEFAULT '''' AFTER forward_fee'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS problem_order_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(160) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_problem_order_locations_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS problem_order_reason_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    reason_name VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_problem_reason_location (location_id, is_active, sort_order),
    CONSTRAINT fk_problem_reason_location FOREIGN KEY (location_id) REFERENCES problem_order_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS problem_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_no VARCHAR(120) NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    problem_reason VARCHAR(200) NOT NULL DEFAULT '',
    handle_method VARCHAR(255) NOT NULL DEFAULT '',
    is_processed TINYINT(1) NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    remark VARCHAR(1000) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_problem_orders_tracking (tracking_no),
    KEY idx_problem_orders_processed (is_processed, created_at),
    KEY idx_problem_orders_location (location_id),
    CONSTRAINT fk_problem_orders_location FOREIGN KEY (location_id) REFERENCES problem_order_locations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_problem_orders_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化最小可用字典（仅在空表时）
INSERT INTO problem_order_locations (location_name, sort_order, is_active)
SELECT '默认地点', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM problem_order_locations LIMIT 1);

INSERT INTO problem_order_reason_options (location_id, reason_name, sort_order, is_active)
SELECT l.id, '默认原因', 1, 1
FROM problem_order_locations l
WHERE l.location_name = '默认地点'
  AND NOT EXISTS (SELECT 1 FROM problem_order_reason_options LIMIT 1)
LIMIT 1;
