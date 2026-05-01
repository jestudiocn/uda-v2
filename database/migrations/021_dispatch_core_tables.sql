-- 派送业务：委托客户、派送客户（收件人）、面单（订单列表一行=一原始面单）
-- 委托客户 = 委托我司派送的一方；派送客户 = 其下的收件人，以编号与货件里的「派送客户编号」对应。

CREATE TABLE IF NOT EXISTS dispatch_consigning_clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(40) NOT NULL COMMENT '委托客户内部编号，全局唯一',
    client_name VARCHAR(160) NOT NULL,
    party_id INT UNSIGNED DEFAULT NULL COMMENT '可选关联财务往来对象',
    status TINYINT(1) NOT NULL DEFAULT 1,
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_consigning_client_code (client_code),
    KEY idx_dispatch_consigning_status (status),
    CONSTRAINT fk_dispatch_consigning_party FOREIGN KEY (party_id) REFERENCES finance_parties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_delivery_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consigning_client_id INT UNSIGNED NOT NULL,
    customer_code VARCHAR(60) NOT NULL COMMENT '派送客户编号，同一委托客户下唯一',
    wechat_id VARCHAR(120) NOT NULL DEFAULT '',
    line_id VARCHAR(120) NOT NULL DEFAULT '',
    lane_or_house_no VARCHAR(120) NOT NULL DEFAULT '' COMMENT '巷/门牌号',
    address_main VARCHAR(500) NOT NULL DEFAULT '' COMMENT '地址（不含巷/门牌）',
    latitude DECIMAL(10, 7) DEFAULT NULL,
    longitude DECIMAL(10, 7) DEFAULT NULL,
    route_primary VARCHAR(120) NOT NULL DEFAULT '' COMMENT '主路线（自编）',
    route_secondary VARCHAR(120) NOT NULL DEFAULT '' COMMENT '副路线（自编）',
    community_name_en VARCHAR(160) NOT NULL DEFAULT '',
    community_name_th VARCHAR(160) NOT NULL DEFAULT '',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_delivery_code (consigning_client_id, customer_code),
    KEY idx_dispatch_delivery_consigning (consigning_client_id, status),
    CONSTRAINT fk_dispatch_delivery_consigning FOREIGN KEY (consigning_client_id) REFERENCES dispatch_consigning_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_waybills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consigning_client_id INT UNSIGNED NOT NULL,
    original_tracking_no VARCHAR(120) NOT NULL COMMENT '原始单号/面单号',
    delivery_customer_code VARCHAR(60) NOT NULL DEFAULT '' COMMENT '可为空',
    delivery_customer_id INT UNSIGNED DEFAULT NULL,
    weight_kg DECIMAL(12, 4) NOT NULL DEFAULT 0,
    volume_m3 DECIMAL(14, 6) NOT NULL DEFAULT 0,
    quantity DECIMAL(12, 4) NOT NULL DEFAULT 1,
    inbound_batch VARCHAR(100) NOT NULL DEFAULT '' COMMENT '入库批次',
    source ENUM('import', 'api') NOT NULL DEFAULT 'import',
    match_status VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT 'matched|no_recipient_code|recipient_not_found|pending',
    raw_payload JSON DEFAULT NULL COMMENT 'API 原始报文备查',
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_waybill_dedupe (consigning_client_id, original_tracking_no, inbound_batch),
    KEY idx_dispatch_waybill_consigning (consigning_client_id, created_at),
    KEY idx_dispatch_waybill_delivery (delivery_customer_id),
    KEY idx_dispatch_waybill_match (match_status),
    CONSTRAINT fk_dispatch_waybill_consigning FOREIGN KEY (consigning_client_id) REFERENCES dispatch_consigning_clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_dispatch_waybill_delivery FOREIGN KEY (delivery_customer_id) REFERENCES dispatch_delivery_customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_dispatch_waybill_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
