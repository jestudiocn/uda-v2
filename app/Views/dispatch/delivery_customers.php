<?php
/** @var array $rows */
/** @var array $consigningOptions */
/** @var int $filterCcId */
/** @var string $message */
/** @var string $error */
/** @var bool $canEdit */
/** @var bool $hideConsigningSelectors */
/** @var bool $hideConsigningFilterAndColumn 绑定账号或全站仅一个启用委托客户时隐藏委托客户筛选与列表列 */
/** @var bool $dispatchBoundClientMissing */
/** @var list<string> $deliveryCustomerStateCatalog */
/** @var bool $deliveryCustomerSchemaV2 */
/** @var string $dqCustomerCode */
/** @var string $dqWechat */
/** @var string $dqRoutePrimary */
/** @var string $dqCustomerState */
/** @var bool $deliveryCustomerHasGeoProfile */
/** @var string $deliveryCustomerGeoProfileHint */
/** @var list<string> $deliveryCustomerGeoStatusCatalog */
/** @var string $dqGeoStatus */
/** @var string $dqAddrTh */
/** @var string $dqAmphoe */
/** @var bool $deliveryCustomerHasThGeoMaster */
/** @var bool $deliveryCustomerThGeoDataReady */
/** @var string $deliveryCustomerThGeoHint */
/** @var list<array{line:int,reason:string}> $importFailureDetails */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var int $totalPages */
/** @var bool $deliveryCustomerSchemaV3 */
/** @var string $migrationHintV3 */
$importFailureDetails = $importFailureDetails ?? [];
$page = isset($page) ? (int)$page : 1;
$perPage = isset($perPage) ? (int)$perPage : 20;
$total = isset($total) ? (int)$total : 0;
$totalPages = isset($totalPages) ? (int)$totalPages : 1;
$deliveryCustomerSchemaV3 = $deliveryCustomerSchemaV3 ?? false;
$migrationHintV3 = $migrationHintV3 ?? '';
$showOpsCol = !$dispatchBoundClientMissing;
$hideConsigningSelectors = $hideConsigningSelectors ?? false;
$hideConsigningFilterAndColumn = $hideConsigningFilterAndColumn ?? false;
$dispatchBoundClientMissing = $dispatchBoundClientMissing ?? false;
$deliveryCustomerStateCatalog = $deliveryCustomerStateCatalog ?? ['正常', '异常', '暂停', '转发'];
$deliveryCustomerSchemaV2 = $deliveryCustomerSchemaV2 ?? false;
$dqCustomerCode = $dqCustomerCode ?? '';
$dqWechat = $dqWechat ?? '';
$dqRoutePrimary = $dqRoutePrimary ?? '';
$dqCustomerState = $dqCustomerState ?? '';
$deliveryCustomerHasGeoProfile = $deliveryCustomerHasGeoProfile ?? false;
$deliveryCustomerGeoProfileHint = $deliveryCustomerGeoProfileHint ?? '';
$deliveryCustomerGeoStatusCatalog = $deliveryCustomerGeoStatusCatalog ?? ['已定位', '免定位(OT/UDA)', '待补定位(准客户)', '缺失待补'];
$dqGeoStatus = $dqGeoStatus ?? '';
$dqAddrTh = $dqAddrTh ?? '';
$dqAmphoe = $dqAmphoe ?? '';
$deliveryCustomerHasThGeoMaster = $deliveryCustomerHasThGeoMaster ?? false;
$deliveryCustomerThGeoDataReady = $deliveryCustomerThGeoDataReady ?? false;
$deliveryCustomerThGeoHint = $deliveryCustomerThGeoHint ?? '';
$useThGeoDropdowns = $deliveryCustomerHasThGeoMaster && $deliveryCustomerThGeoDataReady;
$hasActiveDeliverySearch = $dqCustomerCode !== '' || $dqWechat !== '' || $dqRoutePrimary !== ''
    || ($deliveryCustomerSchemaV2 && $dqCustomerState !== '')
    || ($deliveryCustomerHasGeoProfile && ($dqGeoStatus !== '' || $dqAddrTh !== '' || $dqAmphoe !== ''));
$listParams = [];
if (!$hideConsigningFilterAndColumn) {
    $listParams['consigning_client_id'] = (string)(int)$filterCcId;
}
foreach (
    [
        'q_customer_code' => $dqCustomerCode,
        'q_wechat' => $dqWechat,
        'q_route_primary' => $dqRoutePrimary,
        'q_customer_state' => $dqCustomerState,
        'q_geo_status' => $dqGeoStatus,
        'q_addr_th' => $dqAddrTh,
        'q_amphoe' => $dqAmphoe,
    ] as $qk => $qv
) {
    if (trim((string)$qv) !== '') {
        $listParams[$qk] = (string)$qv;
    }
}
$listParams['per_page'] = (string)$perPage;
if ($page > 1) {
    $listParams['page'] = (string)$page;
}
$deliveryCustomersListQuery = $listParams === [] ? '' : ('?' . http_build_query($listParams));
$quickMissingGeoParams = $listParams;
$quickMissingGeoParams['q_geo_status'] = '缺失待补';
$quickMissingGeoParams['page'] = '1';
$quickMissingGeoUrl = '/dispatch/delivery-customers?' . http_build_query($quickMissingGeoParams);
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送客户</h2>
    <?php if ($hideConsigningSelectors): ?>
        <div class="muted">当前为<strong>委托客户绑定账号</strong>，仅显示与管理该委托客户下的派送客户（与「订单查询」范围一致），不再提供委托客户切换。</div>
    <?php elseif ($hideConsigningFilterAndColumn): ?>
        <div class="muted">当前启用中的<strong>委托客户仅有一个</strong>，已自动限定在该客户，不再展示委托客户筛选与列表中的委托客户列。支持 <strong>.xlsx / .xls</strong> 与 <strong>.csv</strong> 批量导入（推荐 Excel 模板）；单笔新增已关闭。</div>
    <?php else: ?>
        <div class="muted">末端收件人主数据；「派送客户编号」与货件中的编号对应。上方筛选默认<strong>全部</strong>时可跨委托客户浏览列表（表格会显示所属委托客户）。支持 <strong>.xlsx / .xls</strong> 与 <strong>.csv</strong> 批量导入（推荐 Excel 模板）；单笔新增已关闭。</div>
    <?php endif; ?>
