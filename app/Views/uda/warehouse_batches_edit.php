<?php
/** @var bool $schemaReady */
/** @var array<string,mixed>|null $row */
/** @var list<string> $waybills */
/** @var string $message */
/** @var string $error */
$row = (isset($warehouseRow) && is_array($warehouseRow)) ? $warehouseRow : ($row ?? null);
$waybills = (isset($warehouseWaybills) && is_array($warehouseWaybills)) ? $warehouseWaybills : ($waybills ?? []);
$udaCount = (is_array($row) && array_key_exists('uda_count', $row)) ? $row['uda_count'] : null;
$jdCount = (is_array($row) && array_key_exists('jd_count', $row)) ? $row['jd_count'] : null;
$totalCount = (is_array($row) && array_key_exists('total_count', $row)) ? $row['total_count'] : null;
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">UDA快件 / 批次操作 / 批次修改</h2>
    <div class="muted">可修改提单号、UDA件数、JD件数、航班日期、清关完成提货日期；并可按面单号添加或删除。</div>
</div>

<?php if (!$schemaReady || !$row): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据不存在'); ?></div>
    <div class="card"><a class="btn" href="/uda/warehouse/bundles">返回批次列表</a></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div class="muted" style="margin-bottom:8px;">日期号：<strong><?php echo htmlspecialchars((string)($row['date_no'] ?? '')); ?></strong></div>
    <form method="post" class="form-grid" style="grid-template-columns:repeat(3,minmax(220px,1fr));gap:10px;">
        <input type="hidden" name="action" value="save_meta">
        <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>">
        <div><label>提单号</label><input name="bill_no" required maxlength="100" value="<?php echo htmlspecialchars((string)($row['bill_no'] ?? '')); ?>"></div>
        <div><label>UDA件数</label><input type="number" min="0" step="1" name="uda_count" value="<?php echo $udaCount === null ? '' : htmlspecialchars((string)$udaCount); ?>"></div>
        <div><label>JD件数</label><input type="number" min="0" step="1" name="jd_count" value="<?php echo $jdCount === null ? '' : htmlspecialchars((string)$jdCount); ?>"></div>
        <div><label>航班日期</label><input type="date" name="flight_date" value="<?php echo htmlspecialchars((string)($row['flight_date'] ?? '')); ?>"></div>
        <div><label>清关完成提货日期</label><input type="date" name="customs_pickup_date" value="<?php echo htmlspecialchars((string)($row['customs_pickup_date'] ?? '')); ?>"></div>
        <div><label>总件数</label><input type="text" value="<?php echo $totalCount === null ? '' : htmlspecialchars((string)$totalCount); ?>" disabled></div>
        <div class="form-full inline-actions"><button type="submit">保存主数据</button><a class="btn" href="/uda/warehouse/batch-view?id=<?php echo (int)($row['id'] ?? 0); ?>">查看详情</a></div>
    </form>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;">面单维护</h3>
    <form method="post" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;">
        <input type="hidden" name="action" value="add_or_remove_waybill">
        <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>">
        <div>
            <label>面单号</label>
            <input name="tracking_no" autocomplete="off" placeholder="输入面单号">
        </div>
        <button type="submit" name="mode" value="add">添加至此批次</button>
        <button type="submit" name="mode" value="remove" class="btn" style="background:#b91c1c;color:#fff;">从此批次删除</button>
    </form>
    <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">
        <?php if ($waybills === []): ?>
            <span class="muted">暂无面单</span>
        <?php else: ?>
            <?php foreach ($waybills as $wb): ?>
                <span style="display:inline-block;border:1px solid #cbd5e1;background:#f8fafc;border-radius:999px;padding:4px 10px;font-size:13px;"><?php echo htmlspecialchars($wb); ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

