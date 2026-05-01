INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('menu.finance', 'finance', 60, '财务管理菜单'),
('finance.manage', 'finance', 61, '财务-全部管理'),
('finance.transactions.view', 'finance', 62, '财务记录-查看'),
('finance.transactions.create', 'finance', 63, '财务记录-新增'),
('finance.transactions.edit', 'finance', 64, '财务记录-编辑'),

('finance.payables.view', 'finance', 65, '待付款-查看'),
('finance.payables.create', 'finance', 66, '待付款-新增'),
('finance.payables.settle', 'finance', 67, '待付款-确认付款'),

('finance.receivables.view', 'finance', 68, '待收款-查看'),
('finance.receivables.create', 'finance', 69, '待收款-新增'),
('finance.receivables.settle', 'finance', 70, '待收款-确认收款'),

('finance.accounts.view', 'finance', 71, '财务账户-查看'),
('finance.accounts.create', 'finance', 72, '财务账户-新增'),
('finance.accounts.edit', 'finance', 73, '财务账户-编辑'),

('finance.categories.view', 'finance', 74, '财务类目-查看'),
('finance.categories.create', 'finance', 75, '财务类目-新增'),
('finance.categories.edit', 'finance', 76, '财务类目-编辑'),

('finance.parties.view', 'finance', 77, '付款收款对象-查看'),
('finance.parties.create', 'finance', 78, '付款收款对象-新增'),
('finance.parties.edit', 'finance', 79, '付款收款对象-编辑'),

('finance.reports.view', 'finance', 80, '财务报表-查看'),
('finance.reports.export', 'finance', 81, '财务报表-导出'),

('finance.ar.customers', 'finance', 82, '应收账单-客户计费档案'),
('finance.ar.charges.create', 'finance', 83, '应收账单-新增费用记录'),
('finance.ar.charges.view', 'finance', 84, '应收账单-查看费用记录'),
('finance.ar.invoices.create', 'finance', 85, '应收账单-生成账单'),
('finance.ar.invoices.view', 'finance', 86, '应收账单-查看账单'),
('finance.ar.invoices.export', 'finance', 87, '应收账单-导出未收款'),
('finance.ar.ledger.view', 'finance', 88, '应收账单-查看应收台账')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);

INSERT INTO notification_rules (event_key, rule_name, enabled, recipients_mode, custom_user_ids)
VALUES
('finance.payables.created', '财务：待付款新增', 1, 'creator', NULL),
('finance.payables.settled', '财务：待付款已付款', 1, 'creator', NULL),
('finance.receivables.created', '财务：待收款新增', 1, 'creator', NULL),
('finance.receivables.settled', '财务：待收款已收款', 1, 'creator', NULL),
('finance.ar.invoice.created', '财务：应收账单已生成', 1, 'creator', NULL),
('finance.ar.invoice.settled', '财务：应收账单已冲销', 1, 'creator', NULL)
ON DUPLICATE KEY UPDATE
rule_name = VALUES(rule_name),
updated_at = CURRENT_TIMESTAMP;
