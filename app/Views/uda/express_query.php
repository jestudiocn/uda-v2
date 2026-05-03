<?php
/** @var bool $schemaReady */
/** @var array $rows */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var int $totalPages */
/** @var string $message */
/** @var string $error */
$qTrack = (string)($_GET['q_track'] ?? '');
$qDateFrom = (string)($_GET['q_date_from'] ?? '');
$qDateTo = (string)($_GET['q_date_to'] ?? '');
$qForwarded = (string)($_GET['q_forwarded'] ?? '');
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.express_query.title', 'UDA快件 / 快件查询')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.express_query.subtitle', '对应 V1「记录列表」，列表风格比照派送订单查询。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.express_query.schema', '表 `express_uda` 不存在，无法查询。')); ?></div>
    <?php return; ?>
<?php endif; ?>
<?php if (($message ?? '') !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars((string)$message); ?></div><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars((string)$error); ?></div><?php endif; ?>

<div class="card">
    <form method="get" style="display:grid;grid-template-columns:minmax(280px,2.2fr) minmax(180px,1.1fr) minmax(180px,1.1fr) minmax(160px,.9fr) auto;gap:10px;align-items:end;">
        <div style="min-width:0;"><label><?php echo htmlspecialchars(t('uda.page.express_query.track', '快递单号')); ?></label><input style="width:100%;" name="q_track" value="<?php echo htmlspecialchars($qTrack); ?>"></div>
        <div style="min-width:0;"><label><?php echo htmlspecialchars(t('uda.page.express_query.date_from', '开始日期（录入日期）')); ?></label><input style="width:100%;" type="date" name="q_date_from" value="<?php echo htmlspecialchars($qDateFrom); ?>"></div>
        <div style="min-width:0;"><label><?php echo htmlspecialchars(t('uda.page.express_query.date_to', '结束日期（录入日期）')); ?></label><input style="width:100%;" type="date" name="q_date_to" value="<?php echo htmlspecialchars($qDateTo); ?>"></div>
        <div style="min-width:0;">
            <label><?php echo htmlspecialchars(t('uda.page.express_query.forwarded', '是否再发出')); ?></label>
            <select style="width:100%;" name="q_forwarded">
                <option value=""><?php echo htmlspecialchars(t('uda.common.all', '全部')); ?></option>
                <option value="0" <?php echo $qForwarded === '0' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('uda.common.no', '否')); ?></option>
                <option value="1" <?php echo $qForwarded === '1' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('uda.common.yes', '是')); ?></option>
            </select>
        </div>
        <div class="inline-actions" style="justify-content:flex-end;white-space:nowrap;"><button type="submit"><?php echo htmlspecialchars(t('uda.common.query', '查询')); ?></button><a class="btn" href="/uda/express/query"><?php echo htmlspecialchars(t('uda.common.reset', '重置')); ?></a></div>
    </form>
</div>

