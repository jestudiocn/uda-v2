<?php
/** @var bool $schemaReady */
/** @var string $error */
/** @var string $message */
/** @var list<array<string,mixed>> $rows */
/** @var list<array<string,mixed>> $detailRows */
/** @var string $viewDocNo */
$schemaReady = $schemaReady ?? false;
$error = (string)($error ?? '');
$message = (string)($message ?? '');
$rows = $rows ?? [];
$detailRows = $detailRows ?? [];
$viewDocNo = (string)($viewDocNo ?? '');
?>
<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 派送单拣货表</h2>
    <div class="muted">仅在「正式派送单列表」中已点「生成路线分段」后，本页会出现对应派送单（不要求已指派司机）。订单在绑定派送单后仍为「已入库」，须在本表逐客户点「出库」后才变为「已出库」。全部出库后点「已完成」标记拣货结束（正式派送单列表仍保留）；指派司机可在正式列表与拣货并行操作。</div>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>派送单号</th><th>预计派送日期</th><th>拣货表明细</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="3" class="muted">暂无待处理拣货表</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php $docNo = (string)($r['delivery_doc_no'] ?? ''); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($docNo); ?></td>
                        <td><?php echo htmlspecialchars((string)($r['planned_delivery_date'] ?? '')); ?></td>
                        <td><a class="btn" href="/dispatch/ops/delivery-pick-sheets?delivery_doc_no=<?php echo urlencode($docNo); ?>">查看明细</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($viewDocNo !== '' && $detailRows !== []): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">拣货表明细：<?php echo htmlspecialchars($viewDocNo); ?></h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>段号</th><th>客户编码</th><th>微信/Line号</th><th>件数</th><th>主/副线路</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php $allOutbound = true; ?>
                <?php foreach ($detailRows as $d): ?>
                    <?php
                    $isOutbound = (int)($d['is_outbound'] ?? 0) === 1;
                    if (!$isOutbound) { $allOutbound = false; }
                    $code = (string)($d['customer_code'] ?? '');
                    ?>
                    <tr>
                        <td><?php echo (int)($d['segment_no'] ?? 1); ?></td>
                        <td><?php echo htmlspecialchars($code); ?></td>
                        <td><?php echo htmlspecialchars((string)($d['wx_or_line'] ?? '')); ?></td>
                        <td><?php echo (int)($d['piece_count'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string)($d['route_primary'] ?? '') . '/' . (string)($d['route_secondary'] ?? '')); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="pick_mark_outbound">
                                <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($viewDocNo); ?>">
                                <input type="hidden" name="customer_code" value="<?php echo htmlspecialchars($code); ?>">
                                <button type="submit" <?php echo $isOutbound ? 'disabled style="background:#94a3b8;cursor:not-allowed;"' : ''; ?>>出库</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($allOutbound): ?>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="action" value="pick_complete_doc">
            <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($viewDocNo); ?>">
            <button type="submit" style="background:#0f766e;">已完成</button>
        </form>
    <?php else: ?>
        <div class="muted" style="margin-top:12px;">请先完成所有客户出库后，再点击“已完成”。</div>
    <?php endif; ?>
</div>
<?php endif; ?>
