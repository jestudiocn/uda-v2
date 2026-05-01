ALTER TABLE calendar_events
    ADD COLUMN start_date DATE DEFAULT NULL AFTER event_date,
    ADD COLUMN end_date DATE DEFAULT NULL AFTER start_date;

UPDATE calendar_events
SET start_date = event_date,
    end_date = event_date
WHERE start_date IS NULL OR end_date IS NULL;
