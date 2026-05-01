INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('dispatch.forwarding.view', 'dispatch', 103, '派送-转发操作-页面查看'),
('dispatch.forwarding.package.create', 'dispatch', 104, '派送-转发操作-转发合包新增'),
('dispatch.forwarding.customer.manage', 'dispatch', 105, '派送-转发操作-客户维护')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
