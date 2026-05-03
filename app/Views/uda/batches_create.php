<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
/** @var array<string,mixed>|null $currentBatch */
/** @var array{total_weight:float,total_volume:float,bundle_count:int,next_seq:int,total_pieces:int} $totals */
$currentBatch = $currentBatch ?? null;
$totals = $totals ?? ['total_weight' => 0.0, 'total_volume' => 0.0, 'bundle_count' => 0, 'next_seq' => 1, 'total_pieces' => 0];
$nextLabel = str_pad((string)(int)($totals['next_seq'] ?? 1), 3, '0', STR_PAD_LEFT);
$batchId = $currentBatch ? (int)($currentBatch['id'] ?? 0) : 0;
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.batches_create.title', 'UDA快件 / 仓内操作 / 集包录入')); ?></h2>
    <div class="muted"><?php echo t('uda.page.batches_create.subtitle', '一个日期号下可录入多个集包；集包号按 001、002…自动递增；面单号用扫码枪在输入框连续回车采集；单包扫完后填写重量与长宽高（厘米）并提交集包。下方汇总当前日期号总重量与总立方（m³），每提交一个集包后刷新。全部集包做完后点「日期号完成」。'); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo t('uda.page.batches_create.schema', '集包数据表尚未创建，请先执行 <code>database/migrations/036_uda_express_batches.sql</code>、<code>037_uda_manifest_uniques.sql</code>、<code>038_uda_manifest_date_no_and_bill_no.sql</code>（日期号与面单号全库唯一）。'); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$currentBatch): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;"><?php echo htmlspecialchars(t('uda.page.batches_create.step1', '1. 设定日期号')); ?></h3>
    <form method="post" class="form-grid" style="grid-template-columns:minmax(280px,1fr) auto;gap:12px;align-items:end;">
        <input type="hidden" name="action" value="set_batch">
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.batches_create.label_date_no', '日期号')); ?></label>
            <input type="text" name="date_no" maxlength="100" required placeholder="<?php echo htmlspecialchars(t('uda.page.batches_create.ph_date_no', '输入后进入采集流程')); ?>" autocomplete="off">
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.batches_create.label_bill_no', '提单号')); ?></label>
            <input type="text" name="bill_no" maxlength="100" placeholder="<?php echo htmlspecialchars(t('uda.page.batches_create.ph_bill_no', '可填写对应提单号')); ?>" autocomplete="off">
        </div>
        <div class="inline-actions">
            <button type="submit"><?php echo htmlspecialchars(t('uda.page.batches_create.btn_start', '确定并开始')); ?></button>
        </div>
    </form>
    <div class="muted" style="margin-top:10px;"><?php echo t('uda.page.batches_create.date_rule_hint', '日期号全库唯一；若该号已有<strong>进行中</strong>记录，将进入继续录入；若已<strong>结束</strong>则须更换号码。'); ?></div>
