# 业务口径速查索引（按左侧菜单，常用字段 TOP10）

> 用途：给运营、测试、实施同事快速对照“页面字段 ↔ 数据库字段”。  
> 说明：每个模块仅列高频字段 Top10；完整字段请看《全站数据库字典_英文名-标准中文名-业务叫法》。

---

## 1) 首页（聚合看板）

1. `notifications_inbox.title` -> 通知标题 -> 首页通知标题
2. `notifications_inbox.is_read` -> 已读标记 -> 通知已读状态
3. `notifications_inbox.created_at` -> 创建时间 -> 通知时间
4. `calendar_events.title` -> 事件标题 -> 今日日程标题
5. `calendar_events.start_date` -> 开始日期 -> 日程开始
6. `calendar_events.end_date` -> 结束日期 -> 日程结束
7. `calendar_events.progress_percent` -> 进度百分比 -> 日程进度
8. `payables.amount` -> 金额 -> 待付款金额
9. `receivables.amount` -> 金额 -> 待收款金额
10. `receivables.expected_receive_date` -> 预计收款日期 -> 预计收款日

---

## 2) 行事历管理

1. `calendar_events.title` -> 标题 -> 事件标题
2. `calendar_events.event_type` -> 事件类型 -> 提醒/任务类型
3. `calendar_events.start_date` -> 开始日期 -> 开始日期
4. `calendar_events.end_date` -> 结束日期 -> 结束日期
5. `calendar_events.progress_percent` -> 进度百分比 -> 进度%
6. `calendar_events.is_completed` -> 完成标记 -> 是否完成
7. `calendar_events.note` -> 备注 -> 事件说明
8. `calendar_event_assignees.user_id` -> 用户ID -> 指派对象
9. `calendar_event_status_logs.new_progress_percent` -> 新进度百分比 -> 更新后进度
10. `calendar_event_status_logs.created_at` -> 创建时间 -> 进度更新时间

---

## 3) 派送业务 / 订单与派送

1. `dispatch_waybills.original_tracking_no` -> 原始面单号 -> 面单号（主查询）
2. `dispatch_waybills.delivery_customer_code` -> 派送客户编号 -> 客户编码
3. `dispatch_waybills.weight_kg` -> 重量(kg) -> 重量
4. `dispatch_waybills.length_cm` -> 长(cm) -> 长
5. `dispatch_waybills.width_cm` -> 宽(cm) -> 宽
6. `dispatch_waybills.height_cm` -> 高(cm) -> 高
7. `dispatch_delivery_customers.customer_code` -> 客户编码 -> 客户编码
8. `dispatch_delivery_customers.wechat_id` -> 微信号 -> 微信
9. `dispatch_delivery_customers.line_id` -> Line号 -> Line
10. `dispatch_waybills.delivered_at` -> 最后状态更新时间 -> 最后状态更新时间

---

## 4) 派送业务 / 转发操作

1. `dispatch_forward_customers.customer_code` -> 客户代码 -> 转发客户编码
2. `dispatch_forward_customers.customer_name` -> 客户名称 -> 转发客户名称
3. `dispatch_forward_packages.package_no` -> 转发单号 -> 转发包号
4. `dispatch_forward_packages.send_at` -> 发出时间 -> 转发发出时间
5. `dispatch_forward_packages.forward_fee` -> 转发费用 -> 转发运费
6. `dispatch_forward_packages.voucher_path` -> 凭证路径 -> 凭证图片
7. `dispatch_forward_package_items.original_tracking_no` -> 原始面单号 -> 转发面单号
8. `dispatch_waybills.auto_forward_opt_out` -> 自动转发排除标记 -> 不再自动推待转发
9. `dispatch_waybills.order_status` -> 订单状态 -> 待转发/已入库
10. `dispatch_waybills.delivery_customer_code` -> 派送客户编号 -> 客户编码匹配

---

## 5) 派送业务 / 司机端

1. `dispatch_driver_run_tokens.token` -> 访问令牌 -> 司机链接令牌
2. `dispatch_driver_run_tokens.delivery_doc_no` -> 派送单号 -> 所属派送单
3. `dispatch_driver_run_tokens.segment_index` -> 分段序号 -> 第几段路线
4. `dispatch_driver_run_tokens.expires_at` -> 过期时间 -> 链接有效期
5. `dispatch_delivery_doc_stops.stop_order` -> 停靠顺序 -> 客户顺序
6. `dispatch_delivery_doc_stops.segment_index` -> 分段序号 -> 路线段
7. `dispatch_delivery_doc_stops.is_final` -> 最终发布标记 -> 是否已发布
8. `dispatch_delivery_pod.customer_code` -> 客户编码 -> 签收客户
9. `dispatch_delivery_pod.photo1_path` -> 照片1路径 -> 现场照片1
10. `dispatch_delivery_pod.photo2_path` -> 照片2路径 -> 现场照片2

---

## 6) UDA快件 / 问题订单

