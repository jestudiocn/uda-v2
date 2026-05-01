-- UDA 快件 / 仓内操作：将主标识调整为“日期号”，并新增“提单号”字段
-- 兼容旧库：保留 batch_code 历史列，回填到 date_no，后续业务以 date_no 为准

SET @db := DATABASE();

-- 1) 增加 date_no（日期号）
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='uda_manifest_batches' AND COLUMN_NAME='date_no'
        ),
        'SELECT 1',
        'ALTER TABLE uda_manifest_batches ADD COLUMN date_no VARCHAR(100) NOT NULL DEFAULT '''' AFTER batch_code'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) 增加 bill_no（提单号）
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='uda_manifest_batches' AND COLUMN_NAME='bill_no'
        ),
        'SELECT 1',
        'ALTER TABLE uda_manifest_batches ADD COLUMN bill_no VARCHAR(100) NOT NULL DEFAULT '''' AFTER date_no'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) 历史数据回填：date_no 为空时使用 batch_code
UPDATE uda_manifest_batches
SET date_no = batch_code
WHERE COALESCE(date_no, '') = '';

-- 4) date_no 全库唯一
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema=@db AND table_name='uda_manifest_batches' AND index_name='uk_uda_manifest_date_no'
        ),
        'SELECT 1',
        'ALTER TABLE uda_manifest_batches ADD UNIQUE KEY uk_uda_manifest_date_no (date_no)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) 查询索引：日期号+状态
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema=@db AND table_name='uda_manifest_batches' AND index_name='idx_uda_manifest_date_no_status'
        ),
        'SELECT 1',
        'ALTER TABLE uda_manifest_batches ADD KEY idx_uda_manifest_date_no_status (date_no, status)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

