INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('dispatch.package_ops.arrival_scan', 'dispatch', 99, '派送-货件操作-到件扫描'),
('dispatch.package_ops.self_pickup', 'dispatch', 100, '派送-货件操作-自取录入'),
('dispatch.package_ops.status_fix', 'dispatch', 101, '派送-货件操作-货件状态修正'),
('dispatch.waybills.customer_code.edit', 'dispatch', 102, '派送-订单客户编码修改')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
