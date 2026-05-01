-- 转发操作：转发客户、转发合包、转发内件

CREATE TABLE IF NOT EXISTS dispatch_forward_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(60) NOT NULL COMMENT '转发客户代码，唯一',
    customer_name VARCHAR(160) NOT NULL COMMENT '客户名称',
    contact_name VARCHAR(120) NOT NULL DEFAULT '',
    phone VARCHAR(60) NOT NULL DEFAULT '',
    address VARCHAR(500) NOT NULL DEFAULT '',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_forward_customers_code (customer_code),
    KEY idx_dispatch_forward_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_forward_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_no VARCHAR(120) NOT NULL COMMENT '转发单号',
    send_at DATETIME NOT NULL COMMENT '发出时间',
    forward_customer_id INT UNSIGNED DEFAULT NULL,
    forward_customer_code VARCHAR(60) NOT NULL DEFAULT '',
    receiver_name VARCHAR(120) NOT NULL DEFAULT '',
    receiver_phone VARCHAR(60) NOT NULL DEFAULT '',
    receiver_address VARCHAR(500) NOT NULL DEFAULT '',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_forward_packages_no (package_no),
    KEY idx_dispatch_forward_packages_send_at (send_at),
    KEY idx_dispatch_forward_packages_customer (forward_customer_code),
    CONSTRAINT fk_dispatch_forward_package_customer FOREIGN KEY (forward_customer_id) REFERENCES dispatch_forward_customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_dispatch_forward_package_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_forward_package_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    forward_package_id INT UNSIGNED NOT NULL,
    waybill_id INT UNSIGNED NOT NULL,
    original_tracking_no VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_forward_item_waybill (waybill_id),
    KEY idx_dispatch_forward_item_package (forward_package_id),
    KEY idx_dispatch_forward_item_track (original_tracking_no),
    CONSTRAINT fk_dispatch_forward_item_package FOREIGN KEY (forward_package_id) REFERENCES dispatch_forward_packages(id) ON DELETE CASCADE,
    CONSTRAINT fk_dispatch_forward_item_waybill FOREIGN KEY (waybill_id) REFERENCES dispatch_waybills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
