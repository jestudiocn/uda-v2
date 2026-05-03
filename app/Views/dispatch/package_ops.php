<?php
/** @var bool $ordersSchemaV2 */
/** @var string $migrationHint */
/** @var list<string> $orderStatusCatalog */
/** @var bool $canArrivalScan */
/** @var bool $canSelfPickup */
/** @var bool $canForwardPush */
/** @var bool $canStatusFix */
$dash = t('dispatch.view.common.dash', '—');
$orderStatusLabel = static function (string $s): string {
    $map = [
        '待入库' => ['dispatch.view.order_status.wait_inbound', '待入库'],
        '部分入库' => ['dispatch.view.order_status.partial_inbound', '部分入库'],
        '已入库' => ['dispatch.view.order_status.inbound', '已入库'],
        '待自取' => ['dispatch.view.order_status.wait_pickup', '待自取'],
        '待转发' => ['dispatch.view.order_status.wait_forward', '待转发'],
        '已出库' => ['dispatch.view.order_status.outbound', '已出库'],
        '已自取' => ['dispatch.view.order_status.picked', '已自取'],
        '已转发' => ['dispatch.view.order_status.forwarded', '已转发'],
        '已派送' => ['dispatch.view.order_status.delivered', '已派送'],
        '问题件' => ['dispatch.view.order_status.issue', '问题件'],
    ];
    if (!isset($map[$s])) {
        return $s;
    }
    return t($map[$s][0], $map[$s][1]);
};
$catalog = array_values(array_filter(array_map('strval', (array)($orderStatusCatalog ?? []))));
$orderStatusLabels = [];
foreach ($catalog as $st) {
    $orderStatusLabels[$st] = $orderStatusLabel($st);
}
$packageOpsI18n = [
    'dash' => $dash,
    'orderStatusLabels' => $orderStatusLabels,
    'toastTitle' => t('dispatch.view.package_ops.toast_title', '提示'),
    'errCnpConnect' => t('dispatch.view.package_ops.err_cnp_connect', '无法连接菜鸟打印服务'),
    'errWsCreate' => t('dispatch.view.package_ops.err_ws_create', '创建连接失败'),
    'errWsConnect' => t('dispatch.view.package_ops.err_ws_connect', '连接失败'),
    'errCnpTimeout' => t('dispatch.view.package_ops.err_cnp_timeout', '菜鸟请求超时'),
    'errCnpEmpty' => t('dispatch.view.package_ops.err_cnp_empty', '菜鸟无返回'),
    'errCnpPrintFail' => t('dispatch.view.package_ops.err_cnp_print_fail', '菜鸟打印返回失败'),
    'processing' => t('dispatch.view.package_ops.processing', '处理中...'),
    'enterTrack' => t('dispatch.view.package_ops.enter_track', '请输入或扫描单号'),
    'scanOk' => t('dispatch.view.package_ops.scan_ok', '扫描成功：{track}（状态：已入库{pdf}）'),
    'pdfSuffix' => t('dispatch.view.package_ops.pdf_suffix', '，PDF: {mode}'),
    'failNoPdfUrl' => t('dispatch.view.package_ops.fail_no_pdf_url', '处理失败：未生成 PDF 地址'),
    'toastNoPdf' => t('dispatch.view.package_ops.toast_no_pdf', '未生成 PDF'),
    'sentCnp' => t('dispatch.view.package_ops.sent_cnp', '已发送到菜鸟组件直打{pdf}'),
    'cnpFailHint' => t('dispatch.view.package_ops.cnp_fail_hint', '菜鸟打印失败：{msg}'),
    'toastCnpFail' => t('dispatch.view.package_ops.toast_cnp_fail', '菜鸟打印失败'),
    'issueDone' => t('dispatch.view.package_ops.issue_done', '扫描完成：{track}（状态：问题件）'),
    'toastNoCode' => t('dispatch.view.package_ops.toast_no_code', '无客户编码'),
    'blocked' => t('dispatch.view.package_ops.blocked', '扫描拦截：{track}（状态：{status}）'),
    'toastBlocked' => t('dispatch.view.package_ops.toast_blocked', '订单即将完成或已完成（当前：{status}）'),
    'unknownStatus' => t('dispatch.view.package_ops.unknown_status', '未知'),
    'noWaybill' => t('dispatch.view.package_ops.no_waybill', '未匹配到单号：{track}'),
    'toastNoWaybill' => t('dispatch.view.package_ops.toast_no_waybill', '无此单号'),
    'processFail' => t('dispatch.view.package_ops.process_fail', '处理失败'),
    'networkRetry' => t('dispatch.view.package_ops.network_retry', '网络错误，请重试'),
    'enterCust' => t('dispatch.view.package_ops.enter_cust', '请输入客户编码'),
    'querying' => t('dispatch.view.package_ops.querying', '查询中...'),
    'queryFail' => t('dispatch.view.package_ops.query_fail', '查询失败'),
    'pickupOk' => t('dispatch.view.package_ops.pickup_ok', '客户编码 {code} 查询完成，共 {n} 件可自取订单'),
    'pickupNone' => t('dispatch.view.package_ops.pickup_none', '客户编码 {code} 没有可自取订单'),
    'queryCustFirst' => t('dispatch.view.package_ops.query_cust_first', '请先输入客户编码并查询'),
    'checkOrdersFirst' => t('dispatch.view.package_ops.check_orders_first', '请先勾选订单'),
    'submitting' => t('dispatch.view.package_ops.submitting', '提交中...'),
    'submitFail' => t('dispatch.view.package_ops.submit_fail', '提交失败'),
    'selfDone' => t('dispatch.view.package_ops.self_done', '提交完成：已改为已自取 {picked} 件{skipped}'),
    'skippedPart' => t('dispatch.view.package_ops.skipped_part', '，跳过 {n} 件'),
    'toastSelfDone' => t('dispatch.view.package_ops.toast_self_done', '自取录入完成'),
    'forwardOk' => t('dispatch.view.package_ops.forward_ok', '客户编码 {code} 查询完成，共 {n} 件已入库订单'),
    'forwardNone' => t('dispatch.view.package_ops.forward_none', '客户编码 {code} 没有可推送订单'),
    'forwardDone' => t('dispatch.view.package_ops.forward_done', '提交完成：已推送待转发 {pushed} 件{skipped}'),
    'toastForwardDone' => t('dispatch.view.package_ops.toast_forward_done', '手动推送待转发完成'),
    'needTrackOrCode' => t('dispatch.view.package_ops.need_track_or_code', '请至少输入原始单号或客户代码之一'),
    'fixCondTrack' => t('dispatch.view.package_ops.fix_cond_track', '原始单号={v}'),
    'fixCondCode' => t('dispatch.view.package_ops.fix_cond_code', '客户代码={v}'),
    'fixDone' => t('dispatch.view.package_ops.fix_done', '{cond} 查询完成，共 {n} 条匹配订单'),
    'fixNone' => t('dispatch.view.package_ops.fix_none', '{cond} 未匹配到订单'),
    'queryOrdersFirst' => t('dispatch.view.package_ops.query_orders_first', '请先查询订单'),
    'noStatusChanges' => t('dispatch.view.package_ops.no_status_changes', '没有状态变更项'),
    'fixSubmitDone' => t('dispatch.view.package_ops.fix_submit_done', '状态修正完成：成功更新 {n} 条'),
    'toastFixDone' => t('dispatch.view.package_ops.toast_fix_done', '货件状态修正完成'),
    'cnpOk' => t('dispatch.view.package_ops.cnp_ok', '菜鸟状态：已连接'),
    'cnpFailStatus' => t('dispatch.view.package_ops.cnp_fail_status', '菜鸟状态：连接失败（{msg}）'),
    'waitQuery' => t('dispatch.view.package_ops.wait_query', '等待查询...'),
    'waitScan' => t('dispatch.view.package_ops.arrival_wait', '等待扫描...'),
];
?>
<style>
#status_fix_table tbody tr.status-fix-changed td {
    background: #fff7ed !important;
}
#status_fix_table tbody tr.status-fix-changed:hover td {
    background: #ffedd5 !important;
}
</style>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.title', '派送业务 / 货件操作')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('dispatch.view.package_ops.subtitle', '三项功能均支持独立权限控制；无权限的功能不会显示。')); ?></div>
</div>

