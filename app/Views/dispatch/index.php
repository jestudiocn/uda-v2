<?php
/** @var bool $schemaReady */
/** @var bool $ordersSchemaV2 */
/** @var string $migrationHint */
/** @var array $rows */
/** @var array $consigningOptions */
/** @var array $filterResolved */
/** @var string $message */
/** @var string $error */
/** @var bool $showOrderImportLink */
/** @var bool $hideConsigningSelectors */
/** @var bool $dispatchBoundClientMissing */
/** @var bool $canWaybillEdit */
/** @var bool $canWaybillDelete */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var int $totalPages */
/** @var list<string> $orderStatusCatalog */

$qTrack = (string)($_GET['q_track'] ?? '');
$qCustomerCode = (string)($_GET['q_customer_code'] ?? '');
$qWechat = (string)($_GET['q_wechat'] ?? '');
$qInbound = (string)($_GET['q_inbound'] ?? '');
$qStatus = (string)($_GET['q_status'] ?? '');
$qScanDate = (string)($_GET['q_scan_date'] ?? '');
$consigningUiId = isset($consigningUiId) ? (int)$consigningUiId : (int)($_GET['consigning_client_id'] ?? 0);
$orderStatusCatalog = $orderStatusCatalog ?? [];
$ordersSchemaV2 = $ordersSchemaV2 ?? true;
$migrationHint = $migrationHint ?? '';
$filterResolved = $filterResolved ?? ['id' => 0, 'must_select' => false, 'single' => false];
$hideConsigningSelectors = $hideConsigningSelectors ?? false;
$dispatchBoundClientMissing = $dispatchBoundClientMissing ?? false;
$showOrderImportLink = $showOrderImportLink ?? false;
$canWaybillEdit = $canWaybillEdit ?? false;
$canWaybillDelete = $canWaybillDelete ?? false;
$format2 = static function ($v): string {
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 2, '.', '');
};
$formatInt = static function ($v): string {
    if ($v === null || $v === '') return '';
    return (string)round((float)$v);
};
$statusChipClass = static function (string $status): string {
    $s = trim($status);
    if ($s === '' || $s === '—') return 'chip-order-default';
    $map = [
        '待入库' => 'chip-order-wait-inbound',
        '部分入库' => 'chip-order-partial-inbound',
        '已入库' => 'chip-order-inbound',
        '待自取' => 'chip-order-wait-pickup',
        '待转发' => 'chip-order-wait-forward',
        '已出库' => 'chip-order-outbound',
        '已自取' => 'chip-order-picked',
        '已转发' => 'chip-order-forwarded',
        '已派送' => 'chip-order-delivered',
        '问题件' => 'chip-order-issue',
    ];
    if (isset($map[$s])) return $map[$s];
    return 'chip-order-default';
};
?>
<style>
.chip-order-default { background:#e2e8f0; color:#334155; }
.chip-order-wait-inbound { background:#fef3c7; color:#b45309; } /* 待入库 */
.chip-order-partial-inbound { background:#fde68a; color:#92400e; } /* 部分入库 */
.chip-order-inbound { background:#dbeafe; color:#1d4ed8; }      /* 已入库 */
.chip-order-wait-pickup { background:#ede9fe; color:#6d28d9; }  /* 待自取 */
.chip-order-wait-forward { background:#fce7f3; color:#9d174d; } /* 待转发 */
.chip-order-outbound { background:#ffedd5; color:#c2410c; }     /* 已出库 */
.chip-order-picked { background:#dcfce7; color:#166534; }       /* 已自取 */
.chip-order-forwarded { background:#cffafe; color:#0e7490; }    /* 已转发 */
.chip-order-delivered { background:#d1fae5; color:#047857; }    /* 已派送 */
.chip-order-issue { background:#fee2e2; color:#b91c1c; }        /* 问题件 */
</style>
<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 订单查询</h2>
    <?php if ($hideConsigningSelectors): ?>
        <div class="muted">当前登录为<strong>委托派送客户账号</strong>，仅显示绑定委托客户的订单（无需再选委托客户）。原「面单列表」已合并到此页，<code>/dispatch/waybills</code> 会跳转本页。<?php if (!empty($showOrderImportLink)): ?>批量导入与手工录入请前往侧边栏「<a href="/dispatch/order-import">订单导入</a>」。<?php endif; ?>派送照片表在迁移 022 后可用。</div>
    <?php else: ?>
        <div class="muted">公司内部账号可查看全部委托客户的订单（原「面单列表」已合并到此页，旧地址 <code>/dispatch/waybills</code> 会自动跳转到本页）；可在筛选中按「委托客户」缩小范围。<?php if (!empty($showOrderImportLink)): ?>批量导入与手工录入请前往「<a href="/dispatch/order-import">订单导入</a>」。<?php endif; ?>派送照片表 <code>dispatch_waybill_photos</code> 在迁移 022 后可用。</div>
    <?php endif; ?>
</div>

<?php if (isset($schemaReady) && !$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">
        派送资料表尚未建立，请执行 <code>database/migrations/021_dispatch_core_tables.sql</code>。
    </div>
<?php else: ?>

<?php if (!$ordersSchemaV2 && $migrationHint !== ''): ?>
    <div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($migrationHint); ?></div>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($dispatchBoundClientMissing): ?>
    <div class="card" style="border-left:4px solid #dc2626;">账号已绑定委托客户 ID，但该客户在系统中不存在或已被删除。请联系管理员在「系统管理 → 用户管理」中修正绑定，或恢复该委托客户。</div>
<?php endif; ?>

<?php if (!$hideConsigningSelectors && $filterResolved['must_select']): ?>
    <div class="card" style="border-left:4px solid #ca8a04;">当前存在多个委托客户，查询前请在下方「委托客户」下拉框中选择其一。</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin:0 0 10px 0;">筛选</h3>
    <form method="get" style="display:grid;grid-template-columns:repeat(5,minmax(180px,1fr));gap:10px;align-items:end;">
        <div>
            <label for="q_track">原始单号</label>
            <input id="q_track" name="q_track" value="<?php echo htmlspecialchars($qTrack); ?>" maxlength="120" placeholder="模糊">
        </div>
        <div>
            <label for="q_customer_code">客户编码</label>
            <input id="q_customer_code" name="q_customer_code" value="<?php echo htmlspecialchars($qCustomerCode); ?>" maxlength="60" placeholder="面单上或主数据编号">
        </div>
        <div>
            <label for="q_wechat">客户微信号</label>
            <input id="q_wechat" name="q_wechat" value="<?php echo htmlspecialchars($qWechat); ?>" maxlength="120" placeholder="派送客户主数据">
        </div>
        <div>
            <label for="q_inbound">入库编码</label>
            <input id="q_inbound" name="q_inbound" value="<?php echo htmlspecialchars($qInbound); ?>" maxlength="100" placeholder="入库批次">
        </div>
        <?php if (!$hideConsigningSelectors): ?>
        <div>
            <label for="consigning_client_id">委托客户</label>
            <select id="consigning_client_id" name="consigning_client_id">
                <option value="0"><?php echo $filterResolved['single'] ? '（仅有一个客户，已默认）' : '请选择委托客户'; ?></option>
                <?php foreach ($consigningOptions as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>"<?php echo (int)$o['id'] === $consigningUiId ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)($o['client_code'] ?? '') . ' — ' . (string)($o['client_name'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($ordersSchemaV2): ?>
        <div>
            <label for="q_status">订单状态</label>
            <select id="q_status" name="q_status">
                <option value="">全部</option>
                <?php foreach ($orderStatusCatalog as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>"<?php echo $qStatus === $st ? ' selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="q_scan_date">扫描日期</label>
            <input id="q_scan_date" name="q_scan_date" type="date" value="<?php echo htmlspecialchars($qScanDate); ?>">
        </div>
        <?php endif; ?>
        <div>
            <label for="per_page">每页</label>
            <select id="per_page" name="per_page">
                <?php foreach ([20, 50, 100] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <button type="submit">查询</button>
            <a class="btn" href="/dispatch">重置</a>
            <?php
            $exportQuery = $_GET;
            $exportQuery['export'] = 'current';
            $exportUrl = '/dispatch?' . http_build_query($exportQuery);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars($exportUrl); ?>">导出</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if ($total > 0): ?>
        <div class="muted" style="margin-bottom:8px;">共 <?php echo (int)$total; ?> 条，第 <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?> 页</div>
    <?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table table-valign-middle">
            <thead>
                <tr>
                    <th style="min-width:130px;">原始面单号</th>
                    <th style="min-width:76px;">客户编码</th>
                    <th style="min-width:100px;"></th>
                    <th style="min-width:92px;">微信 / Line</th>
                    <th style="min-width:56px;text-align:right;">数量</th>
                    <th style="min-width:56px;text-align:right;">重量</th>
                    <th style="min-width:52px;text-align:right;">长</th>
                    <th style="min-width:52px;text-align:right;">宽</th>
                    <th style="min-width:52px;text-align:right;">高</th>
                    <th style="min-width:92px;">入库批次</th>
                    <th style="min-width:116px;">入库时间</th>
                    <th style="min-width:132px;">最后状态更新时间</th>
                    <th style="min-width:76px;">订单状态</th>
                    <?php if (!$hideConsigningSelectors): ?><th style="min-width:96px;">委托客户</th><?php endif; ?>
                    <?php if ($canWaybillDelete): ?><th style="min-width:52px;padding:4px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?php echo ($hideConsigningSelectors ? 13 : 14) + ($canWaybillDelete ? 1 : 0); ?>" class="muted"><?php echo $filterResolved['must_select'] ? '请选择委托客户后查询' : '暂无数据'; ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $wx = trim((string)($r['resolved_wechat'] ?? ''));
                    $ln = trim((string)($r['resolved_line'] ?? ''));
                    $wxLine = $wx === '' ? ($ln !== '' ? $ln : '—') : ($ln === '' ? $wx : ($wx . ' / ' . $ln));
                    $addrThF = trim((string)($r['addr_th_full'] ?? ''));
                    $addrEnF = trim((string)($r['addr_en_full'] ?? ''));
                    $geoDisp = '';
                    if (($r['latitude'] ?? null) !== null && ($r['longitude'] ?? null) !== null && (string)$r['latitude'] !== '' && (string)$r['longitude'] !== '') {
                        $geoDisp = rtrim(rtrim(sprintf('%.7f', (float)$r['latitude']), '0'), '.')
                            . ', '
                            . rtrim(rtrim(sprintf('%.7f', (float)$r['longitude']), '0'), '.');
                    }
                    $rp = trim((string)($r['route_primary'] ?? ''));
                    $rs = trim((string)($r['route_secondary'] ?? ''));
                    $rc = trim((string)($r['routes_combined'] ?? ''));
                    $routesDisp = $rc !== '' ? $rc : trim($rp . ($rp !== '' && $rs !== '' ? ' - ' : '') . $rs);
                    $en = trim((string)($r['community_name_en'] ?? ''));
                    $th = trim((string)($r['community_name_th'] ?? ''));
                    $communityDisp = ($en === '' && $th === '') ? '—' : (($en !== '' && $th !== '') ? ($en . ' / ' . $th) : ($en !== '' ? $en : $th));
                    $rowId = (int)($r['id'] ?? 0);
                    $code = (string)($r['delivery_customer_code'] ?? '');
                    $detailPayload = [
                        'customer_code' => $code,
                        'wechat_id' => $wx,
                        'line_id' => $ln,
                        'addr_th_full' => $addrThF,
                        'addr_en_full' => $addrEnF,
                        'geo_position' => $geoDisp,
                        'route_primary' => $rp,
                        'route_secondary' => $rs,
                        'routes_combined' => $routesDisp,
                        'community_name' => $communityDisp,
                        'latitude' => ($r['latitude'] ?? null),
                        'longitude' => ($r['longitude'] ?? null),
                    ];
                    ?>
                    <tr data-waybill-id="<?php echo $rowId; ?>">
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['original_tracking_no'] ?? '')); ?></td>
                        <td style="min-width:76px;">
                            <span class="dispatch-code-text"><?php echo htmlspecialchars($code !== '' ? $code : '—'); ?></span>
                        </td>
                        <td style="min-width:100px;white-space:nowrap;vertical-align:middle;">
                            <div class="dispatch-row-actions">
                            <?php if ($canWaybillEdit): ?>
                                <button type="button"
                                        class="btn btn-dispatch-round btn-dispatch-round--edit dispatch-customer-code-edit-toggle"
                                        data-waybill-id="<?php echo $rowId; ?>"
                                        title="修改客户编码">E</button>
                            <?php endif; ?>
                                <button type="button"
                                        class="btn btn-dispatch-round btn-dispatch-round--info dispatch-customer-detail-btn"
                                        data-detail="<?php echo htmlspecialchars(json_encode($detailPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                        title="查看客户详情">i</button>
                            </div>
                        </td>
                        <td class="cell-tip dispatch-wxline"><?php echo html_cell_tip_content($wxLine !== '—' ? $wxLine : ''); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($formatInt($r['quantity'] ?? '')); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($format2($r['weight_kg'] ?? '')); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($format2($r['length_cm'] ?? '')); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($format2($r['width_cm'] ?? '')); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($format2($r['height_cm'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['inbound_batch'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['scanned_at'] ?? ''))); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['delivered_at'] ?? ''))); ?></td>
                        <?php $orderStatusText = (string)($r['order_status'] ?? '—'); ?>
                        <td><span class="chip <?php echo $statusChipClass($orderStatusText); ?>"><?php echo htmlspecialchars($orderStatusText); ?></span></td>
                        <?php if (!$hideConsigningSelectors): ?>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['consigning_client_code'] ?? '') . ' ' . (string)($r['consigning_client_name'] ?? ''))); ?></td>
                        <?php endif; ?>
                        <?php if ($canWaybillDelete): ?>
                        <td style="text-align:center;vertical-align:middle;white-space:nowrap;">
                            <button type="button" class="btn btn-dispatch-round btn-dispatch-round--delete dispatch-waybill-delete-btn" data-waybill-id="<?php echo $rowId; ?>" title="删除订单">D</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    if ($totalPages > 1 && !$filterResolved['must_select']):
        $pgBase = $_GET;
        unset($pgBase['export']);
        $pgBase['per_page'] = (string)$perPage;
        if ($page > 1):
            $pgPrev = $pgBase;
            $pgPrev['page'] = (string)($page - 1);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars('/dispatch?' . http_build_query($pgPrev)); ?>">上一页</a>
        <?php endif;
        if ($page < $totalPages):
            $pgNext = $pgBase;
            $pgNext['page'] = (string)($page + 1);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars('/dispatch?' . http_build_query($pgNext)); ?>">下一页</a>
        <?php endif;
    endif; ?>
</div>

<div id="dispatchCustomerDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:680px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="dcd_close_btn_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;">派送客户详情</h3>
        <div class="form-grid" style="grid-template-columns:160px 1fr;">
            <label>客户编码</label><div id="dcd_customer_code">—</div>
            <label>微信</label><div id="dcd_wechat">—</div>
            <label>Line</label><div id="dcd_line">—</div>
            <label>完整泰文地址</label><div id="dcd_addr_th_full" style="white-space:pre-wrap;">—</div>
            <label>完整英文地址</label><div id="dcd_addr_en_full" style="white-space:pre-wrap;">—</div>
            <label>定位</label>
            <div>
                <span id="dcd_geo_text">—</span>
                <a id="dcd_geo_link" href="#" target="_blank" rel="noopener noreferrer" style="margin-left:8px;display:none;">定位到 Google Map</a>
            </div>
            <label>路线</label><div id="dcd_routes">—</div>
            <label>小区</label><div id="dcd_community">—</div>
            <label>客户需求</label><div id="dcd_requirements">（预留，后续接数据库字段）</div>
        </div>
        <div style="margin-top:12px;text-align:right;">
            <button type="button" class="btn" id="dcd_close_btn" style="background:#64748b;">关闭</button>
        </div>
    </div>
</div>

<div id="dispatchCustomerCodeEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:520px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="dcc_close_btn_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;">修改客编</h3>
        <input type="hidden" id="dcc_waybill_id" value="">
        <div class="form-grid" style="grid-template-columns:120px 1fr;">
            <label>客户编码</label>
            <input id="dcc_customer_code" type="text" maxlength="60" placeholder="留空可清除">
        </div>
        <div style="margin-top:12px;text-align:right;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn" id="dcc_close_btn" style="background:#64748b;">关闭</button>
            <button type="button" class="btn" id="dcc_save_btn">保存</button>
        </div>
    </div>
</div>

<script>
(function () {
    var listPath = window.location.pathname + window.location.search;
    var detailModal = document.getElementById('dispatchCustomerDetailModal');
    var codeEditModal = document.getElementById('dispatchCustomerCodeEditModal');
    function txt(v) { return (v === null || v === undefined || String(v).trim() === '') ? '—' : String(v); }
    function closeDetail() {
        if (detailModal) detailModal.style.display = 'none';
    }
    function closeCodeEdit() {
        if (codeEditModal) codeEditModal.style.display = 'none';
    }
    document.querySelectorAll('.dispatch-customer-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!detailModal) return;
            var payload = {};
            try { payload = JSON.parse(btn.getAttribute('data-detail') || '{}'); } catch (e) {}
            document.getElementById('dcd_customer_code').textContent = txt(payload.customer_code);
            document.getElementById('dcd_wechat').textContent = txt(payload.wechat_id);
            document.getElementById('dcd_line').textContent = txt(payload.line_id);
            document.getElementById('dcd_addr_th_full').textContent = txt(payload.addr_th_full);
            document.getElementById('dcd_addr_en_full').textContent = txt(payload.addr_en_full);
            document.getElementById('dcd_geo_text').textContent = txt(payload.geo_position);
            document.getElementById('dcd_routes').textContent = txt(payload.routes_combined);
            document.getElementById('dcd_community').textContent = txt(payload.community_name);
            var lat = payload.latitude;
            var lng = payload.longitude;
            var hasGeo = lat !== null && lat !== '' && lng !== null && lng !== '';
            var mapLink = document.getElementById('dcd_geo_link');
            if (hasGeo) {
                mapLink.style.display = 'inline';
                mapLink.href = 'https://maps.google.com/?q=' + encodeURIComponent(String(lat) + ',' + String(lng));
            } else {
                mapLink.style.display = 'none';
                mapLink.href = '#';
            }
            detailModal.style.display = 'flex';
        });
    });
    if (detailModal) {
        detailModal.addEventListener('click', function (e) {
            if (e.target === detailModal) closeDetail();
        });
    }
    var closeBtn = document.getElementById('dcd_close_btn');
    if (closeBtn) closeBtn.addEventListener('click', closeDetail);
    var closeBtnX = document.getElementById('dcd_close_btn_x');
    if (closeBtnX) closeBtnX.addEventListener('click', closeDetail);

    document.querySelectorAll('.dispatch-customer-code-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-waybill-id');
            var tr = document.querySelector('tr[data-waybill-id="' + id + '"]');
            if (!tr) return;
            var codeText = tr.querySelector('.dispatch-code-text');
            var oldCode = codeText ? codeText.textContent.trim() : '';
            document.getElementById('dcc_waybill_id').value = id;
            document.getElementById('dcc_customer_code').value = oldCode === '—' ? '' : oldCode;
            if (codeEditModal) codeEditModal.style.display = 'flex';
        });
    });

    var dccCloseBtn = document.getElementById('dcc_close_btn');
    if (dccCloseBtn) dccCloseBtn.addEventListener('click', closeCodeEdit);
    var dccCloseBtnX = document.getElementById('dcc_close_btn_x');
    if (dccCloseBtnX) dccCloseBtnX.addEventListener('click', closeCodeEdit);
    if (codeEditModal) {
        codeEditModal.addEventListener('click', function (e) {
            if (e.target === codeEditModal) closeCodeEdit();
        });
    }

    var dccSaveBtn = document.getElementById('dcc_save_btn');
    if (dccSaveBtn) dccSaveBtn.addEventListener('click', function () {
            var id = document.getElementById('dcc_waybill_id').value;
            var code = document.getElementById('dcc_customer_code').value.trim();
            var fd = new FormData();
            fd.append('waybill_customer_code_update', '1');
            fd.append('waybill_id', id);
            fd.append('delivery_customer_code', code);
            fetch(listPath, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        alert((j && j.error) ? j.error : '更新失败');
                        return;
                    }
                    var row = j.row || null;
                    var tr = document.querySelector('tr[data-waybill-id="' + id + '"]');
                    if (!row || !tr) {
                        window.location.reload();
                        return;
                    }
                    var newCode = row.delivery_customer_code || '';
                    var wx = row.wechat_id || '';
                    var ln = row.line_id || '';
                    var wxLine = wx ? (ln ? (wx + ' / ' + ln) : wx) : (ln || '—');
                    var codeText = tr.querySelector('.dispatch-code-text');
                    if (codeText) {
                        codeText.textContent = newCode || '—';
                    }
                    var detailBtn = tr.querySelector('.dispatch-customer-detail-btn');
                    if (detailBtn) {
                        var addrTh = row.addr_th_full || '';
                        var addrEn = row.addr_en_full || '';
                        var lat = row.latitude;
                        var lng = row.longitude;
                        var geoText = '—';
                        if (lat !== null && lat !== '' && lng !== null && lng !== '') {
                            geoText = String(lat) + ', ' + String(lng);
                        }
                        var rp = row.route_primary || '';
                        var rs = row.route_secondary || '';
                        var rc = row.routes_combined || '';
                        var routes = rc || (rp && rs ? (rp + ' - ' + rs) : (rp || rs || '—'));
                        var en = row.community_name_en || '';
                        var th = row.community_name_th || '';
                        var community = (!en && !th) ? '—' : (en && th ? (en + ' / ' + th) : (en || th));
                        var payload = {
                            customer_code: newCode,
                            wechat_id: wx,
                            line_id: ln,
                            addr_th_full: addrTh,
                            addr_en_full: addrEn,
                            geo_position: geoText === '—' ? '' : geoText,
                            route_primary: rp,
                            route_secondary: rs,
                            routes_combined: routes,
                            community_name: community,
                            latitude: lat,
                            longitude: lng
                        };
                        detailBtn.setAttribute('data-detail', JSON.stringify(payload));
                    }
                    var wxCell = tr.querySelector('.dispatch-wxline');
                    if (wxCell) wxCell.textContent = wxLine;
                    closeCodeEdit();
                })
                .catch(function () {
                    alert('网络错误');
                });
    });

    document.querySelectorAll('.dispatch-waybill-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('确认删除该订单？此操作不可恢复。')) {
                return;
            }
            var id = btn.getAttribute('data-waybill-id');
            var fd = new FormData();
            fd.append('waybill_delete', '1');
            fd.append('waybill_id', id);
            fetch(listPath, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) {
                        alert((j && j.error) ? j.error : '删除失败');
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    alert('网络错误');
                });
        });
    });
})();
</script>

<?php endif; ?>
