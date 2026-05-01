-- 用户绑定委托客户：绑定后该账号在「订单查询」等派送订单功能中仅能访问该委托客户的数据，
-- 且前端不再展示「委托客户」筛选（由系统自动限定范围）。
-- 依赖：001_init_core_tables.sql、021_dispatch_core_tables.sql

ALTER TABLE users
ADD COLUMN dispatch_consigning_client_id INT UNSIGNED NULL DEFAULT NULL
    COMMENT '绑定委托客户；非空时订单等数据仅限该客户'
    AFTER role_id;

ALTER TABLE users
ADD CONSTRAINT fk_users_dispatch_consigning_client
    FOREIGN KEY (dispatch_consigning_client_id) REFERENCES dispatch_consigning_clients(id)
    ON DELETE SET NULL;
