-- 提单号全局唯一、面单号全局唯一（全库不重复）
-- 说明：表结构里存在 batch_* 历史命名，业务语义均按“提单”理解。
-- 在已执行 036 之后运行；可重复执行（索引已存在则跳过）
--
-- 注意：删除 uk_uda_manifest_waybill_batch 前须先有仅含 batch_id 的索引，
-- 否则外键 fk_uda_manifest_waybill_batch 会占用该唯一索引而无法 DROP。

SET @db := DATABASE();

-- 1) 为 batch_id 外键提供独立普通索引（与复合唯一索引并存，便于后续删除复合唯一）
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = @db AND table_name = 'uda_manifest_bundle_waybills' AND index_name = 'idx_uda_manifest_waybill_batch_id') > 0,
        'SELECT 1',
        'ALTER TABLE uda_manifest_bundle_waybills ADD KEY idx_uda_manifest_waybill_batch_id (batch_id)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) 删除旧的面单唯一约束 (batch_id, tracking_no)
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = @db AND table_name = 'uda_manifest_bundle_waybills' AND index_name = 'uk_uda_manifest_waybill_batch') > 0,
        'ALTER TABLE uda_manifest_bundle_waybills DROP INDEX uk_uda_manifest_waybill_batch',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) tracking_no 全局唯一
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = @db AND table_name = 'uda_manifest_bundle_waybills' AND index_name = 'uk_uda_manifest_tracking_global') > 0,
        'SELECT 1',
        'ALTER TABLE uda_manifest_bundle_waybills ADD UNIQUE KEY uk_uda_manifest_tracking_global (tracking_no)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) 提单号 batch_code 全局唯一
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = @db AND table_name = 'uda_manifest_batches' AND index_name = 'uk_uda_manifest_batch_code') > 0,
        'SELECT 1',
        'ALTER TABLE uda_manifest_batches ADD UNIQUE KEY uk_uda_manifest_batch_code (batch_code)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