<?php if (!$ordersSchemaV2): ?>
    <div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($migrationHint); ?></div>
<?php endif; ?>

<?php if (!empty($canArrivalScan)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.arrival_title', '1) 到件扫描')); ?></h3>
    <div class="muted" style="margin-bottom:10px;"><?php echo t('dispatch.view.package_ops.arrival_hint', '扫描枪输入后自动回车即可执行；手工输入时按回车执行。若末尾为 <code>@数字</code>，系统会自动去除后再匹配原始单号。'); ?></div>
    <form id="arrivalScanForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="arrival_scan_submit" value="1">
        <label for="arrival_tracking_no" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.label_no', '单号')); ?></label>
        <input
            id="arrival_tracking_no"
            name="tracking_no"
            type="text"
            autocomplete="off"
            autofocus
            placeholder="<?php echo htmlspecialchars(t('dispatch.view.package_ops.ph_arrival', '请扫描或输入原始单号')); ?>"
            style="min-width:320px;"
        >
        <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_run', '执行')); ?></button>
    </form>
    <div class="muted" id="cnp_status" style="margin-top:8px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.cnp_checking', '菜鸟状态：检测中...')); ?></div>
    <div class="muted" id="arrival_hint" style="margin-top:8px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.arrival_wait', '等待扫描...')); ?></div>
</div>
<?php endif; ?>

