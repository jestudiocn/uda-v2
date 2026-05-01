INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('menu.permissions', 'system', 40, '权限管理菜单'),
('menu.notifications', 'notifications', 50, '通知管理菜单'),
('menu.logs', 'system', 60, '日志管理菜单')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
