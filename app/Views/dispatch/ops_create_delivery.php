<?php

/** @var bool $schemaReady */

/** @var list<array<string,mixed>> $rows */

/** @var string $error */

/** @var string $message */

/** @var string $selectedLine */

/** @var string $selectedDate */

/** @var string $generatedDocNo */

$schemaReady = $schemaReady ?? false;

$rows = $rows ?? [];

$error = (string)($error ?? '');

$message = (string)($message ?? '');

$selectedLine = (string)($selectedLine ?? 'A');

$selectedDate = (string)($selectedDate ?? date('Y-m-d'));

$generatedDocNo = (string)($generatedDocNo ?? '');

?>



<div class="card">

    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 分配派送单</h2>

    <div class="muted">先生成派送单号（预计派送日期 + 派送线），再勾选下方客户生成派送单。提交成功后将自动进入「初步派送单列表」并提示本单号。含重货时（单票重量&gt;20kg 或 长/宽/高至少两项&gt;70cm）在客户编码与微信/Line 之间显示黑色「H」，点开可查看该客户当前全部可派送订单明细。</div>

</div>



<?php if (!$schemaReady): ?>

    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>

    <?php return; ?>

<?php endif; ?>



<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>



<style>

    .dl-heavy-col{width:28px;max-width:32px;padding:4px 2px!important;text-align:center;white-space:nowrap;vertical-align:middle;}

    .dl-heavy-col-th{width:28px;max-width:32px;padding:4px 2px!important;}

    .btn-heavy-h{

        box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;

        width:16px;height:16px;min-width:16px;min-height:16px;padding:0;margin:0;

        background:#000;color:#fff;border:0;border-radius:2px;

        font-weight:800;font-size:10px;letter-spacing:0;cursor:pointer;

        line-height:1;font-family:inherit;flex-shrink:0;

    }

    .btn-heavy-h:hover{background:#1f2937;}

    .btn-heavy-h:focus{outline:2px solid #2563eb;outline-offset:2px;}

    #dl-heavy-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10050;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}

    #dl-heavy-modal .dl-heavy-inner{background:#fff;border-radius:12px;max-width:920px;width:100%;max-height:85vh;overflow:auto;padding:16px 18px;box-shadow:0 10px 40px rgba(0,0,0,.2);position:relative;}

    #dl-heavy-modal h3{margin:0 0 10px;font-size:1.05rem;}

    #dl-heavy-modal .dl-heavy-close{position:absolute;top:10px;right:12px;border:0;background:transparent;font-size:1.4rem;line-height:1;cursor:pointer;color:#64748b;padding:4px 8px;}

    #dl-heavy-modal .dl-heavy-close:hover{color:#0f172a;}

    #dl-heavy-modal table.data-table{margin-top:8px;}

</style>



<div class="card">

    <form method="post" id="deliveryDocForm">

        <input type="hidden" name="action" value="create_delivery_doc">

        <div class="form-grid" style="grid-template-columns:repeat(4,minmax(180px,1fr));gap:10px;align-items:end;">

            <div>

                <label>派送线</label>

                <select name="dispatch_line" id="dispatch_line">

                    <?php foreach (['A','B','C','D','E'] as $line): ?>

                        <option value="<?php echo $line; ?>"<?php echo $selectedLine === $line ? ' selected' : ''; ?>><?php echo $line; ?></option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div>

                <label>预计派送日期</label>

                <input type="date" name="planned_delivery_date" id="planned_delivery_date" value="<?php echo htmlspecialchars($selectedDate); ?>">

            </div>

            <div>

                <label>派送单号</label>

                <input type="text" name="delivery_doc_no" id="delivery_doc_no" readonly value="<?php echo htmlspecialchars($generatedDocNo); ?>" placeholder="点击右侧按钮生成">

            </div>

            <div class="inline-actions">

                <button type="button" id="btnGenNo">生成派送单号</button>

            </div>

        </div>



        <div style="margin-top:12px;overflow:auto;">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>客户编码</th>

                        <th class="dl-heavy-col-th" aria-label="重货"></th>

                        <th>微信/Line号</th>

                        <th>派送件数</th>

                        <th>泰文小区</th>

                        <th>完整泰文地址</th>

                        <th>主/副线路</th>

                        <th>定位</th>

                        <th style="width:72px;text-align:center;">

                            <input type="checkbox" id="checkAll">

                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if ($rows === []): ?>

                        <tr><td colspan="9" class="muted">暂无可绑定客户（当前无已入库货件）</td></tr>

                    <?php else: ?>

                        <?php foreach ($rows as $r): ?>

                            <?php

                            $wx = trim((string)($r['wechat_id'] ?? ''));

                            $ln = trim((string)($r['line_id'] ?? ''));

                            $wxLine = $wx === '' ? ($ln === '' ? '-' : $ln) : ($ln === '' ? $wx : ($wx . ' / ' . $ln));

                            $rp = trim((string)($r['route_primary'] ?? ''));

                            $rs = trim((string)($r['route_secondary'] ?? ''));

                            $routeText = $rp !== '' || $rs !== '' ? ($rp . '/' . $rs) : '-';

                            $lat = $r['latitude'] ?? null;

                            $lng = $r['longitude'] ?? null;

                            $hasGeo = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';

                            $geoText = $hasGeo ? (rtrim(rtrim(sprintf('%.7f', (float)$lat), '0'), '.') . ',' . rtrim(rtrim(sprintf('%.7f', (float)$lng), '0'), '.')) : '-';

                            $heavyHint = !empty($r['heavy_cargo_hint']);

                            $popupOrders = $r['heavy_cargo_popup_orders'] ?? [];

                            if (!is_array($popupOrders)) {

                                $popupOrders = [];

                            }

                            $ordersJson = htmlspecialchars(json_encode($popupOrders, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

                            $custCode = (string)($r['customer_code'] ?? '');

                            ?>

                            <tr>

                                <td><?php echo htmlspecialchars($custCode); ?></td>

                                <td class="dl-heavy-col">

                                    <?php if ($heavyHint): ?>

                                        <button type="button" class="btn-heavy-h" title="重货：查看该客户可派送订单" data-cc="<?php echo htmlspecialchars($custCode, ENT_QUOTES, 'UTF-8'); ?>" data-orders="<?php echo $ordersJson; ?>">H</button>

                                    <?php else: ?>

                                        <span class="muted" style="display:inline-block;width:16px;height:16px;vertical-align:middle;">&nbsp;</span>

                                    <?php endif; ?>

                                </td>

                                <td><?php echo htmlspecialchars($wxLine); ?></td>

                                <td><?php echo (int)($r['inbound_count'] ?? 0); ?></td>

                                <td><?php echo htmlspecialchars((string)($r['community_name_th'] ?? '')); ?></td>

                                <td><?php echo htmlspecialchars((string)($r['addr_th_full'] ?? '')); ?></td>

                                <td><?php echo htmlspecialchars($routeText); ?></td>

                                <td><?php echo htmlspecialchars($geoText); ?></td>

                                <td style="text-align:center;">

                                    <input type="checkbox" name="delivery_customer_ids[]" value="<?php echo (int)($r['id'] ?? 0); ?>" class="row-check">

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="inline-actions" style="margin-top:12px;">

            <button type="submit">生成初步派送单</button>

        </div>

    </form>

</div>



<div id="dl-heavy-modal" role="dialog" aria-modal="true" aria-labelledby="dl-heavy-title">

    <div class="dl-heavy-inner">

        <button type="button" class="dl-heavy-close" aria-label="关闭">&times;</button>

        <h3 id="dl-heavy-title">可派送订单明细</h3>

        <div id="dl-heavy-body"></div>

    </div>

</div>



<script>

(function () {

    var btnGenNo = document.getElementById('btnGenNo');

    var lineEl = document.getElementById('dispatch_line');

    var dateEl = document.getElementById('planned_delivery_date');

    var noEl = document.getElementById('delivery_doc_no');

    var checkAll = document.getElementById('checkAll');

    function genNo() {

        var line = (lineEl && lineEl.value ? String(lineEl.value) : '').trim().toUpperCase();

        var d = dateEl && dateEl.value ? String(dateEl.value) : '';

        if (!line || !d) return '';

        return d.replace(/-/g, '') + '-' + line;

    }

    if (btnGenNo) {

        btnGenNo.addEventListener('click', function () {

            var no = genNo();

            if (!no) {

                alert('请先选择派送线和预计派送日期');

                return;

            }

            if (noEl) noEl.value = no;

        });

    }

    if (checkAll) {

        checkAll.addEventListener('change', function () {

            var checked = !!checkAll.checked;

            document.querySelectorAll('.row-check').forEach(function (el) { el.checked = checked; });

        });

    }

})();

</script>

<script>

(function () {

    var modal = document.getElementById('dl-heavy-modal');

    var bodyEl = document.getElementById('dl-heavy-body');

    var titleEl = document.getElementById('dl-heavy-title');

    if (!modal || !bodyEl || !titleEl) return;



    function esc(s) {

        var d = document.createElement('div');

        d.textContent = s == null ? '' : String(s);

        return d.innerHTML;

    }



    function fmtNum(v) {

        if (v === null || v === undefined || v === '') return '—';

        var n = Number(v);

        if (!isFinite(n)) return esc(String(v));

        if (Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n));

        return String(Math.round(n * 100) / 100);

    }



    function openModal(cc, orders) {

        titleEl.textContent = '可派送订单明细（客户编码 ' + (cc || '—') + '）';

        var rows = Array.isArray(orders) ? orders : [];

        var html = '<table class="data-table"><thead><tr>'

            + '<th>原始单号</th><th>数量</th><th>重量(kg)</th><th>长(cm)</th><th>宽(cm)</th><th>高(cm)</th>'

            + '</tr></thead><tbody>';

        if (rows.length === 0) {

            html += '<tr><td colspan="6" class="muted">暂无订单数据</td></tr>';

        } else {

            rows.forEach(function (o) {

                html += '<tr>'

                    + '<td>' + esc(o.original_tracking_no) + '</td>'

                    + '<td>' + fmtNum(o.quantity) + '</td>'

                    + '<td>' + fmtNum(o.weight_kg) + '</td>'

                    + '<td>' + fmtNum(o.length_cm) + '</td>'

                    + '<td>' + fmtNum(o.width_cm) + '</td>'

                    + '<td>' + fmtNum(o.height_cm) + '</td>'

                    + '</tr>';

            });

        }

        html += '</tbody></table>';

        bodyEl.innerHTML = html;

        modal.style.display = 'flex';

        document.body.style.overflow = 'hidden';

    }



    function closeModal() {

        modal.style.display = 'none';

        document.body.style.overflow = '';

        bodyEl.innerHTML = '';

    }



    document.querySelectorAll('.btn-heavy-h').forEach(function (btn) {

        btn.addEventListener('click', function () {

            var raw = btn.getAttribute('data-orders') || '[]';

            var orders;

            try { orders = JSON.parse(raw); } catch (e) { orders = []; }

            openModal(btn.getAttribute('data-cc') || '', orders);

        });

    });



    modal.querySelector('.dl-heavy-close').addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {

        if (e.target === modal) closeModal();

    });

    document.addEventListener('keydown', function (e) {

        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();

    });

})();

</script>



