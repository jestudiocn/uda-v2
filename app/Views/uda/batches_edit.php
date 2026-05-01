<?php
/** @var bool $schemaReady */
/** @var int $manifestId */
/** @var array<string,mixed>|null $manifestRow */
/** @var array<int,array<string,mixed>> $editBundles */
/** @var string $message */
/** @var string $error */
$manifestId = (int)($manifestId ?? $batchId ?? 0);
$manifestRow = $manifestRow ?? $batchRow ?? null;
$editBundles = $editBundles ?? [];
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">UDA快件 / 仓内操作 / 集包修改</h2>
    <div class="muted">可修改各集包重量与长宽高（厘米）；可在集包内删除或添加面单（全库面单号不可重复）。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">数据表未就绪，请先执行 <code>036_uda_express_batches.sql</code>、<code>037_uda_manifest_uniques.sql</code>、<code>038_uda_manifest_date_no_and_bill_no.sql</code>。</div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$manifestRow): ?>
    <div class="card"><a class="btn" href="/uda/batches/list">返回列表</a></div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <div style="margin-bottom:12px;font-size:16px;line-height:1.65;color:#0f172a;font-weight:700;">
        日期号 <strong><?php echo htmlspecialchars((string)($manifestRow['date_no'] ?? $manifestRow['batch_code'] ?? '')); ?></strong>
        · 提单号 <?php echo htmlspecialchars((string)($manifestRow['bill_no'] ?? '')); ?>
        · 状态 <?php echo ($manifestRow['status'] ?? '') === 'completed' ? '已完成' : '进行中'; ?>
        · ID <?php echo (int)($manifestRow['id'] ?? 0); ?>
    </div>
    <div class="inline-actions">
        <a class="btn" href="/uda/batches/list?manifest_id=<?php echo (int)($manifestRow['id'] ?? 0); ?>">查看明细</a>
        <a class="btn" href="/uda/batches/list">返回列表</a>
    </div>
</div>

<?php foreach ($editBundles as $b): ?>
    <?php
    $bundleId = (int)($b['id'] ?? 0);
    $seq = (int)($b['bundle_seq'] ?? 0);
    $label = str_pad((string)max(1, $seq), 3, '0', STR_PAD_LEFT);
    $rows = $b['waybill_rows'] ?? [];
    ?>
    <div class="card" style="margin-top:12px;">
        <h3 style="margin:0 0 12px 0;font-size:16px;">集包 <?php echo htmlspecialchars($label); ?></h3>
        <form method="post" class="form-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end;max-width:920px;">
            <input type="hidden" name="action" value="update_bundle">
            <input type="hidden" name="manifest_id" value="<?php echo (int)($manifestRow['id'] ?? 0); ?>">
            <input type="hidden" name="bundle_id" value="<?php echo $bundleId; ?>">
            <div>
                <label>重量（kg）</label>
                <input type="text" name="weight_kg" required value="<?php echo htmlspecialchars((string)($b['weight_kg'] ?? '')); ?>" style="width:100%;">
            </div>
            <div>
                <label>长（cm）</label>
                <input type="text" name="length_cm" required value="<?php echo htmlspecialchars((string)($b['length_cm'] ?? '')); ?>" style="width:100%;">
            </div>
            <div>
                <label>宽（cm）</label>
                <input type="text" name="width_cm" required value="<?php echo htmlspecialchars((string)($b['width_cm'] ?? '')); ?>" style="width:100%;">
            </div>
            <div>
                <label>高（cm）</label>
                <input type="text" name="height_cm" required value="<?php echo htmlspecialchars((string)($b['height_cm'] ?? '')); ?>" style="width:100%;">
            </div>
            <div class="inline-actions" style="grid-column:1/-1;">
                <button type="submit">保存本集包尺寸重量</button>
            </div>
        </form>

        <h4 style="margin:16px 0 8px 0;font-size:14px;">面单</h4>
        <div style="overflow:auto;">
            <table class="data-table">
                <thead><tr><th>面单号</th><th style="width:90px;">操作</th></tr></thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="2" class="muted">暂无面单</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $wr): ?>
                            <?php $wid = (int)($wr['id'] ?? 0); ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($wr['tracking_no'] ?? '')); ?></td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该面单？');">
                                        <input type="hidden" name="action" value="delete_waybill">
                                        <input type="hidden" name="manifest_id" value="<?php echo (int)($manifestRow['id'] ?? 0); ?>">
                                        <input type="hidden" name="waybill_id" value="<?php echo $wid; ?>">
                                        <button type="submit" class="btn" style="padding:2px 8px;min-height:auto;font-size:12px;">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form method="post" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
            <input type="hidden" name="action" value="add_waybill">
            <input type="hidden" name="manifest_id" value="<?php echo (int)($manifestRow['id'] ?? 0); ?>">
            <input type="hidden" name="bundle_id" value="<?php echo $bundleId; ?>">
            <div>
                <label>追加面单号</label>
                <input type="text" name="tracking_no" autocomplete="off" placeholder="规范化同录入页" style="min-width:220px;">
            </div>
            <button type="submit">添加到本集包</button>
        </form>
    </div>
<?php endforeach; ?>

<script>
(function () {
    var key = 'uda_batches_edit_scroll_y_' + <?php echo (int)($manifestRow['id'] ?? 0); ?>;
    try {
        var raw = sessionStorage.getItem(key);
        if (raw !== null) {
            var y = parseInt(raw, 10);
            if (!isNaN(y) && y >= 0) {
                window.scrollTo(0, y);
            }
            sessionStorage.removeItem(key);
        }
    } catch (e) {}

    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            try { sessionStorage.setItem(key, String(window.scrollY || 0)); } catch (e) {}
        });
    });
})();
</script>
