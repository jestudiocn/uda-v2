<?php
/** @var bool $schemaReady */
/** @var array $rows */
/** @var array $locationOptions */
/** @var array $reasonOptions */
/** @var array $reasonMap */
/** @var array $handleMethodOptions */
/** @var string $message */
/** @var string $error */
/** @var int $page */
/** @var int $total */
/** @var int $totalPages */
if (!function_exists('uda_issue_status_display')) {
    function uda_issue_status_display(string $db): string
    {
        $db = trim($db);
        if ($db === '处理中') {
            return t('uda.issues.status.in_progress', '处理中');
        }
        if ($db === '已处理') {
            return t('uda.issues.status.done', '已处理');
        }
        return t('uda.issues.status.pending', '未处理');
    }
}
$qTrack = (string)($_GET['q_track'] ?? '');
$qLocationId = (int)($_GET['q_location_id'] ?? 0);
$qReasonSelect = (string)($_GET['q_reason_select'] ?? '');
$qReasonText = (string)($_GET['q_reason_text'] ?? '');
$qProcessed = (string)($_GET['q_processed'] ?? '');
$qFrom = (string)($_GET['q_from'] ?? '');
$qTo = (string)($_GET['q_to'] ?? '');
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.issues_list.title', 'UDA快件 / 问题订单 / 问题订单列表')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.issues_list.subtitle', '对应 V1 列表查询，列表风格比照派送订单查询。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.issues_list.schema', '问题订单相关表不存在，无法查询。')); ?></div>
    <?php return; ?>
<?php endif; ?>
<?php if (($message ?? '') !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars((string)$message); ?></div><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars((string)$error); ?></div><?php endif; ?>

<style>
.issue-status-pending { color:#dc2626; font-weight:700; }
.issue-status-progress { color:#c2410c; font-weight:700; }
.issue-status-done { color:#15803d; font-weight:700; }
</style>

<div class="card">
    <form method="get" style="display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:10px;align-items:end;">
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.track', '面单号')); ?></label><input name="q_track" value="<?php echo htmlspecialchars($qTrack); ?>"></div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.location', '地点')); ?></label>
            <select name="q_location_id">
                <option value=""><?php echo htmlspecialchars(t('uda.common.all_locations', '全部地点')); ?></option>
                <?php foreach ($locationOptions as $loc): ?>
                    <?php $id = (int)($loc['id'] ?? 0); ?>
                    <option value="<?php echo $id; ?>" <?php echo $qLocationId === $id ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($loc['location_name'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.reason_select', '问题原因（下拉）')); ?></label>
            <select name="q_reason_select" id="q_reason_select">
                <option value=""><?php echo htmlspecialchars(t('uda.common.select_blank', '不选')); ?></option>
                <?php foreach (($reasonOptions ?? []) as $opt): ?>
                    <option value="<?php echo htmlspecialchars((string)$opt); ?>" <?php echo $qReasonSelect === (string)$opt ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.reason_text', '问题原因（可手输）')); ?></label><input name="q_reason_text" id="q_reason_text" value="<?php echo htmlspecialchars($qReasonText); ?>" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_blank', '可留空')); ?>"></div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.processed', '是否已处理')); ?></label>
            <select name="q_processed">
                <option value=""><?php echo htmlspecialchars(t('uda.common.all', '全部')); ?></option>
                <option value="未处理" <?php echo $qProcessed === '未处理' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('uda.issues.status.pending', '未处理')); ?></option>
                <option value="处理中" <?php echo $qProcessed === '处理中' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('uda.issues.status.in_progress', '处理中')); ?></option>
                <option value="已处理" <?php echo $qProcessed === '已处理' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('uda.issues.status.done', '已处理')); ?></option>
            </select>
        </div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.date_from', '创建日期（起）')); ?></label><input type="date" name="q_from" value="<?php echo htmlspecialchars($qFrom); ?>"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.date_to', '创建日期（止）')); ?></label><input type="date" name="q_to" value="<?php echo htmlspecialchars($qTo); ?>"></div>
        <div class="inline-actions"><button type="submit"><?php echo htmlspecialchars(t('uda.common.query', '查询')); ?></button><a class="btn" href="/uda/issues/list"><?php echo htmlspecialchars(t('uda.common.reset', '重置')); ?></a></div>
    </form>
</div>

