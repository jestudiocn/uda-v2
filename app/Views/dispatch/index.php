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
/** @var bool $driverFilterSchemaOk */
/** @var list<array{id:int,label:string}> $assignedDriverOptions */
/** @var int $qDriverId */

$qTrack = (string)($_GET['q_track'] ?? '');
$qCustomerCode = (string)($_GET['q_customer_code'] ?? '');
$qWechat = (string)($_GET['q_wechat'] ?? '');
$qInbound = (string)($_GET['q_inbound'] ?? '');
$qStatus = (string)($_GET['q_status'] ?? '');
$qDeliveredFrom = (string)($_GET['q_delivered_from'] ?? '');
$qDeliveredTo = (string)($_GET['q_delivered_to'] ?? '');
$qImportFrom = (string)($_GET['q_import_from'] ?? '');
$qImportTo = (string)($_GET['q_import_to'] ?? '');
$consigningUiId = isset($consigningUiId) ? (int)$consigningUiId : (int)($_GET['consigning_client_id'] ?? 0);
$orderStatusCatalog = $orderStatusCatalog ?? [];
$driverFilterSchemaOk = $driverFilterSchemaOk ?? false;
$assignedDriverOptions = $assignedDriverOptions ?? [];
$qDriverId = isset($qDriverId) ? (int)$qDriverId : (int)($_GET['q_driver_id'] ?? 0);
$ordersSchemaV2 = $ordersSchemaV2 ?? true;
$migrationHint = $migrationHint ?? '';
$filterResolved = $filterResolved ?? ['id' => 0, 'must_select' => false, 'single' => false];
$hideConsigningSelectors = $hideConsigningSelectors ?? false;
$dispatchBoundClientMissing = $dispatchBoundClientMissing ?? false;
$showOrderImportLink = $showOrderImportLink ?? false;
$canWaybillEdit = $canWaybillEdit ?? false;
$canWaybillDelete = $canWaybillDelete ?? false;
$showConsigningColumn = !$hideConsigningSelectors && !(bool)($filterResolved['single'] ?? false);
$dash = t('dispatch.view.common.dash', '—');
$format2 = static function ($v): string {
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 2, '.', '');
};
$formatInt = static function ($v): string {
    if ($v === null || $v === '') return '';
    return (string)round((float)$v);
};
$statusChipClass = static function (string $status) use ($dash): string {
    $s = trim($status);
    if ($s === '' || $s === '—' || $s === $dash) return 'chip-order-default';
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
$orderStatusLabel = static function (string $s) use ($dash): string {
    $trim = trim($s);
    if ($trim === '' || $s === '—') {
        return $dash;
    }
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
.chip-order-status-btn {
  cursor: pointer;
  user-select: none;
}
.chip-order-status-btn:focus-visible {
  outline: 2px solid #64748b;
  outline-offset: 2px;
}
.chip-order-size {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-weight: 700;
  background: #475569;
  color: transparent;
  font-size: 0;
  line-height: 1.35;
  cursor: pointer;
  user-select: none;
}
.chip-order-size::before {
  content: 'LWH';
  color: #f8fafc;
  font-size: 11px;
}
.chip-order-size:hover { text-decoration: none; }
.chip-order-size:focus-visible {
  outline: 2px solid #94a3b8;
  outline-offset: 2px;
}
.pod-thumb {
  max-width: 200px;
  max-height: 200px;
  width: auto;
  height: auto;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  cursor: zoom-in;
  object-fit: contain;
  background: #fff;
}
.dispatch-order-filter-wrap { width: 100%; max-width: 100%; overflow-x: auto; padding-bottom: 2px; box-sizing: border-box; }
.dispatch-order-filter-form { display: flex; flex-direction: column; gap: 10px; width: 100%; max-width: 100%; box-sizing: border-box; }
.dispatch-order-filter-row {
  display: flex; flex-wrap: nowrap; align-items: flex-end; gap: 10px 14px;
  width: 100%; box-sizing: border-box;
}
.dispatch-order-filter-row label { display: block; font-size: 12px; margin-bottom: 4px; color: #475569; }
/* 第一行：各条件等分拉满可用宽度 */
.dispatch-order-filter-row--fields > div {
  flex: 1 1 0;
  min-width: 0;
}
.dispatch-order-filter-row--fields input[type="text"],
.dispatch-order-filter-row--fields select {
  width: 100%;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}
/* 第二行：两组日期间隔拉伸，每页固定宽，按钮靠右 */
.dispatch-order-filter-row--dates .dispatch-order-filter-delivered-pair {
  flex: 1 1 0;
  min-width: 17rem;
}
.dispatch-order-filter-delivered-pair {
  display: flex; flex-wrap: nowrap; align-items: flex-end; gap: 8px;
}
.dispatch-order-filter-delivered-pair > div {
  flex: 1 1 0;
  min-width: 0;
}
.dispatch-order-filter-row--dates .dispatch-order-filter-delivered-pair input[type="date"] {
  width: 100%;
  min-width: 9.25rem;
  max-width: 100%;
  box-sizing: border-box;
}
.dispatch-order-filter-delivered-pair span { flex: 0 0 auto; padding-bottom: 6px; color: #64748b; font-size: 13px; }
.dispatch-order-filter-per-page { flex: 0 0 auto; }
.dispatch-order-filter-per-page select { min-width: 4.5rem; width: 100%; box-sizing: border-box; }
.dispatch-order-filter-actions {
  display: flex; flex-wrap: nowrap; gap: 8px; align-items: center;
  flex: 0 0 auto; margin-left: auto;
}
</style>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('dispatch.view.index.title', '派送业务 / 订单查询')); ?></h2>
    <?php if ($hideConsigningSelectors): ?>
        <div class="muted"><?php echo t('dispatch.view.index.subtitle_client', '当前登录为<strong>委托派送客户账号</strong>，仅显示绑定委托客户的订单（无需再选委托客户）。原「面单列表」已合并到此页，<code>/dispatch/waybills</code> 会跳转本页。'); ?><?php if (!empty($showOrderImportLink)): ?><?php echo t('dispatch.view.index.subtitle_client_import', '批量导入与手工录入请前往侧边栏「<a href="/dispatch/order-import">订单导入</a>」。'); ?><?php endif; ?><?php echo t('dispatch.view.index.subtitle_client_pod', '派送照片表在迁移 022 后可用。'); ?></div>
    <?php else: ?>
        <div class="muted"><?php echo t('dispatch.view.index.subtitle_staff', '公司内部账号可查看全部委托客户的订单（原「面单列表」已合并到此页，旧地址 <code>/dispatch/waybills</code> 会自动跳转到本页）；可在筛选中按「委托客户」缩小范围。'); ?><?php if (!empty($showOrderImportLink)): ?><?php echo t('dispatch.view.index.subtitle_staff_import', '批量导入与手工录入请前往「<a href="/dispatch/order-import">订单导入</a>」。'); ?><?php endif; ?><?php echo t('dispatch.view.index.subtitle_staff_pod', '派送照片表 <code>dispatch_waybill_photos</code> 在迁移 022 后可用。'); ?></div>
    <?php endif; ?>
</div>

<?php if (isset($schemaReady) && !$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">
        <?php echo t('dispatch.view.index.schema', '派送资料表尚未建立，请执行 <code>database/migrations/021_dispatch_core_tables.sql</code>。'); ?>
    </div>
<?php else: ?>

<?php if (!$ordersSchemaV2 && $migrationHint !== ''): ?>
    <div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($migrationHint); ?></div>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($dispatchBoundClientMissing): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('dispatch.view.index.bound_missing', '账号已绑定委托客户 ID，但该客户在系统中不存在或已被删除。请联系管理员在「系统管理 → 用户管理」中修正绑定，或恢复该委托客户。')); ?></div>
<?php endif; ?>

<?php if (!$hideConsigningSelectors && $filterResolved['must_select']): ?>
    <div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars(t('dispatch.view.index.must_select_consigning', '当前存在多个委托客户，查询前请在下方「委托客户」下拉框中选择其一。')); ?></div>
<?php endif; ?>

<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.index.filter_title', '筛选')); ?></h3>
    <div class="dispatch-order-filter-wrap">
    <form method="get" class="dispatch-order-filter-form">
        <div class="dispatch-order-filter-row dispatch-order-filter-row--fields">
        <div>
            <label for="q_track"><?php echo htmlspecialchars(t('dispatch.view.index.label_track', '原始单号')); ?></label>
            <input id="q_track" name="q_track" value="<?php echo htmlspecialchars($qTrack); ?>" maxlength="120" placeholder="<?php echo htmlspecialchars(t('dispatch.view.index.ph_fuzzy', '模糊')); ?>">
        </div>
        <div>
            <label for="q_customer_code"><?php echo htmlspecialchars(t('dispatch.view.index.label_cust_code', '客户编码')); ?></label>
            <input id="q_customer_code" name="q_customer_code" value="<?php echo htmlspecialchars($qCustomerCode); ?>" maxlength="60" placeholder="<?php echo htmlspecialchars(t('dispatch.view.index.ph_cust_code', '面单上或主数据编号')); ?>">
        </div>
        <div>
            <label for="q_wechat"><?php echo htmlspecialchars(t('dispatch.view.index.label_wechat', '客户微信号')); ?></label>
            <input id="q_wechat" name="q_wechat" value="<?php echo htmlspecialchars($qWechat); ?>" maxlength="120" placeholder="<?php echo htmlspecialchars(t('dispatch.view.index.ph_wechat', '派送客户主数据')); ?>">
        </div>
        <div>
            <label for="q_inbound"><?php echo htmlspecialchars(t('dispatch.view.index.label_inbound', '入库编码')); ?></label>
            <input id="q_inbound" name="q_inbound" value="<?php echo htmlspecialchars($qInbound); ?>" maxlength="100" placeholder="<?php echo htmlspecialchars(t('dispatch.view.index.ph_inbound', '入库批次')); ?>">
        </div>
        <?php if (!$hideConsigningSelectors && empty($filterResolved['single'])): ?>
        <div>
            <label for="consigning_client_id"><?php echo htmlspecialchars(t('dispatch.view.index.label_consigning', '委托客户')); ?></label>
            <select id="consigning_client_id" name="consigning_client_id">
                <option value="0"><?php echo htmlspecialchars(t('dispatch.view.index.opt_consigning', '请选择委托客户')); ?></option>
                <?php foreach ($consigningOptions as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>"<?php echo (int)$o['id'] === $consigningUiId ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)($o['client_code'] ?? '') . ' ' . $dash . ' ' . (string)($o['client_name'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($ordersSchemaV2): ?>
        <div>
            <label for="q_status"><?php echo htmlspecialchars(t('dispatch.view.index.label_status', '订单状态')); ?></label>
            <select id="q_status" name="q_status">
                <option value=""><?php echo htmlspecialchars(t('dispatch.view.index.opt_status_all', '全部')); ?></option>
                <?php foreach ($orderStatusCatalog as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>"<?php echo $qStatus === $st ? ' selected' : ''; ?>><?php echo htmlspecialchars($orderStatusLabel($st)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($driverFilterSchemaOk): ?>
        <div>
            <label for="q_driver_id"><?php echo htmlspecialchars(t('dispatch.view.index.label_assigned_driver', '指派司机')); ?></label>
            <select id="q_driver_id" name="q_driver_id">
                <option value="0"><?php echo htmlspecialchars(t('dispatch.view.index.opt_driver_all', '全部')); ?></option>
                <?php foreach ($assignedDriverOptions as $drv): ?>
                    <option value="<?php echo (int)$drv['id']; ?>"<?php echo (int)$drv['id'] === $qDriverId ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)($drv['label'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        </div>
        <div class="dispatch-order-filter-row dispatch-order-filter-row--dates">
        <?php if ($ordersSchemaV2): ?>
        <div class="dispatch-order-filter-delivered-pair">
            <div>
                <label for="q_delivered_from"><?php echo htmlspecialchars(t('dispatch.view.index.label_delivered_range', '日期间隔（最后状态更新）')); ?></label>
                <input id="q_delivered_from" name="q_delivered_from" type="date" value="<?php echo htmlspecialchars($qDeliveredFrom); ?>" title="<?php echo htmlspecialchars(t('dispatch.view.index.title_delivered_from', '含当日，按 delivered_at 日期')); ?>">
            </div>
            <span><?php echo htmlspecialchars(t('dispatch.view.index.sep_range', '～')); ?></span>
            <div>
                <label for="q_delivered_to"><?php echo htmlspecialchars(t('dispatch.view.index.label_to', '至')); ?></label>
                <input id="q_delivered_to" name="q_delivered_to" type="date" value="<?php echo htmlspecialchars($qDeliveredTo); ?>" title="<?php echo htmlspecialchars(t('dispatch.view.index.title_delivered_to', '含当日')); ?>">
            </div>
        </div>
        <div class="dispatch-order-filter-delivered-pair">
            <div>
                <label for="q_import_from"><?php echo htmlspecialchars(t('dispatch.view.index.label_import_range', '日期间隔（导入）')); ?></label>
                <input id="q_import_from" name="q_import_from" type="date" value="<?php echo htmlspecialchars($qImportFrom); ?>" title="<?php echo htmlspecialchars(t('dispatch.view.index.title_import_from', '含当日，按 import_date（导入日期）')); ?>">
            </div>
            <span><?php echo htmlspecialchars(t('dispatch.view.index.sep_range', '～')); ?></span>
            <div>
                <label for="q_import_to"><?php echo htmlspecialchars(t('dispatch.view.index.label_to', '至')); ?></label>
                <input id="q_import_to" name="q_import_to" type="date" value="<?php echo htmlspecialchars($qImportTo); ?>" title="<?php echo htmlspecialchars(t('dispatch.view.index.title_delivered_to', '含当日')); ?>">
            </div>
        </div>
        <?php endif; ?>
        <div class="dispatch-order-filter-per-page">
            <label for="per_page"><?php echo htmlspecialchars(t('dispatch.view.index.label_per_page', '每页')); ?></label>
            <select id="per_page" name="per_page">
                <?php foreach ([20, 50, 100] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="dispatch-order-filter-actions">
            <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.index.btn_query', '查询')); ?></button>
            <a class="btn" href="/dispatch"><?php echo htmlspecialchars(t('dispatch.view.index.btn_reset', '重置')); ?></a>
            <?php
            $exportQuery = $_GET;
            $exportQuery['export'] = 'current';
            $exportUrl = '/dispatch?' . http_build_query($exportQuery);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars($exportUrl); ?>"><?php echo htmlspecialchars(t('dispatch.view.index.btn_export', '导出')); ?></a>
        </div>
        </div>
    </form>
    </div>
</div>

<div class="card">
    <?php if ($total > 0): ?>
        <div class="muted" style="margin-bottom:8px;"><?php echo htmlspecialchars(sprintf(t('dispatch.view.index.summary', '共 %d 条，第 %d / %d 页'), (int)$total, (int)$page, (int)$totalPages)); ?></div>
    <?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table table-valign-middle">
            <thead>
                <tr>
                    <th style="min-width:130px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_original', '原始面单号')); ?></th>
                    <th style="min-width:76px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_cust_code', '客户编码')); ?></th>
                    <th style="min-width:100px;"></th>
                    <th style="min-width:92px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_wxline', '微信 / Line')); ?></th>
                    <th style="min-width:56px;text-align:right;"><?php echo htmlspecialchars(t('dispatch.view.index.th_qty', '数量')); ?></th>
                    <th style="min-width:56px;text-align:right;"><?php echo htmlspecialchars(t('dispatch.view.index.th_weight', '重量')); ?></th>
                    <th style="min-width:68px;text-align:center;"><?php echo htmlspecialchars(t('dispatch.view.index.th_size', '尺寸')); ?></th>
                    <th style="min-width:92px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_batch', '入库批次')); ?></th>
                    <th style="min-width:116px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_scan_at', '入库时间')); ?></th>
                    <th style="min-width:132px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_delivered_at', '最后状态更新时间')); ?></th>
                    <th style="min-width:76px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_order_status', '订单状态')); ?></th>
                    <th style="min-width:116px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_planned', '预计派送日期')); ?></th>
                    <?php if ($showConsigningColumn): ?><th style="min-width:96px;"><?php echo htmlspecialchars(t('dispatch.view.index.th_consigning', '委托客户')); ?></th><?php endif; ?>
                    <?php if ($canWaybillDelete): ?><th style="min-width:52px;padding:4px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?php echo ($showConsigningColumn ? 13 : 12) + ($canWaybillDelete ? 1 : 0); ?>" class="muted"><?php echo htmlspecialchars($filterResolved['must_select'] ? t('dispatch.view.index.empty_must_select', '请选择委托客户后查询') : t('dispatch.view.index.empty_no_data', '暂无数据')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $wx = trim((string)($r['resolved_wechat'] ?? ''));
                    $ln = trim((string)($r['resolved_line'] ?? ''));
                    $wxLine = $wx === '' ? ($ln !== '' ? $ln : $dash) : ($ln === '' ? $wx : ($wx . ' / ' . $ln));
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
                    $communityDisp = ($en === '' && $th === '') ? $dash : (($en !== '' && $th !== '') ? ($en . ' / ' . $th) : ($en !== '' ? $en : $th));
                    $lenDisp = $format2($r['length_cm'] ?? '');
                    $widDisp = $format2($r['width_cm'] ?? '');
                    $heiDisp = $format2($r['height_cm'] ?? '');
                    $sizeDetail = ($lenDisp === '' && $widDisp === '' && $heiDisp === '')
                        ? $dash
                        : (($lenDisp === '' ? $dash : $lenDisp) . '*' . ($widDisp === '' ? $dash : $widDisp) . '*' . ($heiDisp === '' ? $dash : $heiDisp));
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
                            <span class="dispatch-code-text"><?php echo htmlspecialchars($code !== '' ? $code : $dash); ?></span>
                        </td>
                        <td style="min-width:100px;white-space:nowrap;vertical-align:middle;">
                            <div class="dispatch-row-actions">
                            <?php if ($canWaybillEdit): ?>
                                <button type="button"
                                        class="btn btn-dispatch-round btn-dispatch-round--edit dispatch-customer-code-edit-toggle"
                                        data-waybill-id="<?php echo $rowId; ?>"
                                        title="<?php echo htmlspecialchars(t('dispatch.view.index.title_edit_code', '修改客户编码')); ?>">E</button>
                            <?php endif; ?>
                                <button type="button"
                                        class="btn btn-dispatch-round btn-dispatch-round--info dispatch-customer-detail-btn"
                                        data-detail="<?php echo htmlspecialchars(json_encode($detailPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                        title="<?php echo htmlspecialchars(t('dispatch.view.index.title_detail', '查看客户详情')); ?>">i</button>
                            </div>
                        </td>
                        <td class="cell-tip dispatch-wxline"><?php echo html_cell_tip_content($wxLine !== $dash ? $wxLine : ''); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($formatInt($r['quantity'] ?? '')); ?></td>
                        <td class="cell-tip" style="text-align:right;"><?php echo html_cell_tip_content($format2($r['weight_kg'] ?? '')); ?></td>
                        <td style="text-align:center;">
                            <?php if ($sizeDetail === $dash): ?>
                                <span class="muted"><?php echo htmlspecialchars($dash); ?></span>
                            <?php else: ?>
                                <span class="cell-tip-trigger chip-order-size" role="button" tabindex="0"><?php echo htmlspecialchars($sizeDetail, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['inbound_batch'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['scanned_at'] ?? ''))); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['delivered_at'] ?? ''))); ?></td>
                        <?php
                        $orderStatusText = (string)($r['order_status'] ?? '—');
                        $docNoPod = trim((string)($r['delivery_doc_no'] ?? ''));
                        $podCustomerCode = trim((string)($r['resolved_customer_code'] ?? ''));
                        if ($podCustomerCode === '') {
                            $podCustomerCode = trim((string)($r['delivery_customer_code'] ?? ''));
                        }
                        $podP1 = trim((string)($r['pod_photo_1'] ?? ''));
                        $podP2 = trim((string)($r['pod_photo_2'] ?? ''));
                        $deliveredAtDisp = trim((string)($r['delivered_at'] ?? ''));
                        $clickDelivered = $ordersSchemaV2
                            && $orderStatusText === '已派送'
                            && $docNoPod !== ''
                            && $podCustomerCode !== '';
                        ?>
                        <td><?php if ($clickDelivered): ?>
                            <?php
                            $podPayload = [
                                'time' => $deliveredAtDisp,
                                'driver_name' => trim((string)($r['delivery_driver_name'] ?? '')),
                                'url1' => $podP1 !== '' ? ('/dispatch/delivery-pod-photo?' . http_build_query(['f' => $podP1])) : '',
                                'url2' => $podP2 !== '' ? ('/dispatch/delivery-pod-photo?' . http_build_query(['f' => $podP2])) : '',
                                'has1' => $podP1 !== '',
                                'has2' => $podP2 !== '',
                            ];
                            ?>
                            <span class="chip <?php echo $statusChipClass($orderStatusText); ?> chip-order-status-btn" role="button" tabindex="0" data-delivered-detail="<?php echo htmlspecialchars(json_encode($podPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($orderStatusLabel($orderStatusText)); ?></span>
                        <?php else: ?>
                            <span class="chip <?php echo $statusChipClass($orderStatusText); ?>"><?php echo htmlspecialchars($orderStatusLabel($orderStatusText)); ?></span>
                        <?php endif; ?></td>
                        <td class="cell-tip"><?php echo $orderStatusText === '已派送' ? '' : html_cell_tip_content(trim((string)($r['planned_delivery_date'] ?? ''))); ?></td>
                        <?php if ($showConsigningColumn): ?>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['consigning_client_code'] ?? '') . ' ' . (string)($r['consigning_client_name'] ?? ''))); ?></td>
                        <?php endif; ?>
                        <?php if ($canWaybillDelete): ?>
                        <td style="text-align:center;vertical-align:middle;white-space:nowrap;">
                            <button type="button" class="btn btn-dispatch-round btn-dispatch-round--delete dispatch-waybill-delete-btn" data-waybill-id="<?php echo $rowId; ?>" title="<?php echo htmlspecialchars(t('dispatch.view.index.title_delete', '删除订单')); ?>">D</button>
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
            <a class="btn" href="<?php echo htmlspecialchars('/dispatch?' . http_build_query($pgPrev)); ?>"><?php echo htmlspecialchars(t('dispatch.view.index.btn_prev', '上一页')); ?></a>
        <?php endif;
        if ($page < $totalPages):
            $pgNext = $pgBase;
            $pgNext['page'] = (string)($page + 1);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars('/dispatch?' . http_build_query($pgNext)); ?>"><?php echo htmlspecialchars(t('dispatch.view.index.btn_next', '下一页')); ?></a>
        <?php endif;
    endif; ?>
</div>

<div id="dispatchCustomerDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:680px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="dcd_close_btn_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;"><?php echo htmlspecialchars(t('dispatch.view.index.detail_title', '派送客户详情')); ?></h3>
        <div class="form-grid" style="grid-template-columns:160px 1fr;">
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_code', '客户编码')); ?></label><div id="dcd_customer_code"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_wx', '微信')); ?></label><div id="dcd_wechat"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_line', 'Line')); ?></label><div id="dcd_line"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_addr_th', '完整泰文地址')); ?></label><div id="dcd_addr_th_full" style="white-space:pre-wrap;"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_addr_en', '完整英文地址')); ?></label><div id="dcd_addr_en_full" style="white-space:pre-wrap;"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_geo', '定位')); ?></label>
            <div>
                <span id="dcd_geo_text"><?php echo htmlspecialchars($dash); ?></span>
                <a id="dcd_geo_link" href="#" target="_blank" rel="noopener noreferrer" style="margin-left:8px;display:none;"><?php echo htmlspecialchars(t('dispatch.view.index.detail_map_link', '定位到 Google Map')); ?></a>
            </div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_route', '路线')); ?></label><div id="dcd_routes"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_comm', '小区')); ?></label><div id="dcd_community"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.detail_label_req', '客户需求')); ?></label><div id="dcd_requirements"><?php echo htmlspecialchars(t('dispatch.view.index.detail_req_placeholder', '（预留，后续接数据库字段）')); ?></div>
        </div>
        <div style="margin-top:12px;text-align:right;">
            <button type="button" class="btn" id="dcd_close_btn" style="background:#64748b;"><?php echo htmlspecialchars(t('dispatch.view.index.btn_close', '关闭')); ?></button>
        </div>
    </div>
