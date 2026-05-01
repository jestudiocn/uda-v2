-- UDA快件收发 / 转发合包数据表
CREATE TABLE IF NOT EXISTS uda_express_forward_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    express_id INT UNSIGNED NOT NULL,
    source_tracking_no VARCHAR(120) NOT NULL DEFAULT '',
    forward_tracking_no VARCHAR(120) NOT NULL DEFAULT '',
    forward_receiver VARCHAR(160) NOT NULL DEFAULT '',
    forward_fee DECIMAL(12,2) NULL,
    forward_remark VARCHAR(500) NOT NULL DEFAULT '',
    forwarded_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uda_express_forward_packages_express (express_id),
    KEY idx_uda_express_forward_packages_forwarded_at (forwarded_at),
    CONSTRAINT fk_uda_express_forward_packages_express
        FOREIGN KEY (express_id) REFERENCES express_uda(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
