-- 问题订单：处理方式管理字典
-- 可重复执行：表已存在则跳过

CREATE TABLE IF NOT EXISTS problem_order_handle_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_problem_handle_methods_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化最小可用数据（仅空表）
INSERT INTO problem_order_handle_methods (method_name, sort_order, is_active)
SELECT '待确认', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM problem_order_handle_methods LIMIT 1);