<?php if (!empty($canSelfPickup)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.self_pickup_title', '2) 自取录入')); ?></h3>
    <div class="muted" style="margin-bottom:10px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.self_pickup_desc', '输入客户编码后，带出该客户可自取订单（排除：已派送、待入库/部分入库/未入库、问题件、已出库、已自取、已转发、待转发）。勾选并提交后，订单状态改为「已自取」。')); ?></div>
    <form id="selfPickupQueryForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="self_pickup_query" value="1">
        <label for="self_pickup_customer_code" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.label_cust_code', '客户编码')); ?></label>
        <input id="self_pickup_customer_code" name="customer_code" type="text" autocomplete="off" placeholder="<?php echo htmlspecialchars(t('dispatch.view.package_ops.ph_cust_code', '请输入客户编码')); ?>" style="min-width:260px;">
        <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_query_orders', '查询订单')); ?></button>
    </form>
    <div id="self_pickup_hint" class="muted" style="margin-top:8px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.wait_query', '等待查询...')); ?></div>
    <div id="self_pickup_ops" style="margin-top:8px;display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="checkbox" id="self_pickup_select_all">
            <span><?php echo htmlspecialchars(t('dispatch.view.package_ops.select_all', '全选')); ?></span>
        </label>
        <strong><?php echo htmlspecialchars(t('dispatch.view.package_ops.total_count', '总件数：')); ?><span id="self_pickup_total">0</span></strong>
        <strong><?php echo htmlspecialchars(t('dispatch.view.package_ops.checked_count', '已勾选：')); ?><span id="self_pickup_checked">0</span></strong>
        <button type="button" id="self_pickup_submit_btn"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_self_submit', '勾选送出（改为已自取）')); ?></button>
    </div>
    <div style="margin-top:8px;overflow:auto;max-height:340px;">
        <table class="data-table" id="self_pickup_table" style="display:none;min-width:760px;">
            <thead>
                <tr>
                    <th style="width:56px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_select', '选择')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_track', '原始单号')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_status', '当前状态')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_scan', '扫描时间')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_id', '单号ID')); ?></th>
                </tr>
            </thead>
            <tbody id="self_pickup_tbody"></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($canForwardPush)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.forward_title', '3) 手动推送待转发')); ?></h3>
    <div class="muted" style="margin-bottom:10px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.forward_desc', '输入客户编码后，带出该客户当前「已入库」订单。默认全选，可取消部分后推送；提交后状态改为「待转发」。')); ?></div>
    <form id="forwardPushQueryForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="forward_push_query" value="1">
        <label for="forward_push_customer_code" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.label_cust_code', '客户编码')); ?></label>
        <input id="forward_push_customer_code" name="customer_code" type="text" autocomplete="off" placeholder="<?php echo htmlspecialchars(t('dispatch.view.package_ops.ph_cust_code', '请输入客户编码')); ?>" style="min-width:260px;">
        <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_query_orders', '查询订单')); ?></button>
    </form>
    <div id="forward_push_hint" class="muted" style="margin-top:8px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.wait_query', '等待查询...')); ?></div>
    <div id="forward_push_ops" style="margin-top:8px;display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="checkbox" id="forward_push_select_all">
            <span><?php echo htmlspecialchars(t('dispatch.view.package_ops.select_all', '全选')); ?></span>
        </label>
        <strong><?php echo htmlspecialchars(t('dispatch.view.package_ops.total_count', '总件数：')); ?><span id="forward_push_total">0</span></strong>
        <strong><?php echo htmlspecialchars(t('dispatch.view.package_ops.checked_count', '已勾选：')); ?><span id="forward_push_checked">0</span></strong>
        <button type="button" id="forward_push_submit_btn"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_forward_submit', '勾选送出（改为待转发）')); ?></button>
    </div>
    <div style="margin-top:8px;overflow:auto;max-height:340px;">
        <table class="data-table" id="forward_push_table" style="display:none;min-width:760px;">
            <thead>
                <tr>
                    <th style="width:56px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_select', '选择')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_track', '原始单号')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_status', '当前状态')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_scan', '扫描时间')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_id', '单号ID')); ?></th>
                </tr>
            </thead>
            <tbody id="forward_push_tbody"></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($canStatusFix)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.status_fix_title', '4) 货件状态修正')); ?></h3>
    <div class="muted" style="margin-bottom:10px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.status_fix_desc', '可按原始单号、客户代码单独查询，也可两者同时作为多条件查询；在状态下拉中选择新状态后送出，直接更改订单状态。')); ?></div>
    <form id="statusFixQueryForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="status_fix_query" value="1">
        <label for="status_fix_tracking_no" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.label_track_fix', '原始单号')); ?></label>
        <input id="status_fix_tracking_no" name="tracking_no" type="text" autocomplete="off" placeholder="<?php echo htmlspecialchars(t('dispatch.view.package_ops.ph_track_fuzzy', '可模糊查询')); ?>" style="min-width:240px;">
        <label for="status_fix_customer_code" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.label_cust_code_fix', '客户代码')); ?></label>
        <input id="status_fix_customer_code" name="customer_code" type="text" autocomplete="off" placeholder="<?php echo htmlspecialchars(t('dispatch.view.package_ops.ph_cust_exact', '可精确查询')); ?>" style="min-width:220px;">
        <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_query', '查询')); ?></button>
    </form>
    <div id="status_fix_hint" class="muted" style="margin-top:8px;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.wait_query', '等待查询...')); ?></div>
    <div id="status_fix_ops" style="margin-top:8px;display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <strong><?php echo htmlspecialchars(t('dispatch.view.package_ops.match_count', '匹配件数：')); ?><span id="status_fix_total">0</span></strong>
        <button type="button" id="status_fix_submit_btn"><?php echo htmlspecialchars(t('dispatch.view.package_ops.btn_status_submit', '送出状态修正')); ?></button>
    </div>
    <div style="margin-top:8px;overflow:auto;max-height:360px;">
        <table class="data-table" id="status_fix_table" style="display:none;min-width:900px;">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_track', '原始单号')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_cust_code', '客户编码')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_status', '当前状态')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_new_status', '修正状态')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_scan', '扫描时间')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.package_ops.th_id', '单号ID')); ?></th>
                </tr>
            </thead>
            <tbody id="status_fix_tbody"></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="arrivalToast" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.28);z-index:10060;align-items:center;justify-content:center;">
    <div style="min-width:220px;max-width:min(86vw,420px);background:#fff;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.22);padding:16px 18px;">
        <div id="arrivalToastMsg" style="font-size:16px;font-weight:700;color:#111827;"><?php echo htmlspecialchars(t('dispatch.view.package_ops.toast_title', '提示')); ?></div>
    </div>
</div>

