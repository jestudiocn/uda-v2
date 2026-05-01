-- 派送客户：客户状态（正常/异常/暂停/转发）、主副路线合并字段（供列表与日后导出）
-- 依赖：021_dispatch_core_tables.sql
-- 说明：原 status  tinyint 仍为「启用=1 / 停用=0」，与订单匹配逻辑一致；customer_state 为业务状态。

ALTER TABLE dispatch_delivery_customers
ADD COLUMN routes_combined VARCHAR(250) NOT NULL DEFAULT '' COMMENT '主路线-副路线' AFTER route_secondary,
ADD COLUMN customer_state VARCHAR(20) NOT NULL DEFAULT '正常' COMMENT '正常、异常、暂停、转发' AFTER community_name_th;

UPDATE dispatch_delivery_customers
SET routes_combined = CASE
    WHEN TRIM(route_primary) = '' AND TRIM(route_secondary) = '' THEN ''
    WHEN TRIM(route_secondary) = '' THEN TRIM(route_primary)
    WHEN TRIM(route_primary) = '' THEN TRIM(route_secondary)
    ELSE CONCAT(TRIM(route_primary), ' - ', TRIM(route_secondary))
END;

UPDATE dispatch_delivery_customers SET customer_state = '正常' WHERE customer_state = '' OR customer_state IS NULL;