</div>

<div id="dispatchCustomerCodeEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:520px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="dcc_close_btn_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;"><?php echo htmlspecialchars(t('dispatch.view.index.edit_code_title', '修改客编')); ?></h3>
        <input type="hidden" id="dcc_waybill_id" value="">
        <div class="form-grid" style="grid-template-columns:120px 1fr;">
            <label><?php echo htmlspecialchars(t('dispatch.view.index.edit_code_label', '客户编码')); ?></label>
            <input id="dcc_customer_code" type="text" maxlength="60" placeholder="<?php echo htmlspecialchars(t('dispatch.view.index.edit_code_ph', '留空可清除')); ?>">
        </div>
        <div style="margin-top:12px;text-align:right;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn" id="dcc_close_btn" style="background:#64748b;"><?php echo htmlspecialchars(t('dispatch.view.index.btn_close', '关闭')); ?></button>
            <button type="button" class="btn" id="dcc_save_btn"><?php echo htmlspecialchars(t('dispatch.view.index.edit_code_save', '保存')); ?></button>
        </div>
    </div>
</div>

<div id="dispatchDeliveredDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:640px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="ddd_close_btn_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;"><?php echo htmlspecialchars(t('dispatch.view.index.delivered_title', '派送完成信息')); ?></h3>
        <div class="form-grid" style="grid-template-columns:160px 1fr;">
            <label><?php echo htmlspecialchars(t('dispatch.view.index.delivered_time_label', '派送时间（最后状态更新时间）')); ?></label>
            <div id="ddd_time"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.delivered_driver', '派送司机')); ?></label>
            <div id="ddd_driver_name"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.index.delivered_photos', '签收照片')); ?></label>
            <div id="ddd_img_row" style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                <div id="ddd_img1_wrap"><?php echo htmlspecialchars($dash); ?></div>
                <div id="ddd_img2_wrap"><?php echo htmlspecialchars($dash); ?></div>
            </div>
        </div>
        <div style="margin-top:12px;text-align:right;">
            <button type="button" class="btn" id="ddd_close_btn" style="background:#64748b;"><?php echo htmlspecialchars(t('dispatch.view.index.btn_close', '关闭')); ?></button>
        </div>
    </div>
