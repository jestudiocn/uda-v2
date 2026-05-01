<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
/** @var string $prefillDateNo */
$prefillDateNo = (string)($prefillDateNo ?? '');
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">UDA快件 / 批次操作 / 批次录入</h2>
    <div class="muted">第一区块先录入批次主数据；第二区块按 CSV 导入面单号并按日期号归属到批次。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">数据表未就绪，请执行 <code>039_uda_warehouse_batch_tables.sql</code>。</div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;">1) 批次主数据录入</h3>
    <form method="post" class="form-grid" style="grid-template-columns:repeat(3,minmax(220px,1fr));gap:12px;">
        <input type="hidden" name="action" value="create_batch">
        <div><label>日期号</label><input name="date_no" required maxlength="100" value="<?php echo htmlspecialchars($prefillDateNo); ?>"></div>
        <div><label>提单号</label><input name="bill_no" required maxlength="100"></div>
        <div><label>UDA件数</label><input type="number" min="0" step="1" name="uda_count" value=""></div>
        <div><label>JD件数</label><input type="number" min="0" step="1" name="jd_count" value=""></div>
        <div><label>航班日期</label><input type="date" name="flight_date"></div>
        <div><label>清关完成提货日期</label><input type="date" name="customs_pickup_date"></div>
        <div class="form-full inline-actions"><button type="submit">保存批次主数据</button></div>
    </form>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;">2) 面单号 CSV 导入</h3>
    <div class="muted" style="margin-bottom:8px;">
        CSV 表头必须包含 <code>面单号</code>、<code>日期号</code> 两列；导入时只写入日期号等于下方输入值的行。
        <a href="/uda/warehouse/import-template" style="margin-left:8px;">下载CSV模板（中文表头）</a>
    </div>
    <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:minmax(260px,1fr) minmax(260px,1fr) auto;gap:10px;align-items:end;">
        <input type="hidden" name="action" value="import_waybills">
        <div><label>日期号</label><input name="date_no" required maxlength="100" value="<?php echo htmlspecialchars($prefillDateNo); ?>"></div>
        <div><label>CSV 文件</label><input type="file" name="csv_file" accept=".csv,text/csv" required></div>
        <div class="inline-actions"><button type="submit">导入 CSV</button><a class="btn" href="/uda/warehouse/bundles">去批次列表</a></div>
    </form>
</div>

