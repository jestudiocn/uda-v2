CREATE TABLE IF NOT EXISTS system_audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(40) NOT NULL,
    action_key VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) DEFAULT NULL,
    target_id INT UNSIGNED DEFAULT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    actor_name VARCHAR(120) DEFAULT NULL,
    detail_json TEXT DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_module_time (module_key, created_at),
    KEY idx_audit_actor_time (actor_user_id, created_at),
    CONSTRAINT fk_audit_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
