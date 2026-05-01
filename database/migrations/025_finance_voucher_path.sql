-- 财务凭证图档：存 storage/finance_vouchers 下的文件名（仅 basename，可空）
ALTER TABLE transactions ADD COLUMN voucher_path VARCHAR(512) NULL DEFAULT NULL;
ALTER TABLE payables ADD COLUMN voucher_path VARCHAR(512) NULL DEFAULT NULL;
ALTER TABLE receivables ADD COLUMN voucher_path VARCHAR(512) NULL DEFAULT NULL;
