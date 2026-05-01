-- UDA 快件收发：常用收件人 + 转发合包主表/明细
SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS uda_saved_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_name VARCHAR(160) NOT NULL DEFAULT '',
    phone VARCHAR(80) NOT NULL DEFAULT '',
    address VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_uda_saved_recipients_sort (sort_order, id),
    CONSTRAINT fk_uda_saved_recipients_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uda_forward_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_no VARCHAR(120) NOT NULL,
    send_at DATETIME NOT NULL,
    forward_fee DECIMAL(12,2) NOT NULL,
    saved_recipient_id INT UNSIGNED NULL,
    receiver_name VARCHAR(160) NOT NULL DEFAULT '',
    receiver_phone VARCHAR(80) NOT NULL DEFAULT '',
    receiver_address VARCHAR(1000) NOT NULL DEFAULT '',
    voucher_path VARCHAR(255) NOT NULL DEFAULT '',
    remark VARCHAR(500) NOT NULL DEFAULT '',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uda_forward_packages_no (package_no),
    KEY idx_uda_forward_packages_created (created_at),
    CONSTRAINT fk_uda_forward_packages_saved_recipient FOREIGN KEY (saved_recipient_id) REFERENCES uda_saved_recipients(id) ON DELETE SET NULL,
    CONSTRAINT fk_uda_forward_packages_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uda_forward_package_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    forward_package_id INT UNSIGNED NOT NULL,
    express_id INT UNSIGNED NOT NULL,
    tracking_no VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_uda_fwd_pkg_item_express (express_id),
    KEY idx_uda_fwd_pkg_item_pkg (forward_package_id),
    CONSTRAINT fk_uda_fwd_pkg_item_pkg FOREIGN KEY (forward_package_id) REFERENCES uda_forward_packages(id) ON DELETE CASCADE,
    CONSTRAINT fk_uda_fwd_pkg_item_express FOREIGN KEY (express_id) REFERENCES express_uda(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
