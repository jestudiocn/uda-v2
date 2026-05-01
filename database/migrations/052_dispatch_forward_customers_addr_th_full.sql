-- 转发客户：地址栏位改为「完整泰文地址」addr_th_full；去掉未使用的 contact_name、remark。
-- 依赖：027、028。可重复执行。

SET @db := DATABASE();

-- address -> addr_th_full（保留原数据）
SET @has_addr := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'address'
);
SET @has_th := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'addr_th_full'
);
SET @rename_addr := IF(
    @has_addr > 0 AND @has_th = 0,
    'ALTER TABLE dispatch_forward_customers CHANGE COLUMN address addr_th_full VARCHAR(1000) NOT NULL DEFAULT '''' COMMENT ''完整泰文地址（自派送客户同步）''',
    'SELECT 1'
);
PREPARE r1 FROM @rename_addr; EXECUTE r1; DEALLOCATE PREPARE r1;

-- 若曾手动加过 addr_th_full 而 address 已不存在，跳过
SET @drop_contact := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'contact_name'
    ),
    'ALTER TABLE dispatch_forward_customers DROP COLUMN contact_name',
    'SELECT 1'
);
PREPARE r2 FROM @drop_contact; EXECUTE r2; DEALLOCATE PREPARE r2;

SET @drop_remark := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dispatch_forward_customers' AND COLUMN_NAME = 'remark'
    ),
    'ALTER TABLE dispatch_forward_customers DROP COLUMN remark',
    'SELECT 1'
);
PREPARE r3 FROM @drop_remark; EXECUTE r3; DEALLOCATE PREPARE r3;
