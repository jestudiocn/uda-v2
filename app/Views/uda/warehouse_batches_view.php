<?php
/** @var bool $schemaReady */
/** @var array<string,mixed>|null $row */
/** @var list<string> $waybills */
/** @var string $error */
$row = (isset($warehouseRow) && is_array($warehouseRow)) ? $warehouseRow : ($row ?? null);
$waybills = (isset($warehouseWaybills) && is_array($warehouseWaybills)) ? $warehouseWaybills : ($waybills ?? []);
$udaCount = (is_array($row) && array_key_exists('uda_count', $row)) ? $row['uda_count'] : null;
$jdCount = (is_array($row) && array_key_exists('jd_count', $row)) ? $row['jd_count'] : null;
$totalCount = (is_array($row) && array_key_exists('total_count', $row)) ? $row['total_count'] : null;
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">UDA快件 / 批次操作 / 批次详情</h2>
    <div class="muted">查看批次全部数据与面单列表，并可下载此日期号文档。</div>
</div>

<?php if (!$schemaReady || !$row): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据不存在'); ?></div>
    <div class="card"><a class="btn" href="/uda/warehouse/bundles">返回批次列表</a></div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <div class="muted" style="margin-bottom:10px;">
        日期号：<strong><?php echo htmlspecialchars((string)($row['date_no'] ?? '')); ?></strong>
        · 提单号：<?php echo htmlspecialchars((string)($row['bill_no'] ?? '')); ?>
        · UDA件数：<?php echo $udaCount === null ? '-' : (int)$udaCount; ?>
        · JD件数：<?php echo $jdCount === null ? '-' : (int)$jdCount; ?>
        · 总件数：<?php echo $totalCount === null ? '-' : (int)$totalCount; ?>
        · 航班日期：<?php echo htmlspecialchars((string)($row['flight_date'] ?? '')); ?>
        · 清关完成提货日期：<?php echo htmlspecialchars((string)($row['customs_pickup_date'] ?? '')); ?>
    </div>
    <div class="inline-actions">
        <?php $viewId = (int)($row['id'] ?? ($_GET['id'] ?? 0)); ?>
        <a class="btn" href="/uda/warehouse/batch-export?id=<?php echo $viewId; ?>">下载此日期号文档</a>
        <a class="btn" href="/uda/warehouse/batch-edit?id=<?php echo $viewId; ?>">修改</a>
        <a class="btn" href="/uda/warehouse/bundles">返回列表</a>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;">面单号（<?php echo count($waybills); ?>）</h3>
    <?php if ($waybills === []): ?>
        <div class="muted">暂无面单</div>
    <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($waybills as $wb): ?>
                <span style="display:inline-block;border:1px solid #cbd5e1;background:#f8fafc;border-radius:999px;padding:4px 10px;font-size:13px;"><?php echo htmlspecialchars($wb); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