<div class="card">
    <?php if ($total > 0): ?><div class="muted" style="margin-bottom:8px;"><?php echo htmlspecialchars(sprintf(t('uda.pagination.summary', '共 %d 条，第 %d / %d 页'), (int)$total, (int)$page, (int)$totalPages)); ?></div><?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(t('uda.page.issues_list.track', '面单号')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_list.location', '地点')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_list.col_reason', '问题原因')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_list.col_handle', '处理方式')); ?></th><th><?php echo htmlspecialchars(t('uda.common.status', '状态')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_list.col_created', '创建日期')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_list.col_processed_at', '处理日期')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_list.col_remark', '备注说明')); ?></th><th><?php echo htmlspecialchars(t('uda.common.handle', '处理')); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="muted"><?php echo htmlspecialchars(t('uda.common.no_data', '暂无数据')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $statusText = trim((string)($r['process_status_text'] ?? '未处理'));
                    if (!in_array($statusText, ['未处理', '处理中', '已处理'], true)) {
                        $statusText = '未处理';
                    }
                    $statusCls = $statusText === '已处理' ? 'issue-status-done' : ($statusText === '处理中' ? 'issue-status-progress' : 'issue-status-pending');
                    $detailPayload = [
                        'id' => (int)($r['id'] ?? 0),
                        'tracking_no' => (string)($r['tracking_no'] ?? ''),
                        'location_name' => (string)($r['location_name'] ?? ''),
                        'problem_reason' => (string)($r['problem_reason'] ?? ''),
                        'handle_method' => (string)($r['handle_method'] ?? ''),
                        'process_status_text' => $statusText,
                        'remark' => (string)($r['remark'] ?? ''),
                    ];
                    ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['tracking_no'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['location_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['problem_reason'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['handle_method'] ?? '')); ?></td>
                        <td><span class="<?php echo htmlspecialchars($statusCls); ?>"><?php echo htmlspecialchars(uda_issue_status_display($statusText)); ?></span></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['created_at'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['processed_at'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['remark'] ?? '')); ?></td>
                        <td>
                            <button type="button" class="btn issue-handle-btn" data-detail="<?php echo htmlspecialchars(json_encode($detailPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" style="padding:4px 8px;min-height:auto;"><?php echo htmlspecialchars(t('uda.common.handle', '处理')); ?></button>
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
            <?php if ($page > 1): $prev = $base; $prev['page']=(string)($page-1); ?><a class="btn" href="/uda/issues/list?<?php echo htmlspecialchars(http_build_query($prev)); ?>"><?php echo htmlspecialchars(t('uda.common.prev', '上一页')); ?></a><?php endif; ?>
            <?php if ($page < $totalPages): $next = $base; $next['page']=(string)($page+1); ?><a class="btn" href="/uda/issues/list?<?php echo htmlspecialchars(http_build_query($next)); ?>"><?php echo htmlspecialchars(t('uda.common.next', '下一页')); ?></a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="issueHandleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:900px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="issueHandleCloseX" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;">×</button>
        <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('uda.page.issues_list.modal_title', '问题订单处理')); ?></h3>
        <form method="post" class="form-grid" style="grid-template-columns:160px 1fr;gap:10px;">
            <input type="hidden" name="issue_handle_submit" value="1">
            <input type="hidden" name="id" id="ih_id" value="">
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.track', '面单号')); ?></label><div id="ih_tracking_no" style="padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.location', '地点')); ?></label><div id="ih_location_name" style="padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.col_reason', '问题原因')); ?></label><div id="ih_problem_reason" style="padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;"><?php echo htmlspecialchars(t('uda.common.dash', '—')); ?></div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.handle_select', '处理方式（下拉）')); ?></label>
            <select name="handle_method_select" id="ih_handle_method_select">
                <option value=""><?php echo htmlspecialchars(t('uda.page.issues_list.please_select', '请选择')); ?></option>
                <?php foreach (($handleMethodOptions ?? []) as $opt): ?>
                    <option value="<?php echo htmlspecialchars((string)$opt); ?>"><?php echo htmlspecialchars((string)$opt); ?></option>
                <?php endforeach; ?>
            </select>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.handle_text', '处理方式（可手输）')); ?></label><input name="handle_method_text" id="ih_handle_method_text" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_blank', '可留空')); ?>">
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.processed', '是否已处理')); ?></label>
            <select name="process_status" id="ih_process_status">
                <option value="未处理"><?php echo htmlspecialchars(t('uda.issues.status.pending', '未处理')); ?></option>
                <option value="处理中"><?php echo htmlspecialchars(t('uda.issues.status.in_progress', '处理中')); ?></option>
                <option value="已处理"><?php echo htmlspecialchars(t('uda.issues.status.done', '已处理')); ?></option>
            </select>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.col_remark', '备注说明')); ?></label><textarea name="remark" id="ih_remark" rows="4" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_modify', '可修改')); ?>"></textarea>
            <div class="form-full inline-actions"><button type="submit"><?php echo htmlspecialchars(t('uda.common.save_handle', '保存处理')); ?></button></div>
        </form>
    </div>
