INSERT INTO permissions (permission_key, module_key, sort_order, permission_name) VALUES
('calendar.events.update_status', 'calendar', 140, '行事历-事件状态更新'),
('notifications.rules.save', 'system', 360, '通知-规则保存'),
('notifications.inbox.mark_read', 'system', 361, '通知-标记已读'),
('notifications.manage', 'system', 362, '通知-全部管理'),
('finance.ar.billing_scheme.create', 'finance', 265, '财务应收-计费方式新增'),
('finance.ar.billing_scheme.toggle', 'finance', 266, '财务应收-计费方式启停')
ON DUPLICATE KEY UPDATE
permission_name = VALUES(permission_name),
module_key = VALUES(module_key),
sort_order = VALUES(sort_order);