<div class="card">
    <?php if ($total > 0): ?><div class="muted" style="margin-bottom:8px;"><?php echo htmlspecialchars(sprintf(t('uda.pagination.summary', '共 %d 条，第 %d / %d 页'), (int)$total, (int)$page, (int)$totalPages)); ?></div><?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
            <tr>
                <th><?php echo htmlspecialchars(t('uda.page.express_query.col_track', '快递单号')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_receiver', '收件人')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_forwarded', '是否再发出')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_fwd_time', '再发出时间')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_fwd_track', '再发出单号')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_fwd_receiver', '再发出收件人')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_fwd_fee', '再发出费用')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_remark', '备注')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_creator', '录入人')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_query.col_created', '录入时间')); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="muted"><?php echo htmlspecialchars(t('uda.common.no_data', '暂无数据')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php $detail = [
                        'id' => (int)($r['id'] ?? 0),
                        'tracking_no' => (string)($r['tracking_no'] ?? ''),
                        'receiver_name' => (string)($r['receiver_name'] ?? ''),
                        'remark' => (string)($r['remark'] ?? ''),
                        'is_forwarded' => (int)($r['is_forwarded'] ?? 0),
                        'forward_time' => (string)($r['forward_time'] ?? ''),
                        'forward_tracking_no' => (string)($r['forward_tracking_no'] ?? ''),
                        'forward_receiver' => (string)($r['forward_receiver'] ?? ''),
                        'forward_fee' => (string)($r['forward_fee'] ?? ''),
                        'forward_remark' => (string)($r['forward_remark'] ?? ''),
                    ]; ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['tracking_no'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['receiver_name'] ?? '')); ?></td>
                        <td><?php echo (int)($r['is_forwarded'] ?? 0) === 1 ? htmlspecialchars(t('uda.common.yes', '是')) : htmlspecialchars(t('uda.common.no', '否')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['forward_time'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['forward_tracking_no'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['forward_receiver'] ?? '')); ?></td>
                        <td><?php echo ($r['forward_fee'] ?? null) !== null && (string)$r['forward_fee'] !== '' ? number_format((float)$r['forward_fee'], 2) : ''; ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['remark'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['created_by_name'] ?? '')); ?></td>
                        <td class="cell-tip" style="white-space:nowrap;">
                            <span><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></span>
                            <span style="display:inline-flex;gap:6px;vertical-align:middle;margin-left:8px;">
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="forward_row">
                                    <input type="hidden" name="id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                    <button type="submit" style="padding:2px 8px;min-height:auto;"><?php echo htmlspecialchars(t('uda.common.forward', '转')); ?></button>
                                </form>
                                <button type="button" class="btn express-edit-btn" data-detail="<?php echo htmlspecialchars(json_encode($detail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" style="padding:2px 8px;min-height:auto;"><?php echo htmlspecialchars(t('uda.common.edit', '改')); ?></button>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <?php $base = $_GET; ?>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <?php if ($page > 1): $prev = $base; $prev['page']=(string)($page-1); ?><a class="btn" href="/uda/express/query?<?php echo htmlspecialchars(http_build_query($prev)); ?>"><?php echo htmlspecialchars(t('uda.common.prev', '上一页')); ?></a><?php endif; ?>
            <?php if ($page < $totalPages): $next = $base; $next['page']=(string)($page+1); ?><a class="btn" href="/uda/express/query?<?php echo htmlspecialchars(http_build_query($next)); ?>"><?php echo htmlspecialchars(t('uda.common.next', '下一页')); ?></a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="expressEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:900px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="expressEditCloseX" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;">×</button>
        <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('uda.page.express_query.modal_title', '快件资料修改')); ?></h3>
        <form method="post" class="form-grid" style="grid-template-columns:160px 1fr;gap:10px;">
            <input type="hidden" name="action" value="edit_row">
            <input type="hidden" name="id" id="ee_id" value="">
            <label><?php echo htmlspecialchars(t('uda.page.express_query.track', '快递单号')); ?></label><input name="tracking_no" id="ee_tracking_no" required>
            <label><?php echo htmlspecialchars(t('uda.page.express_query.col_receiver', '收件人')); ?></label><input name="receiver_name" id="ee_receiver_name">
            <label><?php echo htmlspecialchars(t('uda.page.express_query.col_remark', '备注')); ?></label><textarea name="remark" id="ee_remark" rows="2"></textarea>
            <div class="form-full inline-actions"><button type="submit"><?php echo htmlspecialchars(t('uda.common.save_changes', '保存修改')); ?></button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('expressEditModal');
    var closeX = document.getElementById('expressEditCloseX');
    function closeModal() { if (modal) modal.style.display = 'none'; }
    document.querySelectorAll('.express-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var payload = {};
            try { payload = JSON.parse(btn.getAttribute('data-detail') || '{}'); } catch (e) {}
            document.getElementById('ee_id').value = String(payload.id || '');
            document.getElementById('ee_tracking_no').value = String(payload.tracking_no || '');
            document.getElementById('ee_receiver_name').value = String(payload.receiver_name || '');
            document.getElementById('ee_remark').value = String(payload.remark || '');
            if (modal) modal.style.display = 'flex';
        });
    });
    if (closeX) closeX.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
});
</script>