<script>
window.__dispatchPackageOpsI18n = <?php echo json_encode($packageOpsI18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
(function () {
    var I = window.__dispatchPackageOpsI18n || {};
    var dash = (I.dash !== undefined && I.dash !== null && String(I.dash) !== '') ? String(I.dash) : '\u2014';
    var stLabels = I.orderStatusLabels || {};
    function sub(tpl, pairs) {
        var s = String(tpl || '');
        Object.keys(pairs || {}).forEach(function (k) {
            var v = pairs[k];
            s = s.split('{' + k + '}').join(v != null ? String(v) : '');
        });
        return s;
    }
    function labStatus(raw) {
        var k = String(raw || '');
        return stLabels[k] || k || '';
    }
    var form = document.getElementById('arrivalScanForm');
    var input = document.getElementById('arrival_tracking_no');
    var hint = document.getElementById('arrival_hint');
    var cnpStatus = document.getElementById('cnp_status');
    var selfPickupForm = document.getElementById('selfPickupQueryForm');
    var selfPickupCodeInput = document.getElementById('self_pickup_customer_code');
    var selfPickupHint = document.getElementById('self_pickup_hint');
    var selfPickupOps = document.getElementById('self_pickup_ops');
    var selfPickupSelectAll = document.getElementById('self_pickup_select_all');
    var selfPickupTotal = document.getElementById('self_pickup_total');
    var selfPickupChecked = document.getElementById('self_pickup_checked');
    var selfPickupTable = document.getElementById('self_pickup_table');
    var selfPickupTbody = document.getElementById('self_pickup_tbody');
    var selfPickupSubmitBtn = document.getElementById('self_pickup_submit_btn');
    var forwardPushForm = document.getElementById('forwardPushQueryForm');
    var forwardPushCodeInput = document.getElementById('forward_push_customer_code');
    var forwardPushHint = document.getElementById('forward_push_hint');
    var forwardPushOps = document.getElementById('forward_push_ops');
    var forwardPushSelectAll = document.getElementById('forward_push_select_all');
    var forwardPushTotal = document.getElementById('forward_push_total');
    var forwardPushChecked = document.getElementById('forward_push_checked');
    var forwardPushTable = document.getElementById('forward_push_table');
    var forwardPushTbody = document.getElementById('forward_push_tbody');
    var forwardPushSubmitBtn = document.getElementById('forward_push_submit_btn');
    var statusFixForm = document.getElementById('statusFixQueryForm');
    var statusFixTrackingNoInput = document.getElementById('status_fix_tracking_no');
    var statusFixCustomerCodeInput = document.getElementById('status_fix_customer_code');
    var statusFixHint = document.getElementById('status_fix_hint');
    var statusFixOps = document.getElementById('status_fix_ops');
    var statusFixTotal = document.getElementById('status_fix_total');
    var statusFixTable = document.getElementById('status_fix_table');
    var statusFixTbody = document.getElementById('status_fix_tbody');
    var statusFixSubmitBtn = document.getElementById('status_fix_submit_btn');
    var orderStatusCatalog = <?php echo json_encode($catalog, JSON_UNESCAPED_UNICODE); ?>;
    var toast = document.getElementById('arrivalToast');
    var toastMsg = document.getElementById('arrivalToastMsg');
    var isBusy = false;
    var toastTimer = null;

    function normalizeTrackingNo(raw) {
        var s = (raw || '').trim();
        if (!s) return '';
        return s.replace(/@\d+$/, '').trim();
    }

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
                toast.style.display = 'none';
            }, autoCloseMs);
        }
    }

    function closeToast() {
        if (!toast) return;
        toast.style.display = 'none';
    }

    function connectCainiaoAgent() {
        var urls = [];
        if (window.location.protocol === 'https:') {
            urls.push('wss://localhost:13529');
            urls.push('ws://localhost:13528');
        } else {
            urls.push('ws://localhost:13528');
            urls.push('wss://localhost:13529');
        }
        return new Promise(function (resolve, reject) {
            var i = 0;
            function next(lastErr) {
                if (i >= urls.length) {
                    reject(new Error(lastErr || I.errCnpConnect));
                    return;
                }
                var ws = null;
                var url = urls[i++];
                try {
                    ws = new WebSocket(url);
                } catch (e) {
                    next(I.errWsCreate);
                    return;
                }
                ws.onopen = function () { resolve(ws); };
                ws.onerror = function () {
                    try { ws.close(); } catch (e) {}
                    next(I.errWsConnect);
                };
            }
            next('');
        });
    }

    function wsRequest(ws, payload, timeoutMs) {
        return new Promise(function (resolve, reject) {
            var done = false;
            var timer = window.setTimeout(function () {
                if (done) return;
                done = true;
                ws.removeEventListener('message', onMsg);
                reject(new Error(I.errCnpTimeout));
            }, timeoutMs || 6000);
            function onMsg(evt) {
                if (done) return;
                var msg = null;
                try { msg = JSON.parse(evt.data || '{}'); } catch (e) {}
                if (!msg || msg.requestID !== payload.requestID) return;
                done = true;
                window.clearTimeout(timer);
                ws.removeEventListener('message', onMsg);
                resolve(msg);
            }
            ws.addEventListener('message', onMsg);
            ws.send(JSON.stringify(payload));
        });
    }

    function makeReqId() {
        return String(Date.now()) + String(Math.floor(Math.random() * 100000)).padStart(5, '0');
    }

    async function ensureCainiaoReady() {
        var ws = await connectCainiaoAgent();
        try {
            await wsRequest(ws, { cmd: 'getAgentInfo', requestID: makeReqId(), version: '1.0' }, 3000);
        } catch (e) {}
        return ws;
    }

    function extractCnpError(rsp) {
        if (!rsp) return I.errCnpEmpty;
        if (rsp.msg) return String(rsp.msg);
        if (rsp.detail) return String(rsp.detail);
        if (rsp.printStatus && Array.isArray(rsp.printStatus) && rsp.printStatus.length > 0) {
            var first = rsp.printStatus[0] || {};
            if (first.msg) return String(first.msg);
            if (first.detail) return String(first.detail);
            if (first.status) return String(first.status);
        }
        return I.errCnpPrintFail;
    }

    function buildDirectPrintReq(printType, pdfUrl) {
        return {
            cmd: 'print',
            requestID: makeReqId(),
            version: '1.0',
            task: {
                taskID: 'ARRIVAL_' + Date.now(),
                preview: false,
                printType: printType,
                documents: [
                    {
                        documentID: 'DOC_' + Date.now(),
                        contents: [
                            {
                                templateURL: pdfUrl,
                                data: {}
                            }
                        ]
                    }
                ]
            }
        };
    }

    async function printArrivalPdfByCainiao(pdfUrl) {
        var ws = await ensureCainiaoReady();
        // 菜鸟协议中 PDF 直打字段为历史拼写 dirctPrint（官方文档即此拼写）。
        var printType = 'dirctPrint';
        var req = buildDirectPrintReq(printType, pdfUrl);
        var rsp = await wsRequest(ws, req, 6000);
        try { ws.close(); } catch (e) {}
        var ok = rsp && (
            rsp.status === 'success' ||
            rsp.success === true ||
            rsp.result === 'success' ||
            rsp.cmd === 'print' ||
            (!!rsp.requestID && !rsp.msg)
        );
        if (!ok) {
            throw new Error(extractCnpError(rsp));
        }
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function selfPickupRows() {
        if (!selfPickupTbody) return [];
        return Array.prototype.slice.call(selfPickupTbody.querySelectorAll('input[type="checkbox"][data-waybill-id]'));
    }

    function updateSelfPickupChecked() {
        var rows = selfPickupRows();
        var checked = rows.filter(function (el) { return !!el.checked; }).length;
        if (selfPickupChecked) selfPickupChecked.textContent = String(checked);
        if (selfPickupSelectAll && rows.length > 0) {
            selfPickupSelectAll.checked = checked === rows.length;
            selfPickupSelectAll.indeterminate = checked > 0 && checked < rows.length;
        } else if (selfPickupSelectAll) {
            selfPickupSelectAll.checked = false;
            selfPickupSelectAll.indeterminate = false;
        }
    }

    function renderSelfPickupRows(rows) {
        if (!selfPickupTbody || !selfPickupTable || !selfPickupOps) return;
        selfPickupTbody.innerHTML = '';
        var list = Array.isArray(rows) ? rows : [];
        list.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td><input type="checkbox" data-waybill-id="' + escapeHtml(r.id) + '"></td>'
                + '<td class="cell-tip">' + escapeHtml(r.original_tracking_no || '') + '</td>'
                + '<td>' + escapeHtml(labStatus(r.order_status)) + '</td>'
                + '<td>' + escapeHtml(r.scanned_at || dash) + '</td>'
                + '<td>' + escapeHtml(r.id) + '</td>';
            selfPickupTbody.appendChild(tr);
        });
        selfPickupTable.style.display = list.length > 0 ? '' : 'none';
        selfPickupOps.style.display = list.length > 0 ? 'flex' : 'none';
        if (selfPickupTotal) selfPickupTotal.textContent = String(list.length);
        updateSelfPickupChecked();
        selfPickupRows().forEach(function (el) {
            el.addEventListener('change', updateSelfPickupChecked);
        });
    }

    function forwardPushRows() {
        if (!forwardPushTbody) return [];
        return Array.prototype.slice.call(forwardPushTbody.querySelectorAll('input[type="checkbox"][data-waybill-id]'));
    }

    function updateForwardPushChecked() {
        var rows = forwardPushRows();
        var checked = rows.filter(function (el) { return !!el.checked; }).length;
        if (forwardPushChecked) forwardPushChecked.textContent = String(checked);
        if (forwardPushSelectAll && rows.length > 0) {
            forwardPushSelectAll.checked = checked === rows.length;
            forwardPushSelectAll.indeterminate = checked > 0 && checked < rows.length;
        } else if (forwardPushSelectAll) {
            forwardPushSelectAll.checked = false;
            forwardPushSelectAll.indeterminate = false;
        }
    }

    function renderForwardPushRows(rows) {
        if (!forwardPushTbody || !forwardPushTable || !forwardPushOps) return;
        forwardPushTbody.innerHTML = '';
        var list = Array.isArray(rows) ? rows : [];
        list.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td><input type="checkbox" data-waybill-id="' + escapeHtml(r.id) + '" checked></td>'
                + '<td class="cell-tip">' + escapeHtml(r.original_tracking_no || '') + '</td>'
                + '<td>' + escapeHtml(labStatus(r.order_status)) + '</td>'
                + '<td>' + escapeHtml(r.scanned_at || dash) + '</td>'
                + '<td>' + escapeHtml(r.id) + '</td>';
            forwardPushTbody.appendChild(tr);
        });
        forwardPushTable.style.display = list.length > 0 ? '' : 'none';
        forwardPushOps.style.display = list.length > 0 ? 'flex' : 'none';
        if (forwardPushTotal) forwardPushTotal.textContent = String(list.length);
        updateForwardPushChecked();
        forwardPushRows().forEach(function (el) {
            el.addEventListener('change', updateForwardPushChecked);
        });
    }

    function renderStatusFixRows(rows) {
        if (!statusFixTbody || !statusFixTable || !statusFixOps) return;
        statusFixTbody.innerHTML = '';
        var list = Array.isArray(rows) ? rows : [];
        list.forEach(function (r) {
            var current = String((r && r.order_status) || '');
            var opts = orderStatusCatalog.map(function (st) {
                var s = String(st || '');
                var selected = s === current ? ' selected' : '';
                return '<option value="' + escapeHtml(s) + '"' + selected + '>' + escapeHtml(labStatus(s)) + '</option>';
            }).join('');
            if (opts.indexOf(' selected') < 0 && current) {
                opts = '<option value="' + escapeHtml(current) + '" selected>' + escapeHtml(labStatus(current)) + '</option>' + opts;
            }
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td class="cell-tip">' + escapeHtml(r.original_tracking_no || '') + '</td>'
                + '<td>' + escapeHtml(r.customer_code || dash) + '</td>'
                + '<td>' + escapeHtml(current ? labStatus(current) : dash) + '</td>'
                + '<td><select data-status-waybill-id="' + escapeHtml(r.id) + '" data-status-original="' + escapeHtml(current) + '">' + opts + '</select></td>'
                + '<td>' + escapeHtml(r.scanned_at || dash) + '</td>'
                + '<td>' + escapeHtml(r.id) + '</td>';
            statusFixTbody.appendChild(tr);
        });
        if (statusFixTbody) {
            statusFixTbody.querySelectorAll('select[data-status-waybill-id]').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    var row = sel.closest('tr');
                    if (!row) return;
                    var oldStatus = String(sel.getAttribute('data-status-original') || '');
                    var newStatus = String(sel.value || '');
                    if (newStatus !== oldStatus) {
                        row.classList.add('status-fix-changed');
                    } else {
                        row.classList.remove('status-fix-changed');
                    }
                });
            });
        }
        statusFixTable.style.display = list.length > 0 ? '' : 'none';
        statusFixOps.style.display = list.length > 0 ? 'flex' : 'none';
        if (statusFixTotal) statusFixTotal.textContent = String(list.length);
    }

    if (form && input) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isBusy) return;
            closeToast();
            // 扫码枪末尾跟 Enter 时，个别浏览器在 submit 瞬间 value 尚未刷满；延后一帧再读。
            window.setTimeout(function () {
                if (isBusy) return;
                var normalized = normalizeTrackingNo(input.value || '');
                input.value = normalized;
                if (!normalized) {
                    showToast(I.enterTrack, 3000);
                    input.focus();
                    return;
                }
                runArrivalSubmit(normalized);
            }, 0);
        });
    }

    function runArrivalSubmit(normalized) {
            isBusy = true;
            if (hint) hint.textContent = I.processing;
            var fd = new FormData();
            fd.append('arrival_scan_submit', '1');
            fd.append('tracking_no', normalized);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) {
                        var pdfMode = String((j && j.pdf_mode) || '');
                        var pdfDebug = String((j && j.pdf_debug) || '');
                        var pdfPart = pdfMode ? sub(I.pdfSuffix, { mode: pdfMode }) : '';
                        if (hint) hint.textContent = sub(I.scanOk, { track: (j.tracking_no || normalized), pdf: pdfPart });
                        var pdfUrl = String((j && j.pdf_url) || '');
                        if (!pdfUrl) {
                            if (hint) hint.textContent = I.failNoPdfUrl;
                            showToast(I.toastNoPdf, 3000);
                            return;
                        }
                        printArrivalPdfByCainiao(pdfUrl)
                            .then(function () {
                                var pdfTail = pdfMode ? ('（PDF: ' + pdfMode + (pdfDebug ? ' / ' + pdfDebug : '') + '）') : '';
                                if (hint) hint.textContent = sub(I.sentCnp, { pdf: pdfTail });
                            })
                            .catch(function (e) {
                                var em = (e && e.message) ? String(e.message) : I.unknownStatus;
                                if (hint) hint.textContent = sub(I.cnpFailHint, { msg: em });
                                showToast(I.toastCnpFail, 3000);
                            });
                    } else {
                        var code = (j && j.code) ? j.code : '';
                        if (code === 'no_customer_code') {
                            if (hint) hint.textContent = sub(I.issueDone, { track: (j.tracking_no || normalized) });
                            showToast(I.toastNoCode, 3000);
                        } else if (code === 'order_near_or_done') {
                            var blockedStatus = ((j && j.status) ? String(j.status) : I.unknownStatus);
                            var blockedDisp = labStatus(blockedStatus) || blockedStatus;
                            if (hint) hint.textContent = sub(I.blocked, { track: (j.tracking_no || normalized), status: blockedDisp });
                            showToast(sub(I.toastBlocked, { status: blockedDisp }), 3000);
                        } else if (code === 'no_waybill') {
                            if (hint) hint.textContent = sub(I.noWaybill, { track: normalized });
                            showToast(I.toastNoWaybill, 3000);
                        } else {
                            if (hint) hint.textContent = I.processFail;
                            showToast((j && j.error) ? j.error : I.processFail, 3000);
                        }
                    }
                })
                .catch(function () {
                    if (hint) hint.textContent = I.processFail;
                    showToast(I.networkRetry, 3000);
                })
                .finally(function () {
                    isBusy = false;
                    input.value = '';
                    input.focus();
                });
    }

    if (selfPickupSelectAll) {
        selfPickupSelectAll.addEventListener('change', function () {
            var checked = !!selfPickupSelectAll.checked;
            selfPickupRows().forEach(function (el) { el.checked = checked; });
            updateSelfPickupChecked();
        });
    }

    if (selfPickupForm && selfPickupCodeInput) {
        selfPickupForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var code = String(selfPickupCodeInput.value || '').trim();
            selfPickupCodeInput.value = code;
            if (!code) {
                showToast(I.enterCust, 3000);
                selfPickupCodeInput.focus();
                return;
            }
            if (selfPickupHint) selfPickupHint.textContent = I.querying;
            var fd = new FormData();
            fd.append('self_pickup_query', '1');
            fd.append('customer_code', code);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        renderSelfPickupRows([]);
                        if (selfPickupHint) selfPickupHint.textContent = I.queryFail;
                        showToast((j && j.error) ? j.error : I.queryFail, 3000);
                        return;
                    }
                    var rows = Array.isArray(j.rows) ? j.rows : [];
                    renderSelfPickupRows(rows);
                    if (selfPickupHint) {
                        selfPickupHint.textContent = rows.length > 0
                            ? sub(I.pickupOk, { code: code, n: rows.length })
                            : sub(I.pickupNone, { code: code });
                    }
                })
                .catch(function () {
                    renderSelfPickupRows([]);
                    if (selfPickupHint) selfPickupHint.textContent = I.queryFail;
                    showToast(I.networkRetry, 3000);
                });
        });
    }

    if (selfPickupSubmitBtn) {
        selfPickupSubmitBtn.addEventListener('click', function () {
            var code = String((selfPickupCodeInput && selfPickupCodeInput.value) || '').trim();
            var picked = selfPickupRows()
                .filter(function (el) { return !!el.checked; })
                .map(function (el) { return String(el.getAttribute('data-waybill-id') || ''); })
                .filter(function (s) { return !!s; });
            if (!code) {
                showToast(I.queryCustFirst, 3000);
                return;
            }
            if (picked.length <= 0) {
                showToast(I.checkOrdersFirst, 3000);
                return;
            }
            if (selfPickupHint) selfPickupHint.textContent = I.submitting;
            var fd = new FormData();
            fd.append('self_pickup_submit', '1');
            fd.append('customer_code', code);
            picked.forEach(function (id) { fd.append('waybill_ids[]', id); });
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        if (selfPickupHint) selfPickupHint.textContent = I.submitFail;
                        showToast((j && j.error) ? j.error : I.submitFail, 3000);
                        return;
                    }
                    var pickedCount = Number((j && j.picked_count) || 0);
                    var skippedCount = Number((j && j.skipped_count) || 0);
                    if (selfPickupHint) {
                        var sk = skippedCount > 0 ? sub(I.skippedPart, { n: skippedCount }) : '';
                        selfPickupHint.textContent = sub(I.selfDone, { picked: pickedCount, skipped: sk });
                    }
                    showToast(I.toastSelfDone, 2000);
                    if (selfPickupCodeInput) selfPickupCodeInput.value = '';
                    renderSelfPickupRows([]);
                })
                .catch(function () {
                    if (selfPickupHint) selfPickupHint.textContent = I.submitFail;
                    showToast(I.networkRetry, 3000);
                });
        });
    }

    if (forwardPushSelectAll) {
        forwardPushSelectAll.addEventListener('change', function () {
            var checked = !!forwardPushSelectAll.checked;
            forwardPushRows().forEach(function (el) { el.checked = checked; });
            updateForwardPushChecked();
        });
    }

    if (forwardPushForm && forwardPushCodeInput) {
        forwardPushForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var code = String(forwardPushCodeInput.value || '').trim();
            forwardPushCodeInput.value = code;
            if (!code) {
                showToast(I.enterCust, 3000);
                forwardPushCodeInput.focus();
                return;
            }
            if (forwardPushHint) forwardPushHint.textContent = I.querying;
            var fd = new FormData();
            fd.append('forward_push_query', '1');
            fd.append('customer_code', code);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        renderForwardPushRows([]);
                        if (forwardPushHint) forwardPushHint.textContent = I.queryFail;
                        showToast((j && j.error) ? j.error : I.queryFail, 3000);
                        return;
                    }
                    var rows = Array.isArray(j.rows) ? j.rows : [];
                    renderForwardPushRows(rows);
                    if (forwardPushHint) {
                        forwardPushHint.textContent = rows.length > 0
                            ? sub(I.forwardOk, { code: code, n: rows.length })
                            : sub(I.forwardNone, { code: code });
                    }
                })
                .catch(function () {
                    renderForwardPushRows([]);
                    if (forwardPushHint) forwardPushHint.textContent = I.queryFail;
                    showToast(I.networkRetry, 3000);
                });
        });
    }

    if (forwardPushSubmitBtn) {
        forwardPushSubmitBtn.addEventListener('click', function () {
            var code = String((forwardPushCodeInput && forwardPushCodeInput.value) || '').trim();
            var picked = forwardPushRows()
                .filter(function (el) { return !!el.checked; })
                .map(function (el) { return String(el.getAttribute('data-waybill-id') || ''); })
                .filter(function (s) { return !!s; });
            if (!code) {
                showToast(I.queryCustFirst, 3000);
                return;
            }
            if (picked.length <= 0) {
                showToast(I.checkOrdersFirst, 3000);
                return;
            }
            if (forwardPushHint) forwardPushHint.textContent = I.submitting;
            var fd = new FormData();
            fd.append('forward_push_submit', '1');
            fd.append('customer_code', code);
            picked.forEach(function (id) { fd.append('waybill_ids[]', id); });
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        if (forwardPushHint) forwardPushHint.textContent = I.submitFail;
                        showToast((j && j.error) ? j.error : I.submitFail, 3000);
                        return;
                    }
                    var pushedCount = Number((j && j.pushed_count) || 0);
                    var skippedCount = Number((j && j.skipped_count) || 0);
                    if (forwardPushHint) {
                        var sk2 = skippedCount > 0 ? sub(I.skippedPart, { n: skippedCount }) : '';
                        forwardPushHint.textContent = sub(I.forwardDone, { pushed: pushedCount, skipped: sk2 });
                    }
                    showToast(I.toastForwardDone, 2000);
                    if (forwardPushCodeInput) forwardPushCodeInput.value = '';
                    renderForwardPushRows([]);
                })
                .catch(function () {
                    if (forwardPushHint) forwardPushHint.textContent = I.submitFail;
                    showToast(I.networkRetry, 3000);
                });
        });
    }

    if (statusFixForm && statusFixTrackingNoInput && statusFixCustomerCodeInput) {
        statusFixForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var trackingNo = normalizeTrackingNo(String(statusFixTrackingNoInput.value || ''));
            var customerCode = String(statusFixCustomerCodeInput.value || '').trim();
            statusFixTrackingNoInput.value = trackingNo;
            statusFixCustomerCodeInput.value = customerCode;
            if (!trackingNo && !customerCode) {
                showToast(I.needTrackOrCode, 3000);
                statusFixTrackingNoInput.focus();
                return;
            }
            if (statusFixHint) statusFixHint.textContent = I.querying;
            var fd = new FormData();
            fd.append('status_fix_query', '1');
            fd.append('tracking_no', trackingNo);
            fd.append('customer_code', customerCode);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        renderStatusFixRows([]);
                        if (statusFixHint) statusFixHint.textContent = I.queryFail;
                        showToast((j && j.error) ? j.error : I.queryFail, 3000);
                        return;
                    }
                    var rows = Array.isArray(j.rows) ? j.rows : [];
                    renderStatusFixRows(rows);
                    var condText = [];
                    if (trackingNo) condText.push(sub(I.fixCondTrack, { v: trackingNo }));
                    if (customerCode) condText.push(sub(I.fixCondCode, { v: customerCode }));
                    var cond = condText.join('，');
                    if (statusFixHint) {
                        statusFixHint.textContent = rows.length > 0
                            ? sub(I.fixDone, { cond: cond, n: rows.length })
                            : sub(I.fixNone, { cond: cond });
                    }
                })
                .catch(function () {
                    renderStatusFixRows([]);
                    if (statusFixHint) statusFixHint.textContent = I.queryFail;
                    showToast(I.networkRetry, 3000);
                });
        });
    }

    if (statusFixSubmitBtn) {
        statusFixSubmitBtn.addEventListener('click', function () {
            var selects = statusFixTbody
                ? Array.prototype.slice.call(statusFixTbody.querySelectorAll('select[data-status-waybill-id]'))
                : [];
            if (selects.length <= 0) {
                showToast(I.queryOrdersFirst, 3000);
                return;
            }
            var updates = {};
            selects.forEach(function (sel) {
                var id = String(sel.getAttribute('data-status-waybill-id') || '');
                var oldStatus = String(sel.getAttribute('data-status-original') || '');
                var newStatus = String(sel.value || '');
                if (!id || !newStatus) return;
                if (newStatus === oldStatus) return;
                updates[id] = newStatus;
            });
            var changedCount = Object.keys(updates).length;
            if (changedCount <= 0) {
                showToast(I.noStatusChanges, 2500);
                return;
            }
            if (statusFixHint) statusFixHint.textContent = I.submitting;
            var fd = new FormData();
            fd.append('status_fix_submit', '1');
            fd.append('status_updates_json', JSON.stringify(updates));
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        if (statusFixHint) statusFixHint.textContent = I.submitFail;
                        showToast((j && j.error) ? j.error : I.submitFail, 3000);
                        return;
                    }
                    var updatedCount = Number((j && j.updated_count) || 0);
                    if (statusFixHint) statusFixHint.textContent = sub(I.fixSubmitDone, { n: updatedCount });
                    showToast(I.toastFixDone, 2000);
                    if (statusFixTrackingNoInput) statusFixTrackingNoInput.value = '';
                    if (statusFixCustomerCodeInput) statusFixCustomerCodeInput.value = '';
                    renderStatusFixRows([]);
                })
                .catch(function () {
                    if (statusFixHint) statusFixHint.textContent = I.submitFail;
                    showToast(I.networkRetry, 3000);
                });
        });
    }

    if (toast) {
        toast.addEventListener('click', function (e) {
            if (e.target === toast) closeToast();
        });
    }

    if (form && input) {
        ensureCainiaoReady()
            .then(function () {
                if (cnpStatus) cnpStatus.textContent = I.cnpOk;
            })
            .catch(function (e) {
                var em = (e && e.message) ? String(e.message) : I.unknownStatus;
                if (cnpStatus) cnpStatus.textContent = sub(I.cnpFailStatus, { msg: em });
            });
    }
})();
</script>
