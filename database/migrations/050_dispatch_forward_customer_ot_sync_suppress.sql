-- OT 线路派送客户会在「转发客户维护」页每次加载时自动写入 dispatch_forward_customers。
-- 用户在本页手动删除后，若无此表，下次打开页面会立刻被自动同步再次插入。
-- 本表记录「用户已在本页删除、且派送侧仍为 OT」的客户编码，使 autoSync 跳过，直至手动推送或派送主路线离开 OT。

CREATE TABLE IF NOT EXISTS dispatch_forward_customer_ot_sync_suppress (
    customer_code VARCHAR(60) NOT NULL COMMENT '与 dispatch_forward_customers.customer_code 一致，存 TRIM 后大写',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
