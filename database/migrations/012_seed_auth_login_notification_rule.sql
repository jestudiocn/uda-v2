INSERT INTO notification_rules (event_key, rule_name, enabled, recipients_mode, custom_user_ids)
VALUES ('auth.login', '认证：用户登入', 1, 'creator', NULL)
ON DUPLICATE KEY UPDATE
    rule_name = VALUES(rule_name),
    updated_at = CURRENT_TIMESTAMP;