</div>

<script>window.__udaIssuesListI18n=<?php echo json_encode([
    'reasonBlank' => t('uda.common.select_blank', '不选'),
    'dash' => t('uda.common.dash', '—'),
], JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var reasonsByLocation = <?php echo json_encode($reasonMap ?? [], JSON_UNESCAPED_UNICODE); ?>;
    var qLocationSelect = document.querySelector('select[name="q_location_id"]');
    var reasonSelect = document.getElementById('q_reason_select');
    var reasonText = document.getElementById('q_reason_text');
    var initialReasonSelect = String(reasonSelect ? (reasonSelect.value || '') : '');
    var I = window.__udaIssuesListI18n || {};
    function refillReasonSelectByLocation() {
        if (!reasonSelect) return;
        var lid = String(qLocationSelect ? (qLocationSelect.value || '') : '');
        var opts = lid !== '' ? (reasonsByLocation[lid] || []) : <?php echo json_encode(array_values($reasonOptions ?? []), JSON_UNESCAPED_UNICODE); ?>;
        reasonSelect.innerHTML = '';
        var blankOpt = document.createElement('option');
        blankOpt.value = '';
        blankOpt.textContent = I.reasonBlank || '';
        reasonSelect.appendChild(blankOpt);
        opts.forEach(function (txt) {
            var safe = String(txt || '');
            if (safe === '') return;
            var op = document.createElement('option');
            op.value = safe;
            op.textContent = safe;
            reasonSelect.appendChild(op);
        });
        if (initialReasonSelect !== '') {
            var has = Array.prototype.some.call(reasonSelect.options, function (o) { return String(o.value) === initialReasonSelect; });
            if (has) reasonSelect.value = initialReasonSelect;
        }
    }
    refillReasonSelectByLocation();
    if (qLocationSelect) {
        qLocationSelect.addEventListener('change', function () {
            initialReasonSelect = '';
            refillReasonSelectByLocation();
        });
    }
    if (reasonSelect && reasonText) {
        reasonSelect.addEventListener('change', function () {
            if (String(this.value || '').trim() !== '') reasonText.value = this.value;
        });
    }
    var handleMethodSelect = document.getElementById('ih_handle_method_select');
    var handleMethodText = document.getElementById('ih_handle_method_text');
    if (handleMethodSelect && handleMethodText) {
        handleMethodSelect.addEventListener('change', function () {
            if (String(this.value || '').trim() !== '') handleMethodText.value = this.value;
        });
    }

    var modal = document.getElementById('issueHandleModal');
    var closeX = document.getElementById('issueHandleCloseX');
    function closeModal() { if (modal) modal.style.display = 'none'; }

    document.querySelectorAll('.issue-handle-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var payload = {};
            try { payload = JSON.parse(btn.getAttribute('data-detail') || '{}'); } catch (e) {}
            document.getElementById('ih_id').value = String(payload.id || '');
            var dsh = I.dash || '—';
            document.getElementById('ih_tracking_no').textContent = String(payload.tracking_no || dsh);
            document.getElementById('ih_location_name').textContent = String(payload.location_name || dsh);
            document.getElementById('ih_problem_reason').textContent = String(payload.problem_reason || dsh);
            var hmSelect = document.getElementById('ih_handle_method_select');
            var hmText = document.getElementById('ih_handle_method_text');
            var hmValue = String(payload.handle_method || '');
            if (hmSelect) {
                var hasOption = Array.prototype.some.call(hmSelect.options, function (o) { return String(o.value) === hmValue; });
                hmSelect.value = hasOption ? hmValue : '';
            }
            if (hmText) hmText.value = hmValue;
            document.getElementById('ih_process_status').value = String(payload.process_status_text || '未处理');
            document.getElementById('ih_remark').value = String(payload.remark || '');
            if (modal) modal.style.display = 'flex';
        });
    });
    if (closeX) closeX.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
});
</script>
