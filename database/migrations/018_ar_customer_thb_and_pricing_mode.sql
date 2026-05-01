-- 应收账单：全站以泰铢计价；费用行记录所用计费形态
ALTER TABLE ar_customer_profiles
    MODIFY COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'THB';

UPDATE ar_customer_profiles SET currency = 'THB' WHERE currency IN ('CNY', '');

ALTER TABLE ar_charge_items
    ADD COLUMN pricing_mode VARCHAR(40) NOT NULL DEFAULT 'line_only' AFTER unit_name;
