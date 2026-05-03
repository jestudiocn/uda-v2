<?php
/** @var bool $schemaReady */
/** @var array<int,array<string,mixed>> $rows */
/** @var array<int,array<string,mixed>> $detailBundles */
/** @var array<string,mixed>|null $manifestRow */
/** @var array<string,mixed>|null $manifestWaybillLookup */
/** @var string $message */
/** @var string $error */
/** @var int $page */
/** @var int $total */
/** @var int $totalPages */
/** @var int $viewManifestId */
$manifestWaybillLookup = $manifestWaybillLookup ?? null;
$qDateNo = htmlspecialchars((string)($_GET['q_date_no'] ?? $_GET['q_manifest_code'] ?? $_GET['q_batch_code'] ?? ''));
$qBillNo = htmlspecialchars((string)($_GET['q_bill_no'] ?? ''));
$qTrackingNo = htmlspecialchars(trim((string)($_GET['q_tracking_no'] ?? '')));
$qFrom = htmlspecialchars((string)($_GET['q_from'] ?? ''));
$qTo = htmlspecialchars((string)($_GET['q_to'] ?? ''));
$manifestHitJson = $manifestWaybillLookup !== null
    ? json_encode($manifestWaybillLookup, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
    : 'null';
?>
<style>
    .ubm-modal-close-x {
        position:absolute; top:10px; right:12px; border:none; background:transparent;
        font-size:26px; line-height:1; color:#64748b; cursor:pointer; padding:0 4px;
    }
    .ubm-modal-close-x:hover { color:#0f172a; }
    .ubm-detail-grid { display:grid; grid-template-columns:170px 1fr; gap:10px 14px; }
    .ubm-detail-grid label { color:#475569; font-weight:600; }
    .ubm-detail-val {
        min-height:34px; padding:6px 10px; border-radius:8px;
        background:#f8fafc; border:1px solid #e2e8f0; color:#0f172a;
        display:flex; align-items:center;
    }
    .ubm-line-combo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .ubm-line-chip {
        display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px;
        border:1px solid #cbd5e1; background:#fff; color:#0f172a; font-size:13px;
    }
    .ubm-line-chip .k { color:#64748b; font-weight:600; }
    .ubm-line-chip .v { color:#0f172a; }
    .ubm-op-btn { padding:4px 10px; min-height:auto; font-size:13px; margin-right:4px; }
    .ubm-manifest-meta { margin-bottom:8px; font-size:16px; line-height:1.65; color:#0f172a; font-weight:700; }
</style>

<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.batches_list.title', 'UDA快件 / 仓内操作 / 集包列表')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.batches_list.subtitle', '日期号与集包、面单查询；按面单号搜索时列表仅显示包含该单的日期号，并弹出所在集包位置。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.batches_list.schema', '集包数据表尚未就绪，请先执行 <code>036_uda_express_batches.sql</code>、<code>037_uda_manifest_uniques.sql</code>、<code>038_uda_manifest_date_no_and_bill_no.sql</code>。')); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if (($message ?? '') !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars((string)$message); ?></div><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars((string)$error); ?></div><?php endif; ?>

<?php if ($viewManifestId > 0 && is_array($manifestRow)): ?>
    <div class="card">
        <h3 style="margin:0 0 8px 0;font-size:16px;"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?> <?php echo htmlspecialchars((string)($manifestRow['date_no'] ?? $manifestRow['batch_code'] ?? '')); ?></h3>
        <div class="ubm-manifest-meta">
            <?php echo htmlspecialchars(t('uda.page.batches_list.label_bill', '提单号：')); ?><?php echo htmlspecialchars((string)($manifestRow['bill_no'] ?? '')); ?>
            ·
            <?php echo htmlspecialchars(t('uda.page.batches_list.label_status', '状态：')); ?><?php echo ($manifestRow['status'] ?? '') === 'completed' ? htmlspecialchars(t('uda.batches.status.completed', '已完成')) : htmlspecialchars(t('uda.batches.status.in_progress', '进行中')); ?>
            · <?php echo htmlspecialchars(t('uda.page.batches_list.label_created', '创建')); ?> <?php echo htmlspecialchars((string)($manifestRow['created_at'] ?? '')); ?>
            <?php if (($manifestRow['completed_at'] ?? '') !== '' && ($manifestRow['completed_at'] ?? null) !== null): ?>
                · <?php echo htmlspecialchars(t('uda.page.batches_list.label_ended', '结束')); ?> <?php echo htmlspecialchars((string)$manifestRow['completed_at']); ?>
            <?php endif; ?>
            · <?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_bundles_n', '集包 %d 个'), (int)($manifestRow['bundle_count'] ?? 0))); ?>
            · <?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_weight', '总重 %s kg'), number_format((float)($manifestRow['total_weight'] ?? 0), 3, '.', ''))); ?>
            · <?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_volume', '总立方 %s m³'), number_format((float)($manifestRow['total_volume'] ?? 0), 6, '.', ''))); ?>
        </div>
        <?php foreach ($detailBundles as $b): ?>
            <?php
            $seq = (int)($b['bundle_seq'] ?? 0);
            $label = str_pad((string)max(1, $seq), 3, '0', STR_PAD_LEFT);
            $wbs = $b['waybills'] ?? [];
            ?>
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid #e2e8f0;">
                <strong><?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_bundle_seq', '集包 %s'), $label)); ?></strong>
                <span class="muted"> · <?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_weight_short', '重 %s kg'), number_format((float)($b['weight_kg'] ?? 0), 2, '.', ''))); ?></span>
                <span class="muted"> · <?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_vol_short', '体积 %s m³'), number_format((float)($b['volume_m3'] ?? 0), 6, '.', ''))); ?></span>
                <span class="muted"> · <?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_dim', '尺寸 %s×%s×%s cm'), number_format((float)($b['length_cm'] ?? 0), 2, '.', ''), number_format((float)($b['width_cm'] ?? 0), 2, '.', ''), number_format((float)($b['height_cm'] ?? 0), 2, '.', ''))); ?></span>
                <div style="margin-top:6px;font-size:13px;"><?php echo htmlspecialchars(sprintf(t('uda.page.batches_list.label_waybills_n', '面单（%d）'), count($wbs))); ?>：<?php echo htmlspecialchars(implode('、', $wbs)); ?></div>
            </div>
        <?php endforeach; ?>
        <div class="inline-actions" style="margin-top:14px;">
            <a class="btn" href="/uda/batches/list"><?php echo htmlspecialchars(t('uda.page.batches_list.back_list', '返回列表')); ?></a>
            <a class="btn" href="/uda/batches/create"><?php echo htmlspecialchars(t('uda.page.batches_list.create_batch', '集包录入')); ?></a>
            <a class="btn" href="/uda/batches/edit?manifest_id=<?php echo (int)($manifestRow['id'] ?? 0); ?>"><?php echo htmlspecialchars(t('uda.page.batches_list.edit_this', '修改本日期号')); ?></a>
            <a class="btn" href="/uda/batches/packing-list-export?manifest_id=<?php echo (int)($manifestRow['id'] ?? 0); ?>"><?php echo htmlspecialchars(t('uda.page.batches_list.export_excel', '导出装箱单（Excel）')); ?></a>
        </div>
    </div>
<?php endif; ?>

<?php if ($viewManifestId === 0 || !is_array($manifestRow)): ?>
<div class="card">
    <form method="get" style="display:grid;grid-template-columns:repeat(2,minmax(200px,1fr));gap:10px;align-items:end;">
        <div><label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?></label><input name="q_date_no" value="<?php echo $qDateNo; ?>" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_fuzzy_match', '模糊匹配')); ?>"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.bill_no', '提单号')); ?></label><input name="q_bill_no" value="<?php echo $qBillNo; ?>" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_fuzzy_match', '模糊匹配')); ?>"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.tracking', '面单号')); ?></label><input name="q_tracking_no" value="<?php echo $qTrackingNo; ?>" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_exact_track', '精确匹配（规范化后）')); ?>" autocomplete="off"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.date_from', '创建日期（起）')); ?></label><input type="date" name="q_from" value="<?php echo $qFrom; ?>"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.date_to', '创建日期（止）')); ?></label><input type="date" name="q_to" value="<?php echo $qTo; ?>"></div>
        <div class="inline-actions" style="grid-column:1/-1;">
            <button type="submit"><?php echo htmlspecialchars(t('uda.common.query', '查询')); ?></button>
            <a class="btn" href="/uda/batches/list"><?php echo htmlspecialchars(t('uda.common.reset', '重置')); ?></a>
            <a class="btn" href="/uda/batches/create"><?php echo htmlspecialchars(t('uda.page.batches_list.create_batch', '集包录入')); ?></a>
        </div>
    </form>
    <?php if ($qTrackingNo !== '' && !$manifestWaybillLookup): ?>
        <div class="muted" style="margin-top:10px;"><?php echo htmlspecialchars(t('uda.page.batches_list.not_found_tracking', '未在系统中找到该面单号。')); ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($total > 0): ?><div class="muted" style="margin-bottom:8px;"><?php echo htmlspecialchars(sprintf(t('uda.pagination.summary', '共 %d 条，第 %d / %d 页'), (int)$total, (int)$page, (int)$totalPages)); ?></div><?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.bill_no', '提单号')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.common.status', '状态')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.batches_list.col_bundles', '集包数')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.batches_list.col_weight', '总重量(kg)')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.batches_list.col_volume', '总立方(m³)')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.batches_list.col_created_at', '创建时间')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.batches_list.col_creator', '录入人')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.common.actions', '操作')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="muted"><?php echo htmlspecialchars(t('uda.common.no_data', '暂无数据')); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $bid = (int)($r['id'] ?? 0); ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($r['date_no'] ?? $r['batch_code'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['bill_no'] ?? '')); ?></td>
                            <td><?php echo ($r['status'] ?? '') === 'completed' ? htmlspecialchars(t('uda.batches.status.completed', '已完成')) : htmlspecialchars(t('uda.batches.status.in_progress', '进行中')); ?></td>
                            <td><?php echo (int)($r['bundle_count'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float)($r['total_weight'] ?? 0), 3, '.', '')); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float)($r['total_volume'] ?? 0), 6, '.', '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['created_by_name'] ?? '')); ?></td>
                            <td style="white-space:nowrap;">
                                <a class="btn ubm-op-btn" href="/uda/batches/list?manifest_id=<?php echo $bid; ?>"><?php echo htmlspecialchars(t('uda.common.view', '查')); ?></a>
                                <a class="btn ubm-op-btn" href="/uda/batches/edit?manifest_id=<?php echo $bid; ?>"><?php echo htmlspecialchars(t('uda.common.edit', '改')); ?></a>
                                <form method="post" style="display:inline;" action="/uda/batches/list" onsubmit="return ubmConfirmDelete(this);">
                                    <input type="hidden" name="action" value="delete_batch">
                                    <input type="hidden" name="manifest_id" value="<?php echo $bid; ?>">
                                    <button type="submit" class="btn ubm-op-btn" style="background:#b91c1c;color:#fff;"><?php echo htmlspecialchars(t('uda.common.delete', '删')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <?php $base = $_GET; unset($base['batch_id'], $base['manifest_id']); ?>
        <div class="inline-actions" style="margin-top:12px;">
            <?php if ($page > 1): $prev = $base; $prev['page'] = (string)($page - 1); ?>
                <a class="btn" href="/uda/batches/list?<?php echo htmlspecialchars(http_build_query($prev)); ?>"><?php echo htmlspecialchars(t('uda.common.prev', '上一页')); ?></a>
            <?php endif; ?>
            <?php if ($page < $totalPages): $next = $base; $next['page'] = (string)($page + 1); ?>
                <a class="btn" href="/uda/batches/list?<?php echo htmlspecialchars(http_build_query($next)); ?>"><?php echo htmlspecialchars(t('uda.common.next', '下一页')); ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div id="ubmLookupModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:900px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" class="ubm-modal-close-x" id="ubmLookupCloseX" aria-label="<?php echo htmlspecialchars(t('uda.common.close', '关闭')); ?>">×</button>
        <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('uda.batches.lookup_title', '面单位置')); ?></h3>
        <div class="ubm-detail-grid">
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.track', '面单号')); ?></label><div id="ubm_l_tracking" class="ubm-detail-val"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></div>
            <label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?> / <?php echo htmlspecialchars(t('uda.common.status', '状态')); ?></label>
            <div id="ubm_l_batchline" class="ubm-detail-val ubm-line-combo">
                <span class="ubm-line-chip"><span class="k"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?></span><span class="v" id="ubm_l_batch_code"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></span></span>
                <span class="ubm-line-chip"><span class="k"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.bill_no', '提单号')); ?></span><span class="v" id="ubm_l_bill_no"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></span></span>
                <span class="ubm-line-chip"><span class="k"><?php echo htmlspecialchars(t('uda.common.status', '状态')); ?></span><span class="v" id="ubm_l_batch_status"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></span></span>
            </div>
            <label><?php echo htmlspecialchars(t('uda.batches.bundle_prefix', '集包')); ?></label><div id="ubm_l_bundle" class="ubm-detail-val"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></div>
            <label><?php echo htmlspecialchars(t('uda.common.actions', '操作')); ?></label>
            <div class="ubm-detail-val" style="gap:8px;">
                <a class="btn ubm-op-btn" id="ubm_l_link_view" href="#"><?php echo htmlspecialchars(t('uda.common.view', '查')); ?></a>
                <a class="btn ubm-op-btn" id="ubm_l_link_edit" href="#"><?php echo htmlspecialchars(t('uda.common.edit', '改')); ?></a>
            </div>
        </div>
    </div>