</div>

<div id="dispatchDeliveredImageModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:10000;align-items:center;justify-content:center;padding:16px;">
    <div style="position:relative;max-width:min(800px,92vw);max-height:min(800px,92vh);display:flex;align-items:center;justify-content:center;">
        <button type="button" id="ddi_close_btn_x" style="position:absolute;top:-10px;right:-2px;border:none;background:transparent;font-size:30px;line-height:1;color:#fff;cursor:pointer;padding:0 4px;">×</button>
        <img id="ddi_img" src="" alt="<?php echo htmlspecialchars(t('dispatch.view.index.img_alt_pod', '签收大图')); ?>" style="max-width:min(800px,92vw);max-height:min(800px,92vh);width:auto;height:auto;object-fit:contain;background:#fff;border-radius:10px;border:1px solid rgba(255,255,255,.4);">
    </div>
</div>

<?php
$dispatchIndexI18n = [
    'dash' => $dash,
    'podEmpty' => t('dispatch.view.index.js_pod_empty', '暂无签收照片或未写入数据库。'),
    'updateFail' => t('dispatch.view.index.js_update_fail', '更新失败'),
    'networkError' => t('dispatch.view.index.js_network_error', '网络错误'),
    'deleteFail' => t('dispatch.view.index.js_delete_fail', '删除失败'),
    'confirmDelete' => t('dispatch.view.index.js_confirm_delete', '确认删除该订单？此操作不可恢复。'),
    'imgAltThumb' => t('dispatch.view.index.img_alt_thumb', '签收照片'),
    'imgAltLarge' => t('dispatch.view.index.img_alt_pod', '签收大图'),
];
?>
<script>
window.__dispatchIndexI18n = <?php echo json_encode($dispatchIndexI18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
(function () {
    var I18N = window.__dispatchIndexI18n || {};
    var dash = (I18N.dash !== undefined && I18N.dash !== null && String(I18N.dash) !== '') ? String(I18N.dash) : '\u2014';
    function stripTrackingScanSuffix(s) {
        return String(s || '').trim().replace(/@\d+$/, '').trim();
    }
    var dispatchOrderFilterForm = document.querySelector('form.dispatch-order-filter-form');
    var qTrackInput = document.getElementById('q_track');
    if (dispatchOrderFilterForm && qTrackInput) {
        dispatchOrderFilterForm.addEventListener('submit', function () {
            qTrackInput.value = stripTrackingScanSuffix(qTrackInput.value);
        });
    }
    var listPath = window.location.pathname + window.location.search;
    var detailModal = document.getElementById('dispatchCustomerDetailModal');
    var codeEditModal = document.getElementById('dispatchCustomerCodeEditModal');
    function txt(v) { return (v === null || v === undefined || String(v).trim() === '') ? dash : String(v); }
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

    var deliveredModal = document.getElementById('dispatchDeliveredDetailModal');
    var deliveredImageModal = document.getElementById('dispatchDeliveredImageModal');
    function closeDeliveredDetail() {
        if (deliveredModal) deliveredModal.style.display = 'none';
    }
    function closeDeliveredImage() {
        if (deliveredImageModal) deliveredImageModal.style.display = 'none';
    }
    function openDeliveredImage(url) {
        if (!deliveredImageModal || !url) return;
        var img = document.getElementById('ddi_img');
        if (!img) return;
        img.src = url;
        if (I18N.imgAltLarge) img.alt = I18N.imgAltLarge;
        deliveredImageModal.style.display = 'flex';
    }
    function renderPodImgWrap(el, url, show) {
        if (!el) return;
        el.innerHTML = '';
        if (!show) {
            el.textContent = dash;
            return;
        }
        var img = document.createElement('img');
        img.src = url;
        img.alt = I18N.imgAltThumb || '';
        img.className = 'pod-thumb';
        img.addEventListener('click', function () { openDeliveredImage(url); });
        el.appendChild(img);
    }
    document.querySelectorAll('.chip-order-status-btn').forEach(function (btn) {
        var openDetail = function () {
            if (!deliveredModal) return;
            var payload = {};
            try { payload = JSON.parse(btn.getAttribute('data-delivered-detail') || '{}'); } catch (e) {}
            document.getElementById('ddd_time').textContent = txt(payload.time);
            var ddn = document.getElementById('ddd_driver_name');
            if (ddn) ddn.textContent = txt(payload.driver_name);
            var w1 = document.getElementById('ddd_img1_wrap');
            var w2 = document.getElementById('ddd_img2_wrap');
            if (!payload.has1 && !payload.has2) {
                if (w1) {
                    w1.innerHTML = '';
                    var podEmptySpan = document.createElement('span');
                    podEmptySpan.className = 'muted';
                    podEmptySpan.textContent = I18N.podEmpty || '';
                    w1.appendChild(podEmptySpan);
                }
                if (w2) w2.textContent = dash;
            } else {
                renderPodImgWrap(w1, payload.url1 || '', !!payload.has1);
                renderPodImgWrap(w2, payload.url2 || '', !!payload.has2);
            }
            deliveredModal.style.display = 'flex';
        };
        btn.addEventListener('click', openDetail);
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDetail();
            }
        });
    });
    if (deliveredModal) {
        deliveredModal.addEventListener('click', function (e) {
            if (e.target === deliveredModal) closeDeliveredDetail();
        });
    }
    var dddClose = document.getElementById('ddd_close_btn');
    if (dddClose) dddClose.addEventListener('click', closeDeliveredDetail);
    var dddCloseX = document.getElementById('ddd_close_btn_x');
    if (dddCloseX) dddCloseX.addEventListener('click', closeDeliveredDetail);
    if (deliveredImageModal) {
        deliveredImageModal.addEventListener('click', function (e) {
            if (e.target === deliveredImageModal) closeDeliveredImage();
        });
    }
    var ddiCloseX = document.getElementById('ddi_close_btn_x');
    if (ddiCloseX) ddiCloseX.addEventListener('click', closeDeliveredImage);

    document.querySelectorAll('.dispatch-customer-code-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-waybill-id');
            var tr = document.querySelector('tr[data-waybill-id="' + id + '"]');
            if (!tr) return;
            var codeText = tr.querySelector('.dispatch-code-text');
            var oldCode = codeText ? codeText.textContent.trim() : '';
            document.getElementById('dcc_waybill_id').value = id;
            document.getElementById('dcc_customer_code').value = (oldCode === dash) ? '' : oldCode;
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
                        alert((j && j.error) ? j.error : (I18N.updateFail || ''));
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
                    var wxLine = wx ? (ln ? (wx + ' / ' + ln) : wx) : (ln || dash);
                    var codeText = tr.querySelector('.dispatch-code-text');
                    if (codeText) {
                        codeText.textContent = newCode || dash;
                    }
                    var detailBtn = tr.querySelector('.dispatch-customer-detail-btn');
                    if (detailBtn) {
                        var addrTh = row.addr_th_full || '';
                        var addrEn = row.addr_en_full || '';
                        var lat = row.latitude;
                        var lng = row.longitude;
                        var geoText = dash;
                        if (lat !== null && lat !== '' && lng !== null && lng !== '') {
                            geoText = String(lat) + ', ' + String(lng);
                        }
                        var rp = row.route_primary || '';
                        var rs = row.route_secondary || '';
                        var rc = row.routes_combined || '';
                        var routes = rc || (rp && rs ? (rp + ' - ' + rs) : (rp || rs || dash));
                        var en = row.community_name_en || '';
                        var th = row.community_name_th || '';
                        var community = (!en && !th) ? dash : (en && th ? (en + ' / ' + th) : (en || th));
                        var payload = {
                            customer_code: newCode,
                            wechat_id: wx,
                            line_id: ln,
                            addr_th_full: addrTh,
                            addr_en_full: addrEn,
                            geo_position: geoText === dash ? '' : geoText,
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
                    alert(I18N.networkError || '');
                });
    });

    document.querySelectorAll('.dispatch-waybill-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm(I18N.confirmDelete || '')) {
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
                        alert((j && j.error) ? j.error : (I18N.deleteFail || ''));
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    alert(I18N.networkError || '');
                });
        });
    });
})();
</script>

<?php endif; ?>
