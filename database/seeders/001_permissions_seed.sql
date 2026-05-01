INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('menu.dashboard', 'dashboard', 10, '控制台'),
('menu.users', 'users', 20, '用户管理菜单'),
('menu.roles', 'roles', 30, '角色管理菜单'),
('users.manage', 'users', 21, '用户管理'),
('roles.manage', 'roles', 31, '角色管理'),
('master.customers.view', 'master_data', 100, '客户资料-查看'),
('master.customers.manage', 'master_data', 101, '客户资料-管理'),
('master.suppliers.view', 'master_data', 110, '供应商-查看'),
('master.suppliers.manage', 'master_data', 111, '供应商-管理'),
('master.products.view', 'master_data', 120, '商品资料-查看'),
('master.products.manage', 'master_data', 121, '商品资料-管理'),
('master.warehouses.view', 'master_data', 130, '仓库资料-查看'),
('master.warehouses.manage', 'master_data', 131, '仓库资料-管理')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
