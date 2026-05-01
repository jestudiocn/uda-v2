INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('dashboard.calendar.manage', 'dashboard', 11, '行事历-新增事件')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