</div>
<?php if (!$deliveryCustomerSchemaV2): ?>
<div class="card" style="border-left:4px solid #ca8a04;">请执行数据库迁移 <code>database/migrations/024_dispatch_delivery_customer_state_routes.sql</code> 后，即可使用客户状态、路线合并栏位及行内编辑。</div>
<?php endif; ?>
<?php if ($migrationHintV3 !== ''): ?>
<div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($migrationHintV3); ?></div>
<?php endif; ?>
<?php if ($deliveryCustomerGeoProfileHint !== ''): ?>
<div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($deliveryCustomerGeoProfileHint); ?></div>
<?php endif; ?>
<?php if ($deliveryCustomerThGeoHint !== ''): ?>
<div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($deliveryCustomerThGeoHint); ?></div>
<?php endif; ?>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!empty($importFailureDetails)): ?>
<div class="card" style="border-left:4px solid #ea580c;">
    <h3 style="margin:0 0 8px 0;font-size:15px;">本次导入失败明细</h3>
    <p class="muted" style="margin:0 0 10px 0;font-size:13px;">「行号」为文件中的行序（第 1 行为表头，第 2 行起为数据；Excel 与 CSV 相同）。请按原因修正后重新上传；表头可与模板不完全一致，但须能被系统识别（见首列失败原因提示）。</p>
    <div style="overflow:auto;max-height:min(360px,50vh);border:1px solid #e5e7eb;border-radius:6px;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:#f9fafb;text-align:left;">
                    <th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;width:72px;">行号</th>
                    <th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;">失败原因</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($importFailureDetails as $fd): ?>
                    <tr>
                        <td style="padding:8px 10px;border-bottom:1px solid #f3f4f6;vertical-align:top;"><?php echo (int)($fd['line'] ?? 0); ?></td>
                        <td style="padding:8px 10px;border-bottom:1px solid #f3f4f6;"><?php echo htmlspecialchars((string)($fd['reason'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($dispatchBoundClientMissing): ?>
<div class="card" style="border-left:4px solid #dc2626;">账号已绑定委托客户，但该客户在系统中不存在或已被删除。请联系管理员在「系统管理 → 用户管理」中修正绑定。</div>
<?php elseif ($hideConsigningSelectors && !empty($consigningOptions)): ?>
<div class="card">
    <div class="muted">当前绑定委托客户：<strong><?php echo htmlspecialchars(trim((string)($consigningOptions[0]['client_code'] ?? '') . ' — ' . (string)($consigningOptions[0]['client_name'] ?? ''))); ?></strong></div>
</div>
<?php elseif (!$hideConsigningFilterAndColumn && !empty($consigningOptions)): ?>
<div class="card">
    <form method="get" class="form-grid" style="grid-template-columns: 160px 1fr auto;align-items:end;">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>" style="display:none" aria-hidden="true">
        <label for="consigning_client_id">委托客户</label>
        <select id="consigning_client_id" name="consigning_client_id" onchange="this.form.submit()">
            <option value="0"<?php echo $filterCcId === 0 ? ' selected' : ''; ?>>全部（所有委托客户）</option>
            <?php foreach ($consigningOptions as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>"<?php echo (int)$o['id'] === $filterCcId ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($o['client_code'] ?? '') . ' — ' . (string)($o['client_name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="page" value="1" style="display:none" aria-hidden="true">
        <button type="submit">筛选</button>
    </form>
</div>
<?php elseif (!$hideConsigningFilterAndColumn && empty($consigningOptions)): ?>
<div class="card"><div class="muted">尚无启用中的委托客户，请先在「委托客户」中维护。</div></div>
<?php endif; ?>

<?php
$exportTplQuery = ['export' => 'delivery_csv_template'];
if (!$hideConsigningFilterAndColumn && (int)$filterCcId > 0) {
    $exportTplQuery['consigning_client_id'] = (string)(int)$filterCcId;
}
$exportTplUrl = '/dispatch/delivery-customers?' . http_build_query($exportTplQuery);
?>
<?php if ($canEdit && !$dispatchBoundClientMissing && !empty($consigningOptions)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">批量导入派送客户（Excel / CSV）</h3>
    <p class="muted" style="margin:0 0 10px 0;font-size:13px;">同一委托客户下，若文件中的「派送客户编号」与已有资料相同，将<strong>整行覆盖</strong>更新（含收件人、电话、地址相关栏位等）。下载模板：服务器已安装 PhpSpreadsheet 时为 <strong>.xlsx</strong>，否则为 <strong>.csv</strong>。</p>
    <form method="post" enctype="multipart/form-data" action="/dispatch/delivery-customers<?php echo htmlspecialchars($deliveryCustomersListQuery, ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <input type="hidden" name="csv_consigning_client_id" value="<?php echo (int)$filterCcId; ?>">
        <a class="btn" href="<?php echo htmlspecialchars($exportTplUrl, ENT_QUOTES, 'UTF-8'); ?>">下载导入模板</a>
        <label for="delivery_import_file" style="margin:0;">选择文件</label>
        <input id="delivery_import_file" name="delivery_import_file" type="file" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" style="max-width:min(360px,100%);">
        <button type="submit" name="import_delivery_customers" value="1">上传导入</button>
    </form>
</div>
<?php endif; ?>

<?php if (!$dispatchBoundClientMissing && !empty($consigningOptions)): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;font-size:15px;">搜索派送客户</h3>
    <form method="get" action="/dispatch/delivery-customers" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <?php if (!$hideConsigningFilterAndColumn): ?>
            <input type="hidden" name="consigning_client_id" value="<?php echo (int)$filterCcId; ?>">
        <?php endif; ?>
        <input type="hidden" name="page" value="1" style="display:none" aria-hidden="true">
        <div style="display:flex;flex-direction:column;gap:4px;min-width:120px;">
            <label for="dq_customer_code" style="margin:0;font-size:13px;">客户编码</label>
            <input id="dq_customer_code" name="q_customer_code" type="text" value="<?php echo htmlspecialchars($dqCustomerCode); ?>" maxlength="60" style="min-width:120px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:120px;">
            <label for="dq_wechat" style="margin:0;font-size:13px;">微信号</label>
            <input id="dq_wechat" name="q_wechat" type="text" value="<?php echo htmlspecialchars($dqWechat); ?>" maxlength="120" style="min-width:120px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:120px;">
            <label for="dq_route_primary" style="margin:0;font-size:13px;">主路线</label>
            <input id="dq_route_primary" name="q_route_primary" type="text" value="<?php echo htmlspecialchars($dqRoutePrimary); ?>" maxlength="120" style="min-width:120px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:7.5rem;">
            <label for="dq_customer_state" style="margin:0;font-size:13px;">客户状态</label>
            <select id="dq_customer_state" name="q_customer_state" style="max-width:10rem;"<?php echo !$deliveryCustomerSchemaV2 ? ' disabled title="请先执行数据库迁移 024 后方可按状态筛选"' : ''; ?>>
                <option value=""><?php echo $deliveryCustomerSchemaV2 ? '（不限）' : '（需迁移）'; ?></option>
                <?php foreach ($deliveryCustomerStateCatalog as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo $dqCustomerState === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($deliveryCustomerHasGeoProfile): ?>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:9rem;">
            <label for="dq_geo_status" style="margin:0;font-size:13px;">定位状态</label>
            <select id="dq_geo_status" name="q_geo_status" style="max-width:12rem;">
                <option value="">（不限）</option>
                <?php foreach ($deliveryCustomerGeoStatusCatalog as $gst): ?>
                    <option value="<?php echo htmlspecialchars($gst); ?>"<?php echo $dqGeoStatus === $gst ? ' selected' : ''; ?>><?php echo htmlspecialchars($gst); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:140px;">
            <label for="dq_addr_th" style="margin:0;font-size:13px;">泰文地址关键词</label>
            <input id="dq_addr_th" name="q_addr_th" type="text" value="<?php echo htmlspecialchars($dqAddrTh); ?>" maxlength="200" placeholder="匹配完整泰文地址" style="min-width:120px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:100px;">
            <label for="dq_amphoe" style="margin:0;font-size:13px;">县（区）</label>
            <input id="dq_amphoe" name="q_amphoe" type="text" value="<?php echo htmlspecialchars($dqAmphoe); ?>" maxlength="160" placeholder="模糊匹配" style="min-width:100px;">
        </div>
        <?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:5rem;">
            <label for="dc_per_page" style="margin:0;font-size:13px;">每页</label>
            <select id="dc_per_page" name="per_page" style="max-width:6rem;">
                <?php foreach ([20, 50, 100] as $pp): ?>
                    <option value="<?php echo $pp; ?>"<?php echo $perPage === $pp ? ' selected' : ''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">搜索</button>
        <?php if ($deliveryCustomerHasGeoProfile): ?>
            <a class="btn" href="<?php echo htmlspecialchars($quickMissingGeoUrl, ENT_QUOTES, 'UTF-8'); ?>" style="background:#b45309;" title="筛选定位状态为「缺失待补」">只看缺失待补</a>
        <?php endif; ?>
        <?php if ($hasActiveDeliverySearch): ?>
            <?php
            $clearQs = [];
            if (!$hideConsigningFilterAndColumn) {
                $clearQs['consigning_client_id'] = (string)(int)$filterCcId;
            }
            $clearUrl = '/dispatch/delivery-customers' . ($clearQs === [] ? '' : ('?' . http_build_query($clearQs)));
            ?>
            <a class="btn" href="<?php echo htmlspecialchars($clearUrl, ENT_QUOTES, 'UTF-8'); ?>" style="background:#64748b;">清除条件</a>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <?php if ($total > 0): ?>
        <div class="muted" style="margin-bottom:8px;">共 <?php echo (int)$total; ?> 条，第 <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?> 页</div>
    <?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table table-valign-middle">
            <thead>
                <tr>
                    <?php if (!$hideConsigningFilterAndColumn): ?>
                    <th>委托客户</th>
                    <?php endif; ?>
                    <th>派送客户编号</th>
                    <th>微信 / Line</th>
                    <th>电话</th>
                    <?php if ($deliveryCustomerHasGeoProfile): ?>
                    <th>完整英文地址</th>
                    <?php endif; ?>
                    <th>定位</th>
                    <?php if ($deliveryCustomerHasGeoProfile): ?>
                    <th>定位状态</th>
                    <?php endif; ?>
                    <th>路线</th>
                    <th>小区</th>
                    <th>变更</th>
                    <th>客户状态</th>
                    <?php if ($showOpsCol): ?>
                    <th style="min-width:120px;">操作</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $colspan = 8 + ($deliveryCustomerHasGeoProfile ? 2 : 0) + ($showOpsCol ? 1 : 0) + ($hideConsigningFilterAndColumn ? 0 : 1);
            ?>
            <?php if ($dispatchBoundClientMissing): ?>
                <tr><td colspan="<?php echo (int)$colspan; ?>" class="muted">绑定无效，无法加载派送客户列表</td></tr>
            <?php elseif (empty($consigningOptions)): ?>
                <tr><td colspan="<?php echo (int)$colspan; ?>" class="muted">请先新增至少一个委托客户</td></tr>
            <?php elseif (empty($rows) && $hasActiveDeliverySearch): ?>
                <tr><td colspan="<?php echo (int)$colspan; ?>" class="muted">无符合条件的派送客户，请调整搜索条件或点击「清除条件」。</td></tr>
            <?php elseif (empty($rows)): ?>
                <tr><td colspan="<?php echo (int)$colspan; ?>" class="muted">暂无派送客户数据</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $wx = trim((string)($r['wechat_id'] ?? ''));
                    $ln = trim((string)($r['line_id'] ?? ''));
                    $phone = trim((string)($r['phone'] ?? ''));
                    $wxLine = $wx === '' ? $ln : ($ln === '' ? $wx : $wx . ' / ' . $ln);
                    $rp = trim((string)($r['route_primary'] ?? ''));
                    $rs = trim((string)($r['route_secondary'] ?? ''));
                    $rcDb = trim((string)($r['routes_combined'] ?? ''));
                    $routesDisp = $rcDb !== '' ? $rcDb : (trim($rp . ($rp !== '' && $rs !== '' ? ' - ' : '') . $rs));
                    $en = trim((string)($r['community_name_en'] ?? ''));
                    $th = trim((string)($r['community_name_th'] ?? ''));
                    $commDisp = ($en === '' && $th === '') ? '—' : (($en !== '' && $th !== '') ? ($en . ' / ' . $th) : ($en !== '' ? $en : $th));
                    $latV = $r['latitude'] ?? null;
                    $lngV = $r['longitude'] ?? null;
                    $geoDisp = '';
                    if ($latV !== null && $latV !== '' && $lngV !== null && $lngV !== '') {
                        $geoDisp = rtrim(rtrim(sprintf('%.7f', (float)$latV), '0'), '.')
                            . ', '
                            . rtrim(rtrim(sprintf('%.7f', (float)$lngV), '0'), '.');
                    }
                    $bizState = '';
                    if ($deliveryCustomerSchemaV2) {
                        $rawSt = trim((string)($r['customer_state'] ?? '正常'));
                        $bizState = in_array($rawSt, $deliveryCustomerStateCatalog, true) ? $rawSt : '正常';
                    }
                    $markTag = '';
                    $nowTs = time();
                    $createdMarkRaw = trim((string)($r['created_marked_at'] ?? ''));
                    $addressMarkRaw = trim((string)($r['address_geo_updated_at'] ?? ''));
                    $createdMarkTs = $createdMarkRaw !== '' ? strtotime($createdMarkRaw) : false;
                    $addressMarkTs = $addressMarkRaw !== '' ? strtotime($addressMarkRaw) : false;
                    if ($addressMarkTs !== false && ($nowTs - $addressMarkTs) <= 30 * 86400) {
                        $markTag = '改';
                    } elseif ($createdMarkTs !== false && ($nowTs - $createdMarkTs) <= 30 * 86400) {
                        $markTag = '新';
                    }
                    $rowId = (int)($r['id'] ?? 0);
                    $editPayload = [
                        'id' => $rowId,
                        'customer_code' => (string)($r['customer_code'] ?? ''),
                        'wechat_id' => $wx,
                        'line_id' => $ln,
                        'phone' => $phone,
                        'addr_house_no' => (string)($r['addr_house_no'] ?? ''),
                        'addr_road_soi' => (string)($r['addr_road_soi'] ?? ''),
                        'addr_moo_village' => (string)($r['addr_moo_village'] ?? ''),
                        'addr_tambon' => (string)($r['addr_tambon'] ?? ''),
                        'addr_amphoe' => (string)($r['addr_amphoe'] ?? ''),
                        'addr_province' => (string)($r['addr_province'] ?? ''),
                        'addr_zipcode' => (string)($r['addr_zipcode'] ?? ''),
                        'th_geo_province_id' => (int)($r['th_geo_province_id'] ?? 0),
                        'th_geo_district_id' => (int)($r['th_geo_district_id'] ?? 0),
                        'th_geo_subdistrict_id' => (int)($r['th_geo_subdistrict_id'] ?? 0),
                        'geo_status' => (string)($r['geo_status'] ?? ''),
                        'addr_th_full' => (string)($r['addr_th_full'] ?? ''),
                        'addr_en_full' => (string)($r['addr_en_full'] ?? ''),
                        'geo_position' => $geoDisp,
                        'route_primary' => $rp,
                        'route_secondary' => $rs,
                        'community_name_en' => $en,
                        'community_name_th' => $th,
                        'customer_state' => $bizState !== '' ? $bizState : '正常',
                        'customer_requirement' => (string)($r['customer_requirements'] ?? ''),
                    ];
                    ?>
                    <tr>
                        <?php if (!$hideConsigningFilterAndColumn): ?>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['client_code'] ?? '') . ' — ' . (string)($r['client_name'] ?? ''))); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars((string)($r['customer_code'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($wxLine); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($phone !== '' ? $phone : ''); ?></td>
                        <?php if ($deliveryCustomerHasGeoProfile): ?>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['addr_en_full'] ?? ''))); ?></td>
                        <?php endif; ?>
                        <td><?php
                        if ($geoDisp !== '' && $latV !== null && $latV !== '' && $lngV !== null && $lngV !== '') {
                            $latMaps = (float)$latV;
                            $lngMaps = (float)$lngV;
                            $mapsUrl = 'https://www.google.com/maps?q=' . rawurlencode(sprintf('%.7f', $latMaps) . ',' . sprintf('%.7f', $lngMaps));
                            echo '<a href="' . htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($geoDisp) . '</a>';
                        } else {
                            echo '—';
                        }
                        ?></td>
                        <?php if ($deliveryCustomerHasGeoProfile): ?>
                        <td><?php
                        $gst = trim((string)($r['geo_status'] ?? ''));
                        echo $gst !== '' ? htmlspecialchars($gst) : '—';
                        ?></td>
                        <?php endif; ?>
                        <td class="cell-tip"><?php echo html_cell_tip_content($routesDisp !== '' ? $routesDisp : ''); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($commDisp !== '—' ? $commDisp : ''); ?></td>
                        <td><?php
                        if ($markTag === '改') {
                            echo '<span class="chip chip-mark-modified">' . htmlspecialchars($markTag) . '</span>';
                        } elseif ($markTag === '新') {
                            echo '<span class="chip chip-mark-new">' . htmlspecialchars($markTag) . '</span>';
                        }
                        ?></td>
                        <td>
                            <?php if ($deliveryCustomerSchemaV2 && $canEdit && !$dispatchBoundClientMissing): ?>
                                <select class="dc-customer-state" data-delivery-id="<?php echo $rowId; ?>" style="max-width:7rem;">
                                    <?php foreach ($deliveryCustomerStateCatalog as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo $bizState === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($deliveryCustomerSchemaV2): ?>
                                <?php echo htmlspecialchars($bizState); ?>
                            <?php else: ?>
                                <?php echo (int)($r['status'] ?? 0) === 1 ? '启用' : '停用'; ?>
                            <?php endif; ?>
                        </td>
                        <?php if ($showOpsCol): ?>
                        <td style="white-space:nowrap;vertical-align:middle;">
                            <div class="dispatch-row-actions">
                            <?php if ($canEdit && $deliveryCustomerSchemaV2): ?>
                                <button type="button" class="btn btn-dispatch-round btn-dispatch-round--edit dc-edit-btn" title="编辑" data-row="<?php echo htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">E</button>
                                <button type="button" class="btn btn-dispatch-round btn-dispatch-round--delete dc-delete-btn" data-delivery-id="<?php echo $rowId; ?>" title="删除">D</button>
                            <?php endif; ?>
                                <button type="button" class="btn btn-dispatch-round btn-dispatch-round--info dc-detail-btn" title="详情" data-row="<?php echo htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">i</button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    if ($totalPages > 1 && !$dispatchBoundClientMissing && !empty($consigningOptions)):
        $pgBase = $listParams;
        unset($pgBase['page']);
        ?>
        <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <?php if ($page > 1):
            $pgPrev = $pgBase;
            $pgPrev['page'] = (string)($page - 1);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars('/dispatch/delivery-customers?' . http_build_query($pgPrev), ENT_QUOTES, 'UTF-8'); ?>">上一页</a>
        <?php endif; ?>
        <?php if ($page < $totalPages):
            $pgNext = $pgBase;
            $pgNext['page'] = (string)($page + 1);
            ?>
            <a class="btn" href="<?php echo htmlspecialchars('/dispatch/delivery-customers?' . http_build_query($pgNext), ENT_QUOTES, 'UTF-8'); ?>">下一页</a>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$dispatchBoundClientMissing && !empty($consigningOptions)): ?>
<?php if ($canEdit && $deliveryCustomerSchemaV2): ?>
<div id="dcEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:640px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="dc_edit_close_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;">编辑派送客户</h3>
        <form method="post" action="/dispatch/delivery-customers<?php echo htmlspecialchars($deliveryCustomersListQuery, ENT_QUOTES, 'UTF-8'); ?>" class="form-grid">
            <input type="hidden" name="save_delivery_customer_edit" value="1">
            <input type="hidden" name="delivery_id" id="dc_edit_id" value="">
            <input type="hidden" name="dc_list_consigning_client_id" value="<?php echo (int)$filterCcId; ?>">
            <input type="hidden" name="dc_list_page" value="<?php echo (int)$page; ?>">
            <input type="hidden" name="dc_list_per_page" value="<?php echo (int)$perPage; ?>">
            <?php if ($dqCustomerCode !== ''): ?><input type="hidden" name="q_customer_code" value="<?php echo htmlspecialchars($dqCustomerCode, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <?php if ($dqWechat !== ''): ?><input type="hidden" name="q_wechat" value="<?php echo htmlspecialchars($dqWechat, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <?php if ($dqRoutePrimary !== ''): ?><input type="hidden" name="q_route_primary" value="<?php echo htmlspecialchars($dqRoutePrimary, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <?php if ($deliveryCustomerSchemaV2 && $dqCustomerState !== ''): ?><input type="hidden" name="q_customer_state" value="<?php echo htmlspecialchars($dqCustomerState, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <?php if ($deliveryCustomerHasGeoProfile && $dqGeoStatus !== ''): ?><input type="hidden" name="q_geo_status" value="<?php echo htmlspecialchars($dqGeoStatus, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <?php if ($deliveryCustomerHasGeoProfile && $dqAddrTh !== ''): ?><input type="hidden" name="q_addr_th" value="<?php echo htmlspecialchars($dqAddrTh, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <?php if ($deliveryCustomerHasGeoProfile && $dqAmphoe !== ''): ?><input type="hidden" name="q_amphoe" value="<?php echo htmlspecialchars($dqAmphoe, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <label for="dc_edit_code">派送客户编号</label>
            <input id="dc_edit_code" name="customer_code" maxlength="60" required>
            <label for="dc_edit_wechat">微信号</label>
            <input id="dc_edit_wechat" name="wechat_id" maxlength="120">
            <label for="dc_edit_line">Line</label>
            <input id="dc_edit_line" name="line_id" maxlength="120">
            <label for="dc_edit_phone">电话</label>
            <input id="dc_edit_phone" name="phone" maxlength="40">
            <label for="dc_edit_addr_house_no">门牌号 / House Number</label>
            <input id="dc_edit_addr_house_no" name="addr_house_no" maxlength="120">
            <label for="dc_edit_addr_road_soi">路（巷） / Road(Soi)</label>
            <input id="dc_edit_addr_road_soi" name="addr_road_soi" maxlength="160">
            <label for="dc_edit_addr_moo">村 / Moo(Village)</label>
            <input id="dc_edit_addr_moo" name="addr_moo_village" maxlength="160">
            <?php if ($useThGeoDropdowns): ?>
            <input type="hidden" name="addr_tambon" id="dc_edit_addr_tambon" value="">
            <input type="hidden" name="addr_amphoe" id="dc_edit_addr_amphoe" value="">
            <input type="hidden" name="addr_province" id="dc_edit_addr_province" value="">
            <input type="hidden" name="addr_zipcode" id="dc_edit_addr_zipcode" value="">
            <input type="hidden" name="th_geo_subdistrict_id" id="dc_edit_th_geo_subdistrict_id" value="0">
            <label for="dc_th_sel_province">府 / Province（官方数据）</label>
            <select id="dc_th_sel_province" style="max-width:100%;">
                <option value="">请选择府</option>
            </select>
            <label for="dc_th_sel_district">县（区） / Amphoe</label>
            <select id="dc_th_sel_district" style="max-width:100%;" disabled>
                <option value="">请先选择府</option>
            </select>
            <label for="dc_th_sel_subdistrict">镇（乡） / Tambon + 邮编</label>
            <select id="dc_th_sel_subdistrict" style="max-width:100%;" disabled>
                <option value="">请先选择县</option>
            </select>
            <label>当前邮编（随镇自动带出）</label>
            <div id="dc_th_zip_preview" class="muted" style="padding-top:6px;">—</div>
            <?php else: ?>
            <label for="dc_edit_addr_tambon">镇（街道）（乡） / Tambon</label>
            <input id="dc_edit_addr_tambon" name="addr_tambon" maxlength="160">
            <label for="dc_edit_addr_amphoe">县（区） / Amphoe</label>
            <input id="dc_edit_addr_amphoe" name="addr_amphoe" maxlength="160">
            <label for="dc_edit_addr_province">府 / Province</label>
            <input id="dc_edit_addr_province" name="addr_province" maxlength="160">
            <label for="dc_edit_addr_zipcode">Zipcode</label>
            <input id="dc_edit_addr_zipcode" name="addr_zipcode" maxlength="20">
            <?php endif; ?>
            <label>完整泰文（预览）</label>
            <div id="dc_edit_preview_th" class="muted" style="grid-column:2/-1;white-space:pre-wrap;padding-top:6px;">—</div>
            <label>完整英文（预览）</label>
            <div id="dc_edit_preview_en" class="muted" style="grid-column:2/-1;white-space:pre-wrap;padding-top:6px;">—</div>
            <label for="dc_edit_geo">定位</label>
            <div style="grid-column:2/-1;">
                <input id="dc_edit_geo" name="geo_position" type="text" maxlength="48" placeholder="例：13.756331, 100.501765" autocomplete="off">
                <div class="muted" style="margin-top:4px;font-size:12px;">留空表示无坐标；格式同导入说明。</div>
            </div>
            <label for="dc_edit_geo_status">定位状态</label>
            <select id="dc_edit_geo_status" name="geo_status">
                <option value="">（自动判定）</option>
                <option value="已定位">已定位</option>
                <option value="免定位(OT/UDA)">免定位(OT/UDA)</option>
                <option value="待补定位(准客户)">待补定位(准客户)</option>
                <option value="缺失待补">缺失待补</option>
            </select>
            <label for="dc_edit_rp">主路线</label>
            <input id="dc_edit_rp" name="route_primary" maxlength="120">
            <label for="dc_edit_rs">副路线</label>
            <input id="dc_edit_rs" name="route_secondary" maxlength="120">
            <label for="dc_edit_en">小区英文名</label>
            <input id="dc_edit_en" name="community_name_en" maxlength="160">
            <label for="dc_edit_th">小区泰文名</label>
            <input id="dc_edit_th" name="community_name_th" maxlength="160">
            <label for="dc_edit_state">客户状态</label>
            <select id="dc_edit_state" name="customer_state">
                <?php foreach ($deliveryCustomerStateCatalog as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="dc_edit_requirement">客户要求</label>
            <textarea id="dc_edit_requirement" name="customer_requirements" rows="4" maxlength="5000" style="grid-column:2/-1;resize:vertical;"></textarea>
            <div class="form-full" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                <button type="button" class="btn" id="dc_edit_cancel" style="background:#64748b;">取消</button>
                <button type="submit">保存</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<div id="dcDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9998;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:700px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="dc_detail_close_x" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 12px 0;">派送客户详情</h3>
        <div class="form-grid" style="grid-template-columns:160px 1fr;">
            <label>客户编号</label><div id="dc_d_customer_code">—</div>
            <label>微信</label><div id="dc_d_wechat">—</div>
            <label>Line</label><div id="dc_d_line">—</div>
            <label>电话</label><div id="dc_d_phone">—</div>
            <label>定位</label><div id="dc_d_geo">—</div>
            <label>主路线</label><div id="dc_d_rp">—</div>
            <label>副路线</label><div id="dc_d_rs">—</div>
            <label>小区英文名</label><div id="dc_d_en">—</div>
            <label>小区泰文名</label><div id="dc_d_th">—</div>
            <label>客户状态</label><div id="dc_d_state">—</div>
            <label>客户要求</label><div id="dc_d_requirement" style="white-space:pre-wrap;">—</div>
            <label>门牌号</label><div id="dc_d_addr_house_no">—</div>
            <label>路（巷）</label><div id="dc_d_addr_road_soi">—</div>
            <label>村</label><div id="dc_d_addr_moo">—</div>
            <label>镇（街道）（乡）</label><div id="dc_d_addr_tambon">—</div>
            <label>县（区）</label><div id="dc_d_addr_amphoe">—</div>
            <label>府</label><div id="dc_d_addr_province">—</div>
            <label>Zipcode</label><div id="dc_d_addr_zipcode">—</div>
            <label>定位状态</label><div id="dc_d_geo_status">—</div>
            <label>完整泰文地址</label><div id="dc_d_addr_th_full" style="white-space:pre-wrap;">—</div>
            <label>完整英文地址</label><div id="dc_d_addr_en_full" style="white-space:pre-wrap;">—</div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
            <button type="button" class="btn" id="dc_detail_close" style="background:#64748b;">关闭</button>
        </div>
    </div>
</div>
<script>
(function () {
    var useThGeoDropdowns = <?php echo $useThGeoDropdowns ? 'true' : 'false'; ?>;
    var modal = document.getElementById('dcEditModal');
    var form = modal ? modal.querySelector('form') : null;
    var thGeoProvinceTh = '';
    var thGeoAmphoeTh = '';
    function dcTrim(v) {
        return String(v || '').trim();
    }
    /** 与后端 DeliveryAddressLines::composeFromParts 一致：七段整合，不含小区名 */
    function dcComposeFullFromParts() {
        var h = dcTrim(document.getElementById('dc_edit_addr_house_no') && document.getElementById('dc_edit_addr_house_no').value);
        var r = dcTrim(document.getElementById('dc_edit_addr_road_soi') && document.getElementById('dc_edit_addr_road_soi').value);
        var m = dcTrim(document.getElementById('dc_edit_addr_moo') && document.getElementById('dc_edit_addr_moo').value);
        var t = dcTrim(document.getElementById('dc_edit_addr_tambon') && document.getElementById('dc_edit_addr_tambon').value);
        var a = dcTrim(document.getElementById('dc_edit_addr_amphoe') && document.getElementById('dc_edit_addr_amphoe').value);
        var pv = dcTrim(document.getElementById('dc_edit_addr_province') && document.getElementById('dc_edit_addr_province').value);
        var z = dcTrim(document.getElementById('dc_edit_addr_zipcode') && document.getElementById('dc_edit_addr_zipcode').value);
        var provZip = pv + (pv && z ? ' ' : '') + z;
        var mid = [];
        [h, r, m, t, a].forEach(function (seg) {
            if (seg) {
                mid.push(seg);
            }
        });
        var thParts = mid.slice();
        if (provZip) {
            thParts.push(provZip);
        }
        var enParts = mid.slice();
        if (provZip) {
            enParts.push(provZip);
        }
        return { th: thParts.join(' '), en: enParts.join(', ') };
    }
    function dcRefreshAddrPreview() {
        var elTh = document.getElementById('dc_edit_preview_th');
        var elEn = document.getElementById('dc_edit_preview_en');
        if (!elTh || !elEn) {
            return;
        }
        var o = dcComposeFullFromParts();
        elTh.textContent = o.th || '—';
        elEn.textContent = o.en || '—';
    }
    function thGeoLabelEnTh(en, th) {
        en = en || '';
        th = th || '';
        if (en && th) {
            return en + ' | ' + th;
        }
        return en || th || '';
    }
    function dcThGeoSetHiddenAddr(tambonTh, amphoeTh, provinceTh, zip) {
        var a = document.getElementById('dc_edit_addr_tambon');
        var b = document.getElementById('dc_edit_addr_amphoe');
        var c = document.getElementById('dc_edit_addr_province');
        var z = document.getElementById('dc_edit_addr_zipcode');
        if (a) {
            a.value = tambonTh || '';
        }
        if (b) {
            b.value = amphoeTh || '';
        }
        if (c) {
            c.value = provinceTh || '';
        }
        if (z) {
            z.value = zip || '';
        }
    }
    function dcThGeoZipPreview(zip) {
        var el = document.getElementById('dc_th_zip_preview');
        if (el) {
            el.textContent = zip && String(zip).trim() !== '' ? String(zip) : '—';
        }
    }
    function dcThGeoResetCascaded() {
        var dSel = document.getElementById('dc_th_sel_district');
        var sSel = document.getElementById('dc_th_sel_subdistrict');
        if (!dSel || !sSel) {
            return;
        }
        dSel.innerHTML = '<option value="">请先选择府</option>';
        dSel.disabled = true;
        sSel.innerHTML = '<option value="">请先选择县</option>';
        sSel.disabled = true;
        var hid = document.getElementById('dc_edit_th_geo_subdistrict_id');
        if (hid) {
            hid.value = '0';
        }
        dcThGeoZipPreview('');
        thGeoAmphoeTh = '';
        dcRefreshAddrPreview();
    }
    function dcThGeoSyncFromSubSelect() {
        var sSel = document.getElementById('dc_th_sel_subdistrict');
        var hid = document.getElementById('dc_edit_th_geo_subdistrict_id');
        if (!sSel || !hid) {
            return;
        }
        var opt = sSel.options[sSel.selectedIndex];
        if (!opt || !opt.value) {
            hid.value = '0';
            dcThGeoSetHiddenAddr('', thGeoAmphoeTh, thGeoProvinceTh, '');
            dcThGeoZipPreview('');
            dcRefreshAddrPreview();
            return;
        }
        hid.value = String(opt.value);
        var tambonTh = opt.getAttribute('data-name-th') || '';
        var zip = opt.getAttribute('data-zip') || '';
        dcThGeoSetHiddenAddr(tambonTh, thGeoAmphoeTh, thGeoProvinceTh, zip);
        dcThGeoZipPreview(zip);
        dcRefreshAddrPreview();
    }
    function dcThGeoPopulateSelect(sel, items, placeholder, isSub) {
        if (!sel) {
            return;
        }
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var o = document.createElement('option');
            o.value = String(it.id);
            o.textContent = isSub
                ? (thGeoLabelEnTh(it.name_en, it.name_th) + ' (' + (it.zipcode || '') + ')')
                : thGeoLabelEnTh(it.name_en, it.name_th);
            o.setAttribute('data-name-th', it.name_th || '');
            if (isSub) {
                o.setAttribute('data-zip', it.zipcode || '');
            }
            sel.appendChild(o);
        }
    }
    function dcThGeoLoadProvinces() {
        var pSel = document.getElementById('dc_th_sel_province');
        if (!pSel) {
            return Promise.resolve();
        }
        return fetch('/dispatch/th-geo/provinces', { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (!j || !j.ok || !j.items) {
                    return;
                }
                dcThGeoPopulateSelect(pSel, j.items, '请选择府', false);
            })
            .catch(function () {});
    }
    function dcThGeoLoadDistricts(provinceId) {
        var dSel = document.getElementById('dc_th_sel_district');
        if (!dSel || provinceId <= 0) {
            return Promise.resolve();
        }
        return fetch('/dispatch/th-geo/districts?province_id=' + encodeURIComponent(String(provinceId)), { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (!j || !j.ok || !j.items) {
                    return;
                }
                dcThGeoPopulateSelect(dSel, j.items, '请选择县', false);
                dSel.disabled = false;
            })
            .catch(function () {});
    }
    function dcThGeoLoadSubdistricts(districtId) {
        var sSel = document.getElementById('dc_th_sel_subdistrict');
        if (!sSel || districtId <= 0) {
            return Promise.resolve();
        }
        return fetch('/dispatch/th-geo/subdistricts?district_id=' + encodeURIComponent(String(districtId)), { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (!j || !j.ok || !j.items) {
                    return;
                }
                dcThGeoPopulateSelect(sSel, j.items, '请选择镇/乡', true);
                sSel.disabled = false;
            })
            .catch(function () {});
    }
    async function dcThGeoBootstrapFromPayload(payload) {
        var pSel = document.getElementById('dc_th_sel_province');
        if (!useThGeoDropdowns || !pSel) {
            return;
        }
        await dcThGeoLoadProvinces();
        var sid = parseInt(String(payload.th_geo_subdistrict_id || 0), 10);
        var pid = parseInt(String(payload.th_geo_province_id || 0), 10);
        var did = parseInt(String(payload.th_geo_district_id || 0), 10);
        pSel.value = '';
        dcThGeoResetCascaded();
        thGeoProvinceTh = '';
        if (sid > 0 && pid > 0 && did > 0) {
            pSel.value = String(pid);
            var pOpt = pSel.options[pSel.selectedIndex];
            thGeoProvinceTh = (pOpt && pOpt.getAttribute('data-name-th')) ? pOpt.getAttribute('data-name-th') : '';
            await dcThGeoLoadDistricts(pid);
            var dSel = document.getElementById('dc_th_sel_district');
            if (dSel) {
                dSel.value = String(did);
                var dOpt = dSel.options[dSel.selectedIndex];
                thGeoAmphoeTh = (dOpt && dOpt.getAttribute('data-name-th')) ? dOpt.getAttribute('data-name-th') : '';
                await dcThGeoLoadSubdistricts(did);
                var sSel = document.getElementById('dc_th_sel_subdistrict');
                if (sSel) {
                    sSel.value = String(sid);
                    dcThGeoSyncFromSubSelect();
                }
            }
        } else {
            dcThGeoSetHiddenAddr(payload.addr_tambon || '', payload.addr_amphoe || '', payload.addr_province || '', payload.addr_zipcode || '');
        }
    }
    async function openModal(payload) {
        if (!modal) {
            return;
        }
        document.getElementById('dc_edit_id').value = String(payload.id || '');
        document.getElementById('dc_edit_code').value = payload.customer_code || '';
        document.getElementById('dc_edit_wechat').value = payload.wechat_id || '';
        document.getElementById('dc_edit_line').value = payload.line_id || '';
        document.getElementById('dc_edit_phone').value = payload.phone || '';
        document.getElementById('dc_edit_addr_house_no').value = payload.addr_house_no || '';
        document.getElementById('dc_edit_addr_road_soi').value = payload.addr_road_soi || '';
        document.getElementById('dc_edit_addr_moo').value = payload.addr_moo_village || '';
        document.getElementById('dc_edit_addr_tambon').value = payload.addr_tambon || '';
        document.getElementById('dc_edit_addr_amphoe').value = payload.addr_amphoe || '';
        document.getElementById('dc_edit_addr_province').value = payload.addr_province || '';
        document.getElementById('dc_edit_addr_zipcode').value = payload.addr_zipcode || '';
        document.getElementById('dc_edit_geo').value = payload.geo_position || '';
        document.getElementById('dc_edit_geo_status').value = payload.geo_status || '';
        document.getElementById('dc_edit_rp').value = payload.route_primary || '';
        document.getElementById('dc_edit_rs').value = payload.route_secondary || '';
        document.getElementById('dc_edit_en').value = payload.community_name_en || '';
        document.getElementById('dc_edit_th').value = payload.community_name_th || '';
        document.getElementById('dc_edit_requirement').value = payload.customer_requirement || '';
        var st = payload.customer_state || '正常';
        var sel = document.getElementById('dc_edit_state');
        if (sel) {
            sel.value = st;
        }
        modal.style.display = 'flex';
        if (useThGeoDropdowns) {
            await dcThGeoBootstrapFromPayload(payload);
        }
        dcRefreshAddrPreview();
    }
    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
    }
    if (modal) {
        document.querySelectorAll('.dc-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                try {
                    var payload = JSON.parse(btn.getAttribute('data-row') || '{}');
                    openModal(payload).catch(function () {});
                } catch (e) {}
            });
        });
    }
    (function thGeoWire() {
        var pSel = document.getElementById('dc_th_sel_province');
        var dSel = document.getElementById('dc_th_sel_district');
        var sSel = document.getElementById('dc_th_sel_subdistrict');
        if (!pSel || !dSel || !sSel) {
            return;
        }
        pSel.addEventListener('change', function () {
            var v = parseInt(String(pSel.value || '0'), 10);
            dcThGeoResetCascaded();
            thGeoProvinceTh = '';
            thGeoAmphoeTh = '';
            if (v > 0) {
                var opt = pSel.options[pSel.selectedIndex];
                thGeoProvinceTh = opt ? (opt.getAttribute('data-name-th') || '') : '';
                dcThGeoLoadDistricts(v);
                dcThGeoSetHiddenAddr('', '', thGeoProvinceTh, '');
            } else {
                dcThGeoSetHiddenAddr('', '', '', '');
            }
            dcRefreshAddrPreview();
        });
        dSel.addEventListener('change', function () {
            var v = parseInt(String(dSel.value || '0'), 10);
            sSel.innerHTML = '<option value="">请先选择县</option>';
            sSel.disabled = true;
            var hid = document.getElementById('dc_edit_th_geo_subdistrict_id');
            if (hid) {
                hid.value = '0';
            }
            thGeoAmphoeTh = '';
            if (v > 0) {
                var opt = dSel.options[dSel.selectedIndex];
                thGeoAmphoeTh = opt ? (opt.getAttribute('data-name-th') || '') : '';
                dcThGeoLoadSubdistricts(v);
            }
            dcThGeoSetHiddenAddr('', thGeoAmphoeTh, thGeoProvinceTh, '');
            dcThGeoZipPreview('');
            dcRefreshAddrPreview();
        });
        sSel.addEventListener('change', function () {
            dcThGeoSyncFromSubSelect();
        });
    })();
    if (form) {
        form.addEventListener('input', function (ev) {
            var t = ev.target;
            if (!t || !t.id) {
                return;
            }
            if (/^dc_edit_addr_/.test(t.id) || t.id === 'dc_edit_addr_moo') {
                dcRefreshAddrPreview();
            }
        });
    }

    var detailModal = document.getElementById('dcDetailModal');
    function openDetail(payload) {
        if (!detailModal) return;
        document.getElementById('dc_d_customer_code').textContent = payload.customer_code || '—';
        document.getElementById('dc_d_wechat').textContent = payload.wechat_id || '—';
        document.getElementById('dc_d_line').textContent = payload.line_id || '—';
        document.getElementById('dc_d_phone').textContent = payload.phone || '—';
        document.getElementById('dc_d_geo').textContent = payload.geo_position || '—';
        document.getElementById('dc_d_rp').textContent = payload.route_primary || '—';
        document.getElementById('dc_d_rs').textContent = payload.route_secondary || '—';
        document.getElementById('dc_d_en').textContent = payload.community_name_en || '—';
        document.getElementById('dc_d_th').textContent = payload.community_name_th || '—';
        document.getElementById('dc_d_state').textContent = payload.customer_state || '—';
        document.getElementById('dc_d_requirement').textContent = payload.customer_requirement || '—';
        document.getElementById('dc_d_addr_house_no').textContent = payload.addr_house_no || '—';
        document.getElementById('dc_d_addr_road_soi').textContent = payload.addr_road_soi || '—';
        document.getElementById('dc_d_addr_moo').textContent = payload.addr_moo_village || '—';
        document.getElementById('dc_d_addr_tambon').textContent = payload.addr_tambon || '—';
        document.getElementById('dc_d_addr_amphoe').textContent = payload.addr_amphoe || '—';
        document.getElementById('dc_d_addr_province').textContent = payload.addr_province || '—';
        document.getElementById('dc_d_addr_zipcode').textContent = payload.addr_zipcode || '—';
        document.getElementById('dc_d_geo_status').textContent = payload.geo_status || '—';
        document.getElementById('dc_d_addr_th_full').textContent = payload.addr_th_full || '—';
        document.getElementById('dc_d_addr_en_full').textContent = payload.addr_en_full || '—';
        detailModal.style.display = 'flex';
    }
    function closeDetail() {
        if (detailModal) detailModal.style.display = 'none';
    }
    document.querySelectorAll('.dc-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                var payload = JSON.parse(btn.getAttribute('data-row') || '{}');
                openDetail(payload);
            } catch (e) {}
        });
    });
    var closeBtn = document.getElementById('dc_detail_close');
    if (closeBtn) closeBtn.addEventListener('click', closeDetail);
    var closeDetailX = document.getElementById('dc_detail_close_x');
    if (closeDetailX) closeDetailX.addEventListener('click', closeDetail);
    if (detailModal) {
        detailModal.addEventListener('click', function (e) {
            if (e.target === detailModal) closeDetail();
        });
    }

    var editCancel = document.getElementById('dc_edit_cancel');
    if (editCancel) editCancel.addEventListener('click', closeModal);
    var editCloseX = document.getElementById('dc_edit_close_x');
    if (editCloseX) editCloseX.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }

    var listPath = window.location.pathname + window.location.search;
    document.querySelectorAll('.dc-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('确认删除该派送客户？此操作不可恢复。')) {
                return;
            }
            var fd = new FormData();
            fd.append('delete_delivery_customer', '1');
            fd.append('delivery_id', btn.getAttribute('data-delivery-id') || '');
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
    document.querySelectorAll('.dc-customer-state').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var fd = new FormData();
            fd.append('delivery_customer_state_update', '1');
            fd.append('delivery_id', sel.getAttribute('data-delivery-id') || '');
            fd.append('customer_state', sel.value);
            fetch(listPath, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    return r.text().then(function (text) {
                        var t = (text || '').replace(/^\uFEFF/, '').trim();
                        try {
                            return JSON.parse(t);
                        } catch (e) {
                            throw new Error('bad_json');
                        }
                    });
                })
                .then(function (j) {
                    if (!j || !j.ok) {
                        alert((j && j.error) ? j.error : '更新失败');
                        window.location.reload();
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    alert('网络错误');
                    window.location.reload();
                });
        });
    });
})();
</script>
<?php endif; ?>
