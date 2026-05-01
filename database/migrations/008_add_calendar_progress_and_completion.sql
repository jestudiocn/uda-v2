ALTER TABLE calendar_events
    ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER event_type,
    ADD COLUMN is_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER progress_percent;
