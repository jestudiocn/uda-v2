INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('menu.calendar', 'dashboard', 12, '行事历管理菜单'),
('calendar.events.view', 'dashboard', 13, '行事历-事件列表'),
('calendar.events.create', 'dashboard', 14, '行事历-新增事件')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