</div>
<?php else: ?>
<div class="card" style="background:#eff6ff;border:1px solid #bfdbfe;">
    <div style="display:flex;flex-wrap:wrap;gap:16px 20px;align-items:center;color:#1d4ed8;font-weight:600;">
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_total_pieces', '本日期号总件数')); ?></span>：<?php echo (int)($totals['total_pieces']); ?></div>
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_current_date', '当前日期号')); ?></span>：<?php echo htmlspecialchars((string)($currentBatch['date_no'] ?? $currentBatch['batch_code'] ?? '')); ?></div>
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_bill', '提单号')); ?></span>：<?php echo htmlspecialchars((string)($currentBatch['bill_no'] ?? '')); ?></div>
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_next_bundle', '当前集包号')); ?></span>：<?php echo htmlspecialchars($nextLabel); ?><?php echo htmlspecialchars(t('uda.page.batches_create.stat_next_bundle_note', '（提交本包后自动递增）')); ?></div>
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_bundle_count', '已提交集包数')); ?></span>：<?php echo (int)($totals['bundle_count']); ?></div>
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_total_weight', '本日期号总重量（kg）')); ?></span>：<?php echo htmlspecialchars(number_format((float)$totals['total_weight'], 3, '.', '')); ?></div>
        <div><span style="font-weight:700;"><?php echo htmlspecialchars(t('uda.page.batches_create.stat_total_volume', '本日期号总立方（m³）')); ?></span>：<?php echo htmlspecialchars(number_format((float)$totals['total_volume'], 6, '.', '')); ?></div>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;"><?php echo htmlspecialchars(sprintf(t('uda.page.batches_create.step2_title', '2. 面单号扫描（当前集包 %s）'), $nextLabel)); ?></h3>
    <div class="muted" style="margin-bottom:8px;"><?php echo t('uda.page.batches_create.step2_hint', '面单号<strong>全库不可重复</strong>。手动输入后按<strong>回车</strong>加入列表；扫码枪一般会在条码末尾自动带<strong>回车</strong>。支持粘贴多行（每行一单号）。自动去空格、大写、去 @ 后缀。'); ?></div>
    <input type="text" id="uda_scan_input" autocomplete="off" placeholder="<?php echo htmlspecialchars(t('uda.page.batches_create.scan_placeholder', '面单号 — 回车添加（手按或扫码枪自动回车）')); ?>" style="width:100%;max-width:480px;margin-bottom:10px;">
    <div id="uda_scan_list_wrap" style="max-height:220px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px;padding:8px;background:#fff;">
        <div class="muted" id="uda_scan_empty"><?php echo htmlspecialchars(t('uda.page.batches_create.scan_empty', '尚未添加面单')); ?></div>
        <ul id="uda_scan_list" style="margin:0;padding-left:20px;display:none;"></ul>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;"><?php echo htmlspecialchars(t('uda.page.batches_create.step3', '3. 本集包尺寸与重量（厘米 / 千克）')); ?></h3>
    <form method="post" id="uda_form_bundle">
        <input type="hidden" name="action" value="complete_bundle">
        <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
        <textarea name="waybill_lines" id="uda_waybill_lines" style="display:none;"></textarea>
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end;width:100%;max-width:920px;">
            <div>
                <label><?php echo htmlspecialchars(t('uda.page.batches_create.label_weight_kg', '重量（kg）')); ?></label>
                <input type="text" name="weight_kg" id="uda_weight_kg" inputmode="decimal" required placeholder="<?php echo htmlspecialchars(t('uda.page.batches_create.ph_weight', '如 12.5')); ?>" style="width:100%;">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('uda.page.batches_create.label_len_cm', '长（cm）')); ?></label>
                <input type="text" name="length_cm" id="uda_length_cm" inputmode="decimal" required style="width:100%;">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('uda.page.batches_create.label_w_cm', '宽（cm）')); ?></label>
                <input type="text" name="width_cm" id="uda_width_cm" inputmode="decimal" required style="width:100%;">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('uda.page.batches_create.label_h_cm', '高（cm）')); ?></label>
                <input type="text" name="height_cm" id="uda_height_cm" inputmode="decimal" required style="width:100%;">
            </div>
        </div>
        <div class="inline-actions" style="margin-top:12px;">
            <button type="submit"><?php echo htmlspecialchars(t('uda.page.batches_create.btn_bundle_done', '本集包完成')); ?></button>
        </div>
    </form>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:16px;"><?php echo htmlspecialchars(t('uda.page.batches_create.step4', '4. 结束当前日期号')); ?></h3>
    <div class="muted" style="margin-bottom:8px;"><?php echo t('uda.page.batches_create.step4_hint', '确认所有集包均已提交后，再点「日期号完成」。至少需已成功提交一个集包。「放弃当前日期号」将删除该日期号下全部数据（仅进行中可删）。'); ?></div>
    <div class="inline-actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <form method="post" style="display:inline;" onsubmit="return confirm((window.__udaBatchesCreateI18n || {}).confirmManifestDone || '');">
            <input type="hidden" name="action" value="complete_batch">
            <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
            <button type="submit" class="btn" style="background:#0f766e;color:#fff;" <?php echo (int)$totals['bundle_count'] < 1 ? 'disabled' : ''; ?>><?php echo htmlspecialchars(t('uda.page.batches_create.btn_manifest_done', '日期号完成')); ?></button>
        </form>
        <form method="post" style="display:inline;" onsubmit="return udaConfirmAbandon(this);">
            <input type="hidden" name="action" value="abandon_batch">
            <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
            <button type="submit" class="btn" style="background:#dc2626;color:#fff;"><?php echo htmlspecialchars(t('uda.page.batches_create.btn_abandon', '放弃当前日期号')); ?></button>
        </form>
    </div>
</div>

<div class="card muted">
    <a href="/uda/batches/list"><?php echo htmlspecialchars(t('uda.page.batches_create.footer_list', '集包列表')); ?></a>
    · <?php echo htmlspecialchars(t('uda.page.batches_create.footer_note', '放弃当前日期号或日期号完成后，可重新设定日期号。')); ?>
</div>

<div id="udaManifestToast" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.28);z-index:10060;align-items:center;justify-content:center;">
    <div style="min-width:220px;max-width:min(86vw,420px);background:#fff;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.22);padding:16px 18px;">
        <div id="udaManifestToastMsg" style="font-size:16px;font-weight:700;color:#111827;"><?php echo htmlspecialchars(t('uda.common.toast_title', '提示')); ?></div>
    </div>
</div>

