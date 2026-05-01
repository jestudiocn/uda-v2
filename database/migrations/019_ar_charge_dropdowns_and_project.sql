-- 应收费用：类目/计费单位下拉可维护；费用行增加「项目」
CREATE TABLE IF NOT EXISTS ar_charge_dropdown_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    option_group ENUM('category', 'unit') NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ar_dropdown_group_name (option_group, name),
    KEY idx_ar_dropdown_group_status (option_group, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ar_charge_items
    ADD COLUMN project_name VARCHAR(200) NOT NULL DEFAULT '' AFTER category_name;

INSERT IGNORE INTO ar_charge_dropdown_options (option_group, name, sort_order, status) VALUES
    ('category', '顾问费', 10, 1),
    ('category', '人力', 20, 1),
    ('unit', '人天', 10, 1),
    ('unit', '月', 20, 1);
