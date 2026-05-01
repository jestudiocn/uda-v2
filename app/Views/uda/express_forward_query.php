<?php
/** @var bool $schemaReady */
/** @var array $rows */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var int $totalPages */
$qReceiver = (string)($_GET['q_receiver'] ?? '');
$qPhone = (string)($_GET['q_phone'] ?? '');
$qAddress = (string)($_GET['q_address'] ?? '');
$qFrom = (string)($_GET['q_from'] ?? '');
$qTo = (string)($_GET['q_to'] ?? '');
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">UDA快件 / 快件收发 / 转发查询</h2>
    <div class="muted">数据来自「转发合包」确认后的合包明细；支持多条件模糊查询。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">
        转发数据表未就绪，请先执行
        <code>database/migrations/035_uda_saved_recipients_and_forward_packages.sql</code>
        （及已执行过的 <code>034_uda_express_forward_packages.sql</code>）。
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <form method="get" style="display:grid;grid-template-columns:minmax(140px,1fr) minmax(140px,1fr) minmax(200px,2fr) minmax(140px,1fr) minmax(140px,1fr) auto;gap:10px;align-items:end;">
        <div style="min-width:0;"><label>收件人</label><input style="width:100%;" name="q_receiver" value="<?php echo htmlspecialchars($qReceiver); ?>" placeholder="模糊"></div>
        <div style="min-width:0;"><label>电话</label><input style="width:100%;" name="q_phone" value="<?php echo htmlspecialchars($qPhone); ?>" placeholder="模糊"></div>
        <div style="min-width:0;"><label>地址</label><input style="width:100%;" name="q_address" value="<?php echo htmlspecialchars($qAddress); ?>" placeholder="模糊"></div>
        <div style="min-width:0;"><label>开始日期</label><input style="width:100%;" type="date" name="q_from" value="<?php echo htmlspecialchars($qFrom); ?>"></div>
        <div style="min-width:0;"><label>结束日期</label><input style="width:100%;" type="date" name="q_to" value="<?php echo htmlspecialchars($qTo); ?>"></div>
        <div class="inline-actions" style="white-space:nowrap;"><button type="submit">查询</button><a class="btn" href="/uda/express/forward-query">重置</a></div>
    </form>
</div>

<div class="card">
    <?php if ($total > 0): ?><div class="muted" style="margin-bottom:8px;">共 <?php echo (int)$total; ?> 条，第 <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?> 页</div><?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>面单号</th>
                    <th>收件人</th>
                    <th>电话</th>
                    <th>地址</th>
                    <th>费用</th>
                    <th>发送日期</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="muted">暂无数据</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['tracking_no'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['receiver_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['receiver_phone'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['receiver_address'] ?? '')); ?></td>
                        <td><?php echo number_format((float)($r['forward_fee'] ?? 0), 2, '.', ''); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['send_at'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <?php $base = $_GET; ?>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <?php if ($page > 1): $prev = $base; $prev['page'] = (string)($page - 1); ?><a class="btn" href="/uda/express/forward-query?<?php echo htmlspecialchars(http_build_query($prev)); ?>">上一页</a><?php endif; ?>
            <?php if ($page < $totalPages): $next = $base; $next['page'] = (string)($page + 1); ?><a class="btn" href="/uda/express/forward-query?<?php echo htmlspecialchars(http_build_query($next)); ?>">下一页</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
