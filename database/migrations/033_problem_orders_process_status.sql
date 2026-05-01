-- 为问题订单增加三态处理状态（未处理/处理中/已处理）
SET @schema := DATABASE();
SET @tbl_exists := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = @schema AND table_name = 'problem_orders'
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema
    AND table_name = 'problem_orders'
    AND column_name = 'process_status'
);

SET @sql := IF(
  @tbl_exists = 1 AND @col_exists = 0,
  "ALTER TABLE `problem_orders` ADD COLUMN `process_status` VARCHAR(20) NOT NULL DEFAULT '未处理' AFTER `is_processed`",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 用历史 is_processed 数据初始化 process_status
UPDATE `problem_orders`
SET `process_status` = CASE
    WHEN COALESCE(`is_processed`, 0) = 1 THEN '已处理'
    ELSE '未处理'
END
WHERE TRIM(COALESCE(`process_status`, '')) = '';