1. `problem_orders.tracking_no` -> 面单号 -> 面单号
2. `problem_orders.problem_status` -> 问题状态 -> 问题状态
3. `problem_orders.reason_option_id` -> 原因选项ID -> 问题原因
4. `problem_orders.handle_method_id` -> 处理方式ID -> 处理方式
5. `problem_orders.location_id` -> 地点ID -> 当前位置
6. `problem_orders.resolution_note` -> 处理说明 -> 处理备注
7. `problem_orders.created_at` -> 创建时间 -> 报问题时间
8. `problem_order_reason_options.reason_name` -> 原因名称 -> 原因项
9. `problem_order_handle_methods.method_name` -> 方式名称 -> 处理方式项
10. `problem_order_locations.location_name` -> 地点名称 -> 地点项

---

## 7) UDA快件 / 集包管理 + 批次操作

1. `uda_manifest_batches.date_no` -> 日期号 -> 日期号
2. `uda_manifest_batches.bill_no` -> 提单号 -> 提单号
3. `uda_manifest_bundles.bundle_no` -> 集包号 -> 集包号
4. `uda_manifest_bundle_waybills.tracking_no` -> 面单号 -> 集包面单
5. `uda_warehouse_batches.date_no` -> 日期号 -> 日期号
6. `uda_warehouse_batches.bill_no` -> 提单号 -> 提单号
7. `uda_warehouse_batches.uda_count` -> UDA件数 -> UDA件数
8. `uda_warehouse_batches.jd_count` -> JD件数 -> JD件数
9. `uda_warehouse_batches.total_count` -> 总件数 -> 总件数
10. `uda_warehouse_batch_waybills.tracking_no` -> 面单号 -> 批次面单
11. 装箱单导出（`manifestPackingListExport`） -> 按日期号汇总集包/面单生成 xlsx；净重为导出时「毛重合计×0.80～0.90（步进 0.01）」再取两位小数（非库字段）-> 入口：集包列表进入日期号明细后「导出装箱单（Excel）」

---

## 8) 财务管理（基础财务）

1. `transactions.type` -> 交易类型 -> 收入/支出
2. `transactions.amount` -> 金额 -> 流水金额
3. `transactions.party_id` -> 往来方ID -> 收付款对象
4. `transactions.category_id` -> 类目ID -> 财务分类
5. `transactions.account_id` -> 账户ID -> 收付账户
6. `transactions.voucher_path` -> 凭证路径 -> 交易凭证
7. `payables.amount` -> 金额 -> 应付金额
8. `payables.expected_pay_date` -> 预计付款日期 -> 预计付款日
9. `receivables.amount` -> 金额 -> 应收金额
10. `receivables.expected_receive_date` -> 预计收款日期 -> 预计收款日

---

## 9) 财务管理（AR）

1. `ar_customer_profiles.party_id` -> 往来方ID -> AR客户
2. `ar_customer_profiles.billing_cycle` -> 计费周期 -> 出账周期
3. `ar_party_billing_schemes.scheme_label` -> 方案名称 -> 计费方式名
4. `ar_charge_items.billing_date` -> 计费日期 -> 费用日期
5. `ar_charge_items.category_name` -> 类目名称 -> 费用类目
6. `ar_charge_items.calculated_amount` -> 计算金额 -> 费用金额
7. `ar_invoices.invoice_no` -> 账单号 -> 账单号
8. `ar_invoices.period_start` -> 账期开始 -> 账期起
9. `ar_invoices.period_end` -> 账期结束 -> 账期止
10. `ar_receivable_ledger.balance_after` -> 变更后余额 -> 台账余额

---

## 10) 系统管理

1. `users.username` -> 登录账号 -> 用户账号
2. `users.full_name` -> 姓名 -> 用户姓名
3. `users.role_id` -> 角色ID -> 主角色
4. `users.status` -> 状态 -> 启停状态
5. `roles.role_name` -> 角色名称 -> 角色名
6. `permissions.permission_key` -> 权限键 -> 权限编码
7. `permissions.permission_name` -> 权限名称 -> 权限名称
8. `notification_rules.event_key` -> 事件键 -> 通知触发事件
9. `notifications_inbox.is_read` -> 已读标记 -> 通知已读
10. `system_audit_logs.action_key` -> 动作键 -> 操作动作

---

## 11) 非菜单基础主数据与迁移中间表

1. `customers.customer_code` -> 客户编码 -> 客户编码
2. `suppliers.supplier_code` -> 供应商编码 -> 供应商编码
3. `products.sku` -> SKU编码 -> SKU
4. `warehouses.warehouse_code` -> 仓库编码 -> 仓库编码
5. `departments.dept_code` -> 部门编码 -> 部门代码
6. `mig_v1_transactions.amount` -> 金额 -> V1迁移金额
7. `mig_v1_transactions.created_at` -> 创建时间 -> V1交易时间
8. `mig_v1_payables.status` -> 状态 -> V1应付状态
9. `mig_v1_receivables.status` -> 状态 -> V1应收状态
10. `mig_v1_accounts.account_name` -> 账户名称 -> V1账户名

---

## 使用建议（给运营/测试）

- 查不到字段：先用本文件定位模块高频字段，再去完整字典看全字段。  
- 同名字段口径：`status` 在不同表含义不同，务必结合“表名 + 字段名”一起确认。  
- 导出/报表核对：优先核对 `金额 + 状态 + 日期 + 单号` 四类字段。  
