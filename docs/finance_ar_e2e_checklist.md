# 财务应收账单 E2E 验证清单

## 1. 初始化
- 执行迁移：`017_create_finance_ar_tables.sql`
- 执行迁移：`018_ar_customer_thb_and_pricing_mode.sql`（泰铢默认、费用行 `pricing_mode` 字段）
- 执行权限与通知种子：`007_permissions_finance_seed.sql`
- 用具备 `finance.ar.*` 权限的账号登录

## 2. 客户计费档案
- 进入 `/finance/ar/customers`
- 选择客户，勾选至少一种「计费形态」（可多选，对应同时使用多种计费的客户）
- 预期：列表显示该客户已启用的形态说明；系统固定以泰铢（THB）计价，无需填写币种

## 3. 费用记录
- 进入 `/finance/ar/charges/create`
- 选择客户后，在「计费形态」下拉中选择本次费用使用的形态
- **按量计价**：单价 `100`、数量 `2` → 金额 `200.00`
- **固定费用 + 按量**：基础费用 `20`、单价 `100`、数量 `2` → 金额 `220.00`
- 预期：
  - 成功保存
  - `/finance/ar/charges/list` 显示正确金额与计费形态列
  - 状态为 `draft`

## 4. 生成账单并自动转待收款
- 进入 `/finance/ar/invoices/list`
- 按包含上一步费用记录的区间生成账单
- 预期：
  - 账单生成成功，状态 `issued`
  - 自动创建 1 笔待收款（`receivables`）
  - 费用记录状态从 `draft` 变成 `invoiced`
  - `/finance/ar/ledger` 新增 1 笔借方分录（开票增加应收）

## 5. 导出未收款明细
- 在账单列表点击「导出未收款」
- 预期：
  - 成功下载 CSV
  - 包含客户、账单号、明细行、计费形态（已执行 018 时）、小计、总和
  - 总和与账单金额一致

## 6. 收款与冲销
- 进入 `/finance/receivables/settle?id={receivable_id}`
- 完成确认收款
- 预期：
  - 待收款状态更新为 `received`
  - 自动生成收入交易（`transactions`）
  - 对应 `ar_invoices.status` 更新为 `paid`
  - `/finance/ar/ledger` 新增贷方冲销分录
  - 台账余额正确减少

## 7. 对账核对
- 客户维度核对：应收台账余额 = 所有借方合计 - 所有贷方合计
- 核对账单、待收款、交易三者金额一致

## 8. 扩展新计费形态（开发侧）
- 在 `FinanceController::arPricingModeCatalogue()` 增加选项文案
- 在 `arComputeChargeAmount()` 增加分支与参数约定
- 客户档案中即可勾选新形态，无需业务人员编写公式
