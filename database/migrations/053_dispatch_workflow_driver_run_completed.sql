-- 正式派送单：司机整单派送完成时间（与拣货完成 picking_completed_at 区分）
-- 全新 SQL 安装时须先有表；与 DispatchController::ensureDispatchDeliveryDocWorkflowTables 一致
CREATE TABLE IF NOT EXISTS dispatch_delivery_doc_workflow (
    delivery_doc_no VARCHAR(64) NOT NULL PRIMARY KEY,
    optimized_at DATETIME NULL,
    finalized_at DATETIME NULL,
    tokens_generated_at DATETIME NULL,
    assigned_driver_user_id INT UNSIGNED NULL,
    assigned_at DATETIME NULL,
    picking_completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_workflow_assigned_driver (assigned_driver_user_id),
    CONSTRAINT fk_workflow_assigned_driver FOREIGN KEY (assigned_driver_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db = DATABASE();
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @db AND table_name = 'dispatch_delivery_doc_workflow' AND column_name = 'driver_run_completed_at'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_delivery_doc_workflow ADD COLUMN driver_run_completed_at DATETIME NULL DEFAULT NULL COMMENT ''司机派送完成'' AFTER picking_completed_at'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
