CREATE TABLE IF NOT EXISTS notification_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(80) NOT NULL UNIQUE,
    rule_name VARCHAR(120) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    recipients_mode VARCHAR(40) NOT NULL DEFAULT 'creator_and_assignees',
    custom_user_ids TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications_inbox (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    content VARCHAR(1000) DEFAULT NULL,
    biz_type VARCHAR(60) DEFAULT NULL,
    biz_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user_time (user_id, created_at),
    KEY idx_notifications_biz (biz_type, biz_id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO notification_rules (event_key, rule_name, enabled, recipients_mode, custom_user_ids)
VALUES
    ('calendar.event_created', '行事历：新增事件', 1, 'creator_and_assignees', NULL),
    ('calendar.status_updated', '行事历：状态更新', 1, 'creator_and_assignees', NULL),
    ('calendar.completed', '行事历：事件完成', 1, 'creator_and_assignees', NULL),
    ('auth.login', '认证：用户登入', 1, 'creator', NULL)
ON DUPLICATE KEY UPDATE
    rule_name = VALUES(rule_name),
    updated_at = CURRENT_TIMESTAMP;
