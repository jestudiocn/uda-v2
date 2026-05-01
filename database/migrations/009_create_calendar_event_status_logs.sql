CREATE TABLE IF NOT EXISTS calendar_event_status_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    changed_by INT UNSIGNED DEFAULT NULL,
    old_progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    new_progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    old_is_completed TINYINT(1) NOT NULL DEFAULT 0,
    new_is_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_calendar_status_logs_event (event_id),
    KEY idx_calendar_status_logs_user (changed_by),
    CONSTRAINT fk_calendar_status_logs_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_status_logs_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
