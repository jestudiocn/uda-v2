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
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 生成派送单</h2>
    <div class="muted">先生成派送单号（预计派送日期 + 派送线），再勾选下方客户生成派送单。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
                        <tr><td colspan="8" class="muted">暂无可绑定客户（当前无已入库货件）</td></tr>
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
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($r['customer_code'] ?? '')); ?></td>
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
            <button type="submit">生成派送单</button>
        </div>
    </form>
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

