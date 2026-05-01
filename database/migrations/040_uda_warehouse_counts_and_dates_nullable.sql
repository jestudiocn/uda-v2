-- UDA快件 / 仓内操作：允许 UDA件数 / JD件数 / 航班日期 / 清关完成提货日期为空

SET @db := DATABASE();

-- UDA件数、JD件数、总件数允许为 NULL
ALTER TABLE uda_warehouse_batches
    MODIFY uda_count INT UNSIGNED NULL DEFAULT NULL,
    MODIFY jd_count INT UNSIGNED NULL DEFAULT NULL,
    MODIFY total_count INT UNSIGNED NULL DEFAULT NULL,
    MODIFY flight_date DATE NULL DEFAULT NULL,
    MODIFY customs_pickup_date DATE NULL DEFAULT NULL;

-- 航班日期、清关完成提货日期本来就是 NULL，可再确认不做强制 change

