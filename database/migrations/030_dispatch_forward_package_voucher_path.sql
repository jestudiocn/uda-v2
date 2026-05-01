-- 转发合包：凭证图片路径（仅保存文件名，文件存储在本地目录）
-- 可重复执行：已存在列会跳过

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_packages' AND COLUMN_NAME = 'voucher_path'
        ),
        'SELECT 1',
        'ALTER TABLE dispatch_forward_packages ADD COLUMN voucher_path VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''凭证图片文件名'' AFTER receiver_phone'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
