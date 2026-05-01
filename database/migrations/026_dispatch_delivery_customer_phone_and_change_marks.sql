-- 派送客户：新增电话字段，以及「新增/地址定位变更」时间戳标记
-- 依赖：021_dispatch_core_tables.sql（可叠加在 024 之后执行）

ALTER TABLE dispatch_delivery_customers
ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT '' COMMENT '联系电话' AFTER line_id,
ADD COLUMN created_marked_at DATETIME NULL COMMENT '新增标记时间（用于前端显示“新”）' AFTER updated_at,
ADD COLUMN address_geo_updated_at DATETIME NULL COMMENT '地址或定位变更时间（用于前端显示“改”）' AFTER created_marked_at;

UPDATE dispatch_delivery_customers
SET created_marked_at = COALESCE(created_at, NOW())
WHERE created_marked_at IS NULL;
