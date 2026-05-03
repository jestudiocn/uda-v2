-- 订单：司机上传签收照片并置为「已派送」时写入派送司机姓名（订单查询列表不展示，已派送弹窗展示）

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE table_schema = @db AND table_name = 'dispatch_waybills' AND column_name = 'delivery_driver_name') > 0,
        'SELECT 1',
        'ALTER TABLE dispatch_waybills ADD COLUMN delivery_driver_name VARCHAR(120) NULL DEFAULT NULL COMMENT ''司机上传签收时写入（users.full_name）'' AFTER order_status'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
