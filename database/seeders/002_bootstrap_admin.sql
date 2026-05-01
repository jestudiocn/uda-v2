-- 默认角色与管理员账号（仅首次）
INSERT INTO roles (role_name, description)
VALUES ('super_admin', '系统超级管理员')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 管理员初始密码：123456（首次登录请修改）
INSERT INTO users (username, password_hash, full_name, role_id, status, must_change_password)
SELECT
    'admin',
    '$2y$10$Jt6Rn5fGkuNRwztA2x0uNO92b5vax5DZR2PVGobKLro0HNH/qd7US',
    '系统管理员',
    r.id,
    1,
    1
FROM roles r
WHERE r.role_name = 'super_admin'
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');