<script>window.__udaBatchesCreateI18n=<?php echo json_encode([
    'confirmManifestDone' => t('uda.page.batches_create.confirm_manifest_done', '确定当前日期号下所有集包均已结束？'),
    'confirmAbandon1' => t('uda.batches_create_js.confirm_abandon_1', '确定要放弃当前日期号？将删除该日期号及全部集包、面单数据。'),
    'confirmAbandon2' => t('uda.batches_create_js.confirm_abandon_2', '再次确认：删除后不可恢复，是否继续？'),
    'dupInList' => t('uda.batches_create_js.dup_in_list', '该面单已在当前待提交列表中，请勿重复'),
    'checkFailed' => t('uda.batches_create_js.check_failed', '校验失败'),
    'sameManifestOther' => t('uda.batches_create_js.same_manifest_other_bundle', '该面单已在当前日期号的其它集包中录入（全库不可重复）'),
    'usedInManifest' => t('uda.batches_create_js.used_in_manifest', '该面单已在日期号「%s」中使用（全库不可重复）'),
    'networkError' => t('uda.batches_create_js.network_error', '网络错误，无法校验面单'),
    'needOneScan' => t('uda.batches_create_js.need_one_scan', '请先扫描至少一个面单号'),
], JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
function udaConfirmAbandon(form) {
    var I = window.__udaBatchesCreateI18n || {};
    if (!confirm(I.confirmAbandon1 || '')) return false;
    if (!confirm(I.confirmAbandon2 || '')) return false;
    return true;
}
(function () {
    var manifestBatchId = <?php echo (int)$batchId; ?>;
    var scans = [];
    var input = document.getElementById('uda_scan_input');
    var listEl = document.getElementById('uda_scan_list');
    var emptyEl = document.getElementById('uda_scan_empty');
    var form = document.getElementById('uda_form_bundle');
    var ta = document.getElementById('uda_waybill_lines');
    var toast = document.getElementById('udaManifestToast');
    var toastMsg = document.getElementById('udaManifestToastMsg');
    var toastTimer = null;
    var I = window.__udaBatchesCreateI18n || {};

    function showToast(msg, autoCloseMs) {
        if (!toast || !toastMsg) return;
        toastMsg.textContent = msg || '';
        toast.style.display = 'flex';
        if (toastTimer) {
            window.clearTimeout(toastTimer);
            toastTimer = null;
        }
        if ((autoCloseMs || 0) > 0) {
            toastTimer = window.setTimeout(function () {
                if (toast) toast.style.display = 'none';
            }, autoCloseMs);
        }
    }

    function closeToast() {
        if (!toast) return;
        toast.style.display = 'none';
    }

    function focusScan() {
        if (input) input.focus();
    }

    if (toast) {
        toast.addEventListener('click', function (e) {
            if (e.target === toast) closeToast();
        });
    }

    function norm(s) {
        s = String(s || '').toUpperCase().trim().replace(/@.*$/, '');
        return s;
    }

    function render() {
        if (!listEl || !emptyEl) return;
        if (scans.length === 0) {
            emptyEl.style.display = '';
            listEl.style.display = 'none';
            listEl.innerHTML = '';
            return;
        }
        emptyEl.style.display = 'none';
        listEl.style.display = '';
        listEl.innerHTML = '';
        scans.forEach(function (t) {
            var li = document.createElement('li');
            li.textContent = t;
            listEl.appendChild(li);
        });
    }

    async function tryAddWaybill(raw) {
        var t = norm(raw);
        if (!t) return false;
        if (scans.indexOf(t) >= 0) {
            showToast(I.dupInList || '', 3200);
            return false;
        }
        try {
            var qs = new URLSearchParams({ tracking_no: t, batch_id: String(manifestBatchId) });
            var r = await fetch('/uda/batches/waybill-check?' + qs.toString(), { credentials: 'same-origin' });
            var j = await r.json();
            if (!j || j.ok === false) {
                showToast((j && j.error) ? String(j.error) : (I.checkFailed || ''), 3200);
                return false;
            }
            if (j.exists) {
                if (j.same_open_batch) {
                    showToast(I.sameManifestOther || '', 4000);
                } else {
                    var bc = j.batch_code ? String(j.batch_code) : '';
                    var dn = j.date_no ? String(j.date_no) : bc;
                    var tpl = I.usedInManifest || '';
                    showToast(tpl.replace('%s', dn), 4200);
                }
                return false;
            }
            scans.push(t);
            render();
            return true;
        } catch (err) {
            showToast(I.networkError || '', 3000);
            return false;
        }
    }

    if (input) {
        input.focus();
        // 扫码枪会在极短时间内连续 keydown，若以 keydown+Enter 立刻读 value，偶发少末位字符；改在 keyup 再读并提交。
        input.addEventListener('keyup', function (e) {
            if (e.key !== 'Enter' && e.keyCode !== 13) return;
            e.preventDefault();
            var raw = String(input.value || '');
            input.value = '';
            void (async function () {
                await tryAddWaybill(raw);
                focusScan();
            })();
        });
        input.addEventListener('paste', function (e) {
            var cd = e.clipboardData && e.clipboardData.getData('text');
            if (!cd) return;
            e.preventDefault();
            void (async function () {
                var lines = cd.split(/\r?\n/);
                for (var i = 0; i < lines.length; i++) {
                    await tryAddWaybill(lines[i]);
                }
                focusScan();
            })();
        });
    }

    if (form && ta) {
        form.addEventListener('submit', function (e) {
            if (scans.length === 0) {
                e.preventDefault();
                showToast(I.needOneScan || '', 3000);
                return;
            }
            ta.value = scans.join('\n');
        });
    }
})();
</script>
<?php endif; ?>
