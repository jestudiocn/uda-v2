<?php

/** @var bool $schemaReady */

/** @var list<array<string,mixed>> $rows */

/** @var string $error */

$schemaReady = $schemaReady ?? false;

$rows = $rows ?? [];

$error = (string)($error ?? '');

require_once __DIR__ . '/../../inc/view_cell_tip.php';

$schemaErr = t('dispatch.view.common.schema_not_ready', '数据表未就绪');

?>

<div class="card">

    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.title', '派送业务 / 派送操作 / 可派送列表')); ?></h2>

    <div class="muted"><?php echo t('dispatch.view.ops_delivery_list.subtitle', '按派送客户聚合展示。派送件数=该客户当前“已入库”订单的数量汇总（SUM(quantity)）。含重货时（单票重量&gt;20kg 或 长/宽/高至少两项&gt;70cm）在客户编码与微信/Line 之间显示黑色「H」，点开可查看该客户当前全部可派送订单明细。'); ?></div>

</div>

<?php if (!$schemaReady): ?>

    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : $schemaErr); ?></div>

    <?php return; ?>

<?php endif; ?>

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

    <div style="overflow:auto;">

        <table class="data-table">

            <thead>

                <tr>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_code', '客户编码')); ?></th>

                    <th class="dl-heavy-col-th" aria-label="<?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_heavy', '重货')); ?>"></th>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_wxline', '微信/Line号')); ?></th>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_qty', '派送件数')); ?></th>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_comm_en', '英文小区')); ?></th>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_comm_th', '泰文小区')); ?></th>

                    <th style="width:240px;max-width:240px;"><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_addr', '完整英文地址')); ?></th>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_route', '主/副线路')); ?></th>

                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.th_geo', '定位')); ?></th>

                </tr>

            </thead>

            <tbody>

                <?php if ($rows === []): ?>

                    <tr><td colspan="9" class="muted"><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.empty', '暂无可派送客户（当前无已入库货件）')); ?></td></tr>

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

                                    <button type="button" class="btn-heavy-h" title="<?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.btn_heavy_title', '重货：查看该客户可派送订单')); ?>" data-cc="<?php echo htmlspecialchars($custCode, ENT_QUOTES, 'UTF-8'); ?>" data-orders="<?php echo $ordersJson; ?>">H</button>

                                <?php else: ?>

                                    <span class="muted" style="display:inline-block;width:16px;height:16px;vertical-align:middle;">&nbsp;</span>

                                <?php endif; ?>

                            </td>

                            <td><?php echo htmlspecialchars($wxLine); ?></td>

                            <td><?php echo (int)round((float)($r['inbound_count'] ?? 0)); ?></td>

                            <td><?php echo htmlspecialchars((string)($r['community_name_en'] ?? '')); ?></td>

                            <td><?php echo htmlspecialchars((string)($r['community_name_th'] ?? '')); ?></td>

                            <td class="cell-tip" style="width:240px;max-width:240px;"><?php echo html_cell_tip_content((string)($r['addr_en_full'] ?? '')); ?></td>

                            <td><?php echo htmlspecialchars($routeText); ?></td>

                            <td>

                                <?php if ($hasGeo): ?>

                                    <a href="https://maps.google.com/?q=<?php echo urlencode((string)$geoText); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($geoText); ?></a>

                                <?php else: ?>

                                    <span class="muted">-</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<div id="dl-heavy-modal" role="dialog" aria-modal="true" aria-labelledby="dl-heavy-title">

    <div class="dl-heavy-inner">

        <button type="button" class="dl-heavy-close" aria-label="<?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.modal_close', '关闭')); ?>">&times;</button>

        <h3 id="dl-heavy-title"><?php echo htmlspecialchars(t('dispatch.view.ops_delivery_list.modal_title', '可派送订单明细')); ?></h3>

        <div id="dl-heavy-body"></div>

    </div>

</div>

<script>window.__dispatchDlI18n=<?php echo json_encode([
    'dash' => t('dispatch.view.common.dash', '—'),
    'modalTitle' => t('dispatch.view.ops_delivery_list.modal_title', '可派送订单明细'),
    'modalTitleCc' => t('dispatch.view.ops_delivery_list.modal_title_cc', '可派送订单明细（客户编码 %s）'),
    'thTrack' => t('dispatch.view.ops_delivery_list.modal_th_track', '原始单号'),
    'thQty' => t('dispatch.view.ops_delivery_list.modal_th_qty', '数量'),
    'thW' => t('dispatch.view.ops_delivery_list.modal_th_w', '重量(kg)'),
    'thL' => t('dispatch.view.ops_delivery_list.modal_th_l', '长(cm)'),
    'thWd' => t('dispatch.view.ops_delivery_list.modal_th_wd', '宽(cm)'),
    'thH' => t('dispatch.view.ops_delivery_list.modal_th_h', '高(cm)'),
    'empty' => t('dispatch.view.ops_delivery_list.modal_empty', '暂无订单数据'),
], JSON_UNESCAPED_UNICODE); ?>;</script>

<script>

(function () {

    var I = window.__dispatchDlI18n || {};

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

        if (v === null || v === undefined || v === '') return (I.dash || '—');

        var n = Number(v);

        if (!isFinite(n)) return esc(String(v));

        if (Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n));

        return String(Math.round(n * 100) / 100);

    }

    function openModal(cc, orders) {

        var tpl = I.modalTitleCc || '';

        titleEl.textContent = tpl.indexOf('%s') >= 0 ? tpl.replace('%s', cc || (I.dash || '—')) : ((I.modalTitle || '') + '（' + (cc || (I.dash || '—')) + '）');

        var rows = Array.isArray(orders) ? orders : [];

        var html = '<table class="data-table"><thead><tr>'

            + '<th>' + esc(I.thTrack || '') + '</th><th>' + esc(I.thQty || '') + '</th><th>' + esc(I.thW || '') + '</th><th>' + esc(I.thL || '') + '</th><th>' + esc(I.thWd || '') + '</th><th>' + esc(I.thH || '') + '</th>'

            + '</tr></thead><tbody>';

        if (rows.length === 0) {

            html += '<tr><td colspan="6" class="muted">' + esc(I.empty || '') + '</td></tr>';

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
