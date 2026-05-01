INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('users.create', 'users', 22, '用户-新增'),
('users.toggle', 'users', 23, '用户-启停'),
('users.reset_password', 'users', 24, '用户-重置密码'),
('roles.create', 'roles', 32, '角色-新增'),
('roles.edit', 'roles', 33, '角色-编辑'),
('permissions.assign', 'system', 41, '权限-保存配置')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
