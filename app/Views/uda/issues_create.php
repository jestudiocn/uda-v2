<?php
/** @var bool $schemaReady */
/** @var array $locationOptions */
/** @var array $reasonMap */
/** @var array $reasonOptions */
/** @var string $message */
/** @var string $error */
/** @var bool $showAlertError */
$oldTrackingNo = (string)($_POST['tracking_no'] ?? '');
$oldLocationId = (int)($_POST['location_id'] ?? 0);
$oldReasonSelect = (string)($_POST['problem_reason_select'] ?? '');
$oldReasonText = (string)($_POST['problem_reason_text'] ?? '');
$oldRemark = (string)($_POST['remark'] ?? '');
$showSubmitErrorToast = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $error !== '';
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.issues_create.title', 'UDA快件 / 问题订单 / 问题订单录入')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.issues_create.subtitle', '对应 V1 问题订单录入。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.issues_create.schema', '问题订单相关表不存在，无法录入。')); ?></div>
    <?php return; ?>
<?php endif; ?>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="post" class="form-grid" style="grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px;">
        <input type="hidden" name="problem_create_submit" value="1">
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.track', '面单号')); ?></label><input type="text" name="tracking_no" id="issue_tracking_no" value="<?php echo htmlspecialchars($oldTrackingNo); ?>" required></div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.location', '地点')); ?></label>
            <select name="location_id" id="issue_location_id" required>
                <option value=""><?php echo htmlspecialchars(t('uda.common.select_location', '请选择地点')); ?></option>
                <?php foreach ($locationOptions as $loc): ?>
                    <?php $locId = (int)($loc['id'] ?? 0); ?>
                    <option value="<?php echo $locId; ?>" <?php echo $oldLocationId === $locId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($loc['location_name'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_list.reason_select', '问题原因（下拉）')); ?></label>
            <select name="problem_reason_select" id="issue_reason_select">
                <option value=""><?php echo htmlspecialchars(t('uda.common.select_blank', '不选')); ?></option>
                <?php foreach (($reasonOptions ?? []) as $opt): ?>
                    <option value="<?php echo htmlspecialchars((string)$opt); ?>" <?php echo $oldReasonSelect === (string)$opt ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$opt); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_list.reason_text', '问题原因（可手输）')); ?></label><input name="problem_reason_text" id="issue_reason_text" value="<?php echo htmlspecialchars($oldReasonText); ?>" placeholder="<?php echo htmlspecialchars(t('uda.common.placeholder_blank', '可留空')); ?>"></div>
        <div class="form-full"><label><?php echo htmlspecialchars(t('uda.page.issues_create.remark_label', '备注说明')); ?></label><textarea name="remark" rows="4" placeholder="<?php echo htmlspecialchars(t('uda.common.optional', '选填')); ?>"><?php echo htmlspecialchars($oldRemark); ?></textarea></div>
        <div class="form-full inline-actions"><button type="submit"><?php echo htmlspecialchars(t('uda.common.add', '新增')); ?></button></div>
    </form>
</div>

<div id="issueCreateToast" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.28);z-index:10060;align-items:center;justify-content:center;">
    <div style="min-width:220px;max-width:min(86vw,420px);background:#fff;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.22);padding:16px 18px;">
        <div id="issueCreateToastMsg" style="font-size:16px;font-weight:700;color:#111827;"><?php echo htmlspecialchars(t('uda.common.tip', '提示')); ?></div>
    </div>
</div>

<script>window.__udaIssueCreateI18n=<?php echo json_encode([
    'reasonBlank' => t('uda.common.select_blank', '不选'),
    'chooseLocationFirst' => t('uda.page.issues_create.choose_location_first', '请先选择地点'),
], JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var reasonsByLocation = <?php echo json_encode($reasonMap ?? [], JSON_UNESCAPED_UNICODE); ?>;
    var tracking = document.getElementById('issue_tracking_no');
    var locationSelect = document.getElementById('issue_location_id');
    var reasonSelect = document.getElementById('issue_reason_select');
    var reasonText = document.getElementById('issue_reason_text');
    var toast = document.getElementById('issueCreateToast');
    var toastMsg = document.getElementById('issueCreateToastMsg');
    var I = window.__udaIssueCreateI18n || {};
    var initialReasonSelect = String(reasonSelect ? (reasonSelect.value || '') : '');
    function showToast(msg, autoCloseMs) {
        if (!toast || !toastMsg) return;
        toastMsg.textContent = msg || '';
        toast.style.display = 'flex';
        if ((autoCloseMs || 0) > 0) {
            window.setTimeout(function () {
                if (toast) toast.style.display = 'none';
            }, autoCloseMs);
        }
    }
    function refillReasonSelectByLocation() {
        if (!reasonSelect) return;
        var lid = String(locationSelect ? (locationSelect.value || '') : '');
        var opts = reasonsByLocation[lid] || [];
        reasonSelect.innerHTML = '';
        var blankOpt = document.createElement('option');
        blankOpt.value = '';
        blankOpt.textContent = lid === '' ? (I.chooseLocationFirst || '') : (I.reasonBlank || '');
        reasonSelect.appendChild(blankOpt);
        opts.forEach(function (it) {
            var txt = String(it && it.reason_name ? it.reason_name : '');
            if (txt === '') return;
            var op = document.createElement('option');
            op.value = txt;
            op.textContent = txt;
            reasonSelect.appendChild(op);
        });
        if (initialReasonSelect !== '') {
            var has = Array.prototype.some.call(reasonSelect.options, function (o) { return String(o.value) === initialReasonSelect; });
            if (has) reasonSelect.value = initialReasonSelect;
        }
    }
    refillReasonSelectByLocation();
    if (locationSelect) {
        locationSelect.addEventListener('change', function () {
            initialReasonSelect = '';
            refillReasonSelectByLocation();
        });
    }
    if (tracking) {
        tracking.addEventListener('input', function () {
            this.value = String(this.value || '').toUpperCase().trim().replace(/@.*$/, '');
        });
    }
    if (reasonSelect && reasonText) {
        reasonSelect.addEventListener('change', function () {
            if (String(this.value || '').trim() !== '') reasonText.value = this.value;
        });
    }
    if (toast) {
        toast.addEventListener('click', function (e) {
            if (e.target === toast) toast.style.display = 'none';
        });
    }
    <?php if ($showSubmitErrorToast): ?>
    showToast(<?php echo json_encode((string)$error, JSON_UNESCAPED_UNICODE); ?>, 2800);
    <?php endif; ?>
});
</script>
