<?php
/** @var bool $ordersSchemaV2 */
/** @var string $migrationHint */
/** @var list<string> $orderStatusCatalog */
/** @var bool $canArrivalScan */
/** @var bool $canSelfPickup */
/** @var bool $canForwardPush */
/** @var bool $canStatusFix */
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
    <h2 style="margin:0 0 6px 0;">派送业务 / 货件操作</h2>
    <div class="muted">三项功能均支持独立权限控制；无权限的功能不会显示。</div>
</div>

<?php if (!$ordersSchemaV2): ?>
    <div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($migrationHint); ?></div>
<?php endif; ?>

<?php if (!empty($canArrivalScan)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">1) 到件扫描</h3>
    <div class="muted" style="margin-bottom:10px;">扫描枪输入后自动回车即可执行；手工输入时按回车执行。若末尾为 <code>@数字</code>，系统会自动去除后再匹配原始单号。</div>
    <form id="arrivalScanForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="arrival_scan_submit" value="1">
        <label for="arrival_tracking_no" style="margin:0;">单号</label>
        <input
            id="arrival_tracking_no"
            name="tracking_no"
            type="text"
            autocomplete="off"
            autofocus
            placeholder="请扫描或输入原始单号"
            style="min-width:320px;"
        >
        <button type="submit">执行</button>
    </form>
    <div class="muted" id="cnp_status" style="margin-top:8px;">菜鸟状态：检测中...</div>
    <div class="muted" id="arrival_hint" style="margin-top:8px;">等待扫描...</div>
</div>
<?php endif; ?>

<?php if (!empty($canSelfPickup)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">2) 自取录入</h3>
    <div class="muted" style="margin-bottom:10px;">输入客户编码后，带出该客户可自取订单（排除：已派送、待入库/部分入库/未入库、问题件、已出库、已自取、已转发、待转发）。勾选并提交后，订单状态改为「已自取」。</div>
    <form id="selfPickupQueryForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="self_pickup_query" value="1">
        <label for="self_pickup_customer_code" style="margin:0;">客户编码</label>
        <input id="self_pickup_customer_code" name="customer_code" type="text" autocomplete="off" placeholder="请输入客户编码" style="min-width:260px;">
        <button type="submit">查询订单</button>
    </form>
    <div id="self_pickup_hint" class="muted" style="margin-top:8px;">等待查询...</div>
    <div id="self_pickup_ops" style="margin-top:8px;display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="checkbox" id="self_pickup_select_all">
            <span>全选</span>
        </label>
        <strong>总件数：<span id="self_pickup_total">0</span></strong>
        <strong>已勾选：<span id="self_pickup_checked">0</span></strong>
        <button type="button" id="self_pickup_submit_btn">勾选送出（改为已自取）</button>
    </div>
    <div style="margin-top:8px;overflow:auto;max-height:340px;">
        <table class="data-table" id="self_pickup_table" style="display:none;min-width:760px;">
            <thead>
                <tr>
                    <th style="width:56px;">选择</th>
                    <th>原始单号</th>
                    <th>当前状态</th>
                    <th>扫描时间</th>
                    <th>单号ID</th>
                </tr>
            </thead>
            <tbody id="self_pickup_tbody"></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($canForwardPush)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">3) 手动推送待转发</h3>
    <div class="muted" style="margin-bottom:10px;">输入客户编码后，带出该客户当前「已入库」订单。默认全选，可取消部分后推送；提交后状态改为「待转发」。</div>
    <form id="forwardPushQueryForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="forward_push_query" value="1">
        <label for="forward_push_customer_code" style="margin:0;">客户编码</label>
        <input id="forward_push_customer_code" name="customer_code" type="text" autocomplete="off" placeholder="请输入客户编码" style="min-width:260px;">
        <button type="submit">查询订单</button>
    </form>
    <div id="forward_push_hint" class="muted" style="margin-top:8px;">等待查询...</div>
    <div id="forward_push_ops" style="margin-top:8px;display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="checkbox" id="forward_push_select_all">
            <span>全选</span>
        </label>
        <strong>总件数：<span id="forward_push_total">0</span></strong>
        <strong>已勾选：<span id="forward_push_checked">0</span></strong>
        <button type="button" id="forward_push_submit_btn">勾选送出（改为待转发）</button>
    </div>
    <div style="margin-top:8px;overflow:auto;max-height:340px;">
        <table class="data-table" id="forward_push_table" style="display:none;min-width:760px;">
            <thead>
                <tr>
                    <th style="width:56px;">选择</th>
                    <th>原始单号</th>
                    <th>当前状态</th>
                    <th>扫描时间</th>
                    <th>单号ID</th>
                </tr>
            </thead>
            <tbody id="forward_push_tbody"></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($canStatusFix)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">4) 货件状态修正</h3>
    <div class="muted" style="margin-bottom:10px;">可按原始单号、客户代码单独查询，也可两者同时作为多条件查询；在状态下拉中选择新状态后送出，直接更改订单状态。</div>
    <form id="statusFixQueryForm" method="post" action="/dispatch/package-ops" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="status_fix_query" value="1">
        <label for="status_fix_tracking_no" style="margin:0;">原始单号</label>
        <input id="status_fix_tracking_no" name="tracking_no" type="text" autocomplete="off" placeholder="可模糊查询" style="min-width:240px;">
        <label for="status_fix_customer_code" style="margin:0;">客户代码</label>
        <input id="status_fix_customer_code" name="customer_code" type="text" autocomplete="off" placeholder="可精确查询" style="min-width:220px;">
        <button type="submit">查询</button>
    </form>
    <div id="status_fix_hint" class="muted" style="margin-top:8px;">等待查询...</div>
    <div id="status_fix_ops" style="margin-top:8px;display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <strong>匹配件数：<span id="status_fix_total">0</span></strong>
        <button type="button" id="status_fix_submit_btn">送出状态修正</button>
    </div>
    <div style="margin-top:8px;overflow:auto;max-height:360px;">
        <table class="data-table" id="status_fix_table" style="display:none;min-width:900px;">
            <thead>
                <tr>
                    <th>原始单号</th>
                    <th>客户编码</th>
                    <th>当前状态</th>
                    <th>修正状态</th>
                    <th>扫描时间</th>
                    <th>单号ID</th>
                </tr>
            </thead>
            <tbody id="status_fix_tbody"></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="arrivalToast" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.28);z-index:10060;align-items:center;justify-content:center;">
    <div style="min-width:220px;max-width:min(86vw,420px);background:#fff;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.22);padding:16px 18px;">
        <div id="arrivalToastMsg" style="font-size:16px;font-weight:700;color:#111827;">提示</div>
    </div>
</div>

<script>
(function () {
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
    var orderStatusCatalog = <?php echo json_encode(array_values(array_filter(array_map('strval', (array)($orderStatusCatalog ?? [])))), JSON_UNESCAPED_UNICODE); ?>;
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
                    reject(new Error(lastErr || '无法连接菜鸟打印服务'));
                    return;
                }
                var ws = null;
                var url = urls[i++];
                try {
                    ws = new WebSocket(url);
                } catch (e) {
                    next('创建连接失败');
                    return;
                }
                ws.onopen = function () { resolve(ws); };
                ws.onerror = function () {
                    try { ws.close(); } catch (e) {}
                    next('连接失败');
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
                reject(new Error('菜鸟请求超时'));
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
        if (!rsp) return '菜鸟无返回';
        if (rsp.msg) return String(rsp.msg);
        if (rsp.detail) return String(rsp.detail);
        if (rsp.printStatus && Array.isArray(rsp.printStatus) && rsp.printStatus.length > 0) {
            var first = rsp.printStatus[0] || {};
            if (first.msg) return String(first.msg);
            if (first.detail) return String(first.detail);
            if (first.status) return String(first.status);
        }
        return '菜鸟打印返回失败';
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
                + '<td>' + escapeHtml(r.order_status || '') + '</td>'
                + '<td>' + escapeHtml(r.scanned_at || '—') + '</td>'
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
                + '<td>' + escapeHtml(r.order_status || '') + '</td>'
                + '<td>' + escapeHtml(r.scanned_at || '—') + '</td>'
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
                return '<option value="' + escapeHtml(s) + '"' + selected + '>' + escapeHtml(s) + '</option>';
            }).join('');
            if (opts.indexOf(' selected') < 0 && current) {
                opts = '<option value="' + escapeHtml(current) + '" selected>' + escapeHtml(current) + '</option>' + opts;
            }
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td class="cell-tip">' + escapeHtml(r.original_tracking_no || '') + '</td>'
                + '<td>' + escapeHtml(r.customer_code || '—') + '</td>'
                + '<td>' + escapeHtml(current || '—') + '</td>'
                + '<td><select data-status-waybill-id="' + escapeHtml(r.id) + '" data-status-original="' + escapeHtml(current) + '">' + opts + '</select></td>'
                + '<td>' + escapeHtml(r.scanned_at || '—') + '</td>'
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
            var normalized = normalizeTrackingNo(input.value || '');
            input.value = normalized;
            if (!normalized) {
                showToast('请输入或扫描单号', 3000);
                input.focus();
                return;
            }
            isBusy = true;
            if (hint) hint.textContent = '处理中...';
            var fd = new FormData();
            fd.append('arrival_scan_submit', '1');
            fd.append('tracking_no', normalized);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) {
                        var pdfMode = String((j && j.pdf_mode) || '');
                        var pdfDebug = String((j && j.pdf_debug) || '');
                        if (hint) hint.textContent = '扫描成功：' + (j.tracking_no || normalized) + '（状态：已入库' + (pdfMode ? '，PDF: ' + pdfMode : '') + '）';
                        var pdfUrl = String((j && j.pdf_url) || '');
                        if (!pdfUrl) {
                            if (hint) hint.textContent = '处理失败：未生成 PDF 地址';
                            showToast('未生成 PDF', 3000);
                            return;
                        }
                        printArrivalPdfByCainiao(pdfUrl)
                            .then(function () {
                                if (hint) hint.textContent = '已发送到菜鸟组件直打' + (pdfMode ? '（PDF: ' + pdfMode + (pdfDebug ? ' / ' + pdfDebug : '') + '）' : '');
                            })
                            .catch(function (e) {
                                if (hint) hint.textContent = '菜鸟打印失败：' + (e && e.message ? e.message : '未知错误');
                                showToast('菜鸟打印失败', 3000);
                            });
                    } else {
                        var code = (j && j.code) ? j.code : '';
                        if (code === 'no_customer_code') {
                            if (hint) hint.textContent = '扫描完成：' + (j.tracking_no || normalized) + '（状态：问题件）';
                            showToast('无客户编码', 3000);
                        } else if (code === 'order_near_or_done') {
                            var blockedStatus = ((j && j.status) ? String(j.status) : '未知');
                            if (hint) hint.textContent = '扫描拦截：' + (j.tracking_no || normalized) + '（状态：' + blockedStatus + '）';
                            showToast('订单即将完成或已完成（当前：' + blockedStatus + '）', 3000);
                        } else if (code === 'no_waybill') {
                            if (hint) hint.textContent = '未匹配到单号：' + normalized;
                            showToast('无此单号', 3000);
                        } else {
                            if (hint) hint.textContent = '处理失败';
                            showToast((j && j.error) ? j.error : '处理失败', 3000);
                        }
                    }
                })
                .catch(function () {
                    if (hint) hint.textContent = '处理失败';
                    showToast('网络错误，请重试', 3000);
                })
                .finally(function () {
                    isBusy = false;
                    input.value = '';
                    input.focus();
                });
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
                showToast('请输入客户编码', 3000);
                selfPickupCodeInput.focus();
                return;
            }
            if (selfPickupHint) selfPickupHint.textContent = '查询中...';
            var fd = new FormData();
            fd.append('self_pickup_query', '1');
            fd.append('customer_code', code);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        renderSelfPickupRows([]);
                        if (selfPickupHint) selfPickupHint.textContent = '查询失败';
                        showToast((j && j.error) ? j.error : '查询失败', 3000);
                        return;
                    }
                    var rows = Array.isArray(j.rows) ? j.rows : [];
                    renderSelfPickupRows(rows);
                    if (selfPickupHint) {
                        selfPickupHint.textContent = rows.length > 0
                            ? ('客户编码 ' + code + ' 查询完成，共 ' + rows.length + ' 件可自取订单')
                            : ('客户编码 ' + code + ' 没有可自取订单');
                    }
                })
                .catch(function () {
                    renderSelfPickupRows([]);
                    if (selfPickupHint) selfPickupHint.textContent = '查询失败';
                    showToast('网络错误，请重试', 3000);
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
                showToast('请先输入客户编码并查询', 3000);
                return;
            }
            if (picked.length <= 0) {
                showToast('请先勾选订单', 3000);
                return;
            }
            if (selfPickupHint) selfPickupHint.textContent = '提交中...';
            var fd = new FormData();
            fd.append('self_pickup_submit', '1');
            fd.append('customer_code', code);
            picked.forEach(function (id) { fd.append('waybill_ids[]', id); });
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        if (selfPickupHint) selfPickupHint.textContent = '提交失败';
                        showToast((j && j.error) ? j.error : '提交失败', 3000);
                        return;
                    }
                    var pickedCount = Number((j && j.picked_count) || 0);
                    var skippedCount = Number((j && j.skipped_count) || 0);
                    if (selfPickupHint) {
                        selfPickupHint.textContent = '提交完成：已改为已自取 ' + pickedCount + ' 件' + (skippedCount > 0 ? ('，跳过 ' + skippedCount + ' 件') : '');
                    }
                    showToast('自取录入完成', 2000);
                    if (selfPickupCodeInput) selfPickupCodeInput.value = '';
                    renderSelfPickupRows([]);
                })
                .catch(function () {
                    if (selfPickupHint) selfPickupHint.textContent = '提交失败';
                    showToast('网络错误，请重试', 3000);
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
                showToast('请输入客户编码', 3000);
                forwardPushCodeInput.focus();
                return;
            }
            if (forwardPushHint) forwardPushHint.textContent = '查询中...';
            var fd = new FormData();
            fd.append('forward_push_query', '1');
            fd.append('customer_code', code);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        renderForwardPushRows([]);
                        if (forwardPushHint) forwardPushHint.textContent = '查询失败';
                        showToast((j && j.error) ? j.error : '查询失败', 3000);
                        return;
                    }
                    var rows = Array.isArray(j.rows) ? j.rows : [];
                    renderForwardPushRows(rows);
                    if (forwardPushHint) {
                        forwardPushHint.textContent = rows.length > 0
                            ? ('客户编码 ' + code + ' 查询完成，共 ' + rows.length + ' 件已入库订单')
                            : ('客户编码 ' + code + ' 没有可推送订单');
                    }
                })
                .catch(function () {
                    renderForwardPushRows([]);
                    if (forwardPushHint) forwardPushHint.textContent = '查询失败';
                    showToast('网络错误，请重试', 3000);
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
                showToast('请先输入客户编码并查询', 3000);
                return;
            }
            if (picked.length <= 0) {
                showToast('请先勾选订单', 3000);
                return;
            }
            if (forwardPushHint) forwardPushHint.textContent = '提交中...';
            var fd = new FormData();
            fd.append('forward_push_submit', '1');
            fd.append('customer_code', code);
            picked.forEach(function (id) { fd.append('waybill_ids[]', id); });
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        if (forwardPushHint) forwardPushHint.textContent = '提交失败';
                        showToast((j && j.error) ? j.error : '提交失败', 3000);
                        return;
                    }
                    var pushedCount = Number((j && j.pushed_count) || 0);
                    var skippedCount = Number((j && j.skipped_count) || 0);
                    if (forwardPushHint) {
                        forwardPushHint.textContent = '提交完成：已推送待转发 ' + pushedCount + ' 件' + (skippedCount > 0 ? ('，跳过 ' + skippedCount + ' 件') : '');
                    }
                    showToast('手动推送待转发完成', 2000);
                    if (forwardPushCodeInput) forwardPushCodeInput.value = '';
                    renderForwardPushRows([]);
                })
                .catch(function () {
                    if (forwardPushHint) forwardPushHint.textContent = '提交失败';
                    showToast('网络错误，请重试', 3000);
                });
        });
    }

    if (statusFixForm && statusFixTrackingNoInput && statusFixCustomerCodeInput) {
        statusFixForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var trackingNo = String(statusFixTrackingNoInput.value || '').trim();
            var customerCode = String(statusFixCustomerCodeInput.value || '').trim();
            statusFixTrackingNoInput.value = trackingNo;
            statusFixCustomerCodeInput.value = customerCode;
            if (!trackingNo && !customerCode) {
                showToast('请至少输入原始单号或客户代码之一', 3000);
                statusFixTrackingNoInput.focus();
                return;
            }
            if (statusFixHint) statusFixHint.textContent = '查询中...';
            var fd = new FormData();
            fd.append('status_fix_query', '1');
            fd.append('tracking_no', trackingNo);
            fd.append('customer_code', customerCode);
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        renderStatusFixRows([]);
                        if (statusFixHint) statusFixHint.textContent = '查询失败';
                        showToast((j && j.error) ? j.error : '查询失败', 3000);
                        return;
                    }
                    var rows = Array.isArray(j.rows) ? j.rows : [];
                    renderStatusFixRows(rows);
                    var condText = [];
                    if (trackingNo) condText.push('原始单号=' + trackingNo);
                    if (customerCode) condText.push('客户代码=' + customerCode);
                    var cond = condText.join('，');
                    if (statusFixHint) {
                        statusFixHint.textContent = rows.length > 0
                            ? (cond + ' 查询完成，共 ' + rows.length + ' 条匹配订单')
                            : (cond + ' 未匹配到订单');
                    }
                })
                .catch(function () {
                    renderStatusFixRows([]);
                    if (statusFixHint) statusFixHint.textContent = '查询失败';
                    showToast('网络错误，请重试', 3000);
                });
        });
    }

    if (statusFixSubmitBtn) {
        statusFixSubmitBtn.addEventListener('click', function () {
            var selects = statusFixTbody
                ? Array.prototype.slice.call(statusFixTbody.querySelectorAll('select[data-status-waybill-id]'))
                : [];
            if (selects.length <= 0) {
                showToast('请先查询订单', 3000);
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
                showToast('没有状态变更项', 2500);
                return;
            }
            if (statusFixHint) statusFixHint.textContent = '提交中...';
            var fd = new FormData();
            fd.append('status_fix_submit', '1');
            fd.append('status_updates_json', JSON.stringify(updates));
            fetch('/dispatch/package-ops', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        if (statusFixHint) statusFixHint.textContent = '提交失败';
                        showToast((j && j.error) ? j.error : '提交失败', 3000);
                        return;
                    }
                    var updatedCount = Number((j && j.updated_count) || 0);
                    if (statusFixHint) statusFixHint.textContent = '状态修正完成：成功更新 ' + updatedCount + ' 条';
                    showToast('货件状态修正完成', 2000);
                    if (statusFixTrackingNoInput) statusFixTrackingNoInput.value = '';
                    if (statusFixCustomerCodeInput) statusFixCustomerCodeInput.value = '';
                    renderStatusFixRows([]);
                })
                .catch(function () {
                    if (statusFixHint) statusFixHint.textContent = '提交失败';
                    showToast('网络错误，请重试', 3000);
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
                if (cnpStatus) cnpStatus.textContent = '菜鸟状态：已连接';
            })
            .catch(function (e) {
                if (cnpStatus) cnpStatus.textContent = '菜鸟状态：连接失败（' + (e && e.message ? e.message : '未知错误') + '）';
            });
    }
})();
</script>
