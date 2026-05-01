-- 迁移批次建议：先导近12个月，再补历史。
-- 以下模板默认 staging 表已通过 014 创建并导入了 V1 数据。

-- 1) 迁移账户
INSERT INTO accounts (id, account_name, account_type, status)
SELECT s.id, s.account_name, s.account_type, s.status
FROM mig_v1_accounts s
ON DUPLICATE KEY UPDATE
    account_name = VALUES(account_name),
    account_type = VALUES(account_type),
    status = VALUES(status);

-- 2) 迁移类目
INSERT INTO transaction_categories (id, name, type, status)
SELECT s.id, s.name, s.type, s.status
FROM mig_v1_transaction_categories s
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    type = VALUES(type),
    status = VALUES(status);

-- 3) 迁移交易（近12个月示例）
INSERT INTO transactions (id, type, amount, client, category_id, account_id, description, created_by, created_at)
SELECT
    s.id, s.type, s.amount, s.client, s.category_id, s.account_id, s.description, s.created_by, COALESCE(s.created_at, NOW())
FROM mig_v1_transactions s
WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
ON DUPLICATE KEY UPDATE
    type = VALUES(type),
    amount = VALUES(amount),
    client = VALUES(client),
    category_id = VALUES(category_id),
    account_id = VALUES(account_id),
    description = VALUES(description),
    created_by = VALUES(created_by),
    created_at = VALUES(created_at);

-- 4) 迁移待付款（近12个月示例）
INSERT INTO payables (
    id, vendor_name, amount, expected_pay_date, remark, status, created_by, paid_at, paid_transaction_id, created_at
)
SELECT
    s.id, s.vendor_name, s.amount, s.expected_pay_date, s.remark, s.status, s.created_by, s.paid_at, s.paid_transaction_id, COALESCE(s.created_at, NOW())
FROM mig_v1_payables s
WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
ON DUPLICATE KEY UPDATE
    vendor_name = VALUES(vendor_name),
    amount = VALUES(amount),
    expected_pay_date = VALUES(expected_pay_date),
    remark = VALUES(remark),
    status = VALUES(status),
    created_by = VALUES(created_by),
    paid_at = VALUES(paid_at),
    paid_transaction_id = VALUES(paid_transaction_id),
    created_at = VALUES(created_at);

-- 5) 迁移待收款（近12个月示例）
INSERT INTO receivables (
    id, client_name, amount, expected_receive_date, remark, status, created_by, received_at, received_transaction_id, created_at
)
SELECT
    s.id, s.client_name, s.amount, s.expected_receive_date, s.remark, s.status, s.created_by, s.received_at, s.received_transaction_id, COALESCE(s.created_at, NOW())
FROM mig_v1_receivables s
WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
ON DUPLICATE KEY UPDATE
    client_name = VALUES(client_name),
    amount = VALUES(amount),
    expected_receive_date = VALUES(expected_receive_date),
    remark = VALUES(remark),
    status = VALUES(status),
    created_by = VALUES(created_by),
    received_at = VALUES(received_at),
    received_transaction_id = VALUES(received_transaction_id),
    created_at = VALUES(created_at);

-- 6) 对账检查（迁移后执行）
-- 6.1 总收入支出
SELECT
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income_total,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense_total
FROM transactions;

-- 6.2 未结清笔数
SELECT
    (SELECT COUNT(*) FROM payables WHERE status = 'pending') AS pending_payables,
    (SELECT COUNT(*) FROM receivables WHERE status = 'pending') AS pending_receivables;