</div>

<script>window.__udaBatchesListI18n=<?php echo json_encode([
    'confirmDelete1' => t('uda.batches.confirm_delete_1', '确定删除该日期号及下属全部集包、面单？'),
    'confirmDelete2' => t('uda.batches.confirm_delete_2', '再次确认：删除后不可恢复。'),
    'dash' => t('uda.common.dash', '—'),
    'bundlePrefix' => t('uda.batches.bundle_prefix', '集包'),
    'statusCompleted' => t('uda.batches.status.completed', '已完成'),
    'statusProgress' => t('uda.batches.status.in_progress', '进行中'),
], JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
function ubmConfirmDelete(form) {
    var I = window.__udaBatchesListI18n || {};
    if (!confirm(I.confirmDelete1 || '')) return false;
    if (!confirm(I.confirmDelete2 || '')) return false;
    return true;
}
(function () {
    var I = window.__udaBatchesListI18n || {};
    function txt(v) {
        var s = String(v || '').trim();
        return s === '' ? (I.dash || '—') : s;
    }
    var modal = document.getElementById('ubmLookupModal');
    var closeX = document.getElementById('ubmLookupCloseX');
    function closeLookup() {
        if (modal) modal.style.display = 'none';
    }
    if (closeX) closeX.addEventListener('click', closeLookup);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeLookup(); });

    function openLookup(hit) {
        if (!modal || !hit) return;
        document.getElementById('ubm_l_tracking').textContent = txt(hit.tracking_no);
        document.getElementById('ubm_l_batch_code').textContent = txt(hit.date_no || hit.manifest_code || hit.batch_code);
        document.getElementById('ubm_l_bill_no').textContent = txt(hit.bill_no);
        var st = String(hit.batch_status || '');
        document.getElementById('ubm_l_batch_status').textContent = st === 'completed' ? (I.statusCompleted || '') : (st === 'open' ? (I.statusProgress || '') : txt(st));
        document.getElementById('ubm_l_bundle').textContent = (I.bundlePrefix || '') + ' ' + txt(hit.bundle_label);
        var bid = parseInt(String(hit.manifest_id || hit.batch_id || '0'), 10) || 0;
        var v = document.getElementById('ubm_l_link_view');
        var e = document.getElementById('ubm_l_link_edit');
        if (v) v.href = '/uda/batches/list?manifest_id=' + bid;
        if (e) e.href = '/uda/batches/edit?manifest_id=' + bid;
        modal.style.display = 'flex';
    }

    var rawHit = <?php echo $manifestHitJson; ?>;
    try {
        if (rawHit && typeof rawHit === 'object' && rawHit.tracking_no) {
            openLookup(rawHit);
        }
    } catch (err) {}
})();
</script>
