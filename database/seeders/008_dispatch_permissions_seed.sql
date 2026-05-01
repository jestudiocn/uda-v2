INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('menu.dispatch', 'dispatch', 90, '派送业务菜单'),
('dispatch.manage', 'dispatch', 91, '派送-全部管理'),
('dispatch.consigning_clients.view', 'dispatch', 92, '派送-委托客户查看'),
('dispatch.consigning_clients.edit', 'dispatch', 93, '派送-委托客户维护'),
('dispatch.delivery_customers.view', 'dispatch', 94, '派送-派送客户查看'),
('dispatch.delivery_customers.edit', 'dispatch', 95, '派送-派送客户维护'),
('dispatch.waybills.view', 'dispatch', 96, '派送-面单/订单查看'),
('dispatch.waybills.import', 'dispatch', 97, '派送-面单导入与接口'),
('dispatch.waybills.edit', 'dispatch', 98, '派送-订单修改')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
