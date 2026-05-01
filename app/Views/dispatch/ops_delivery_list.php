<?php
/** @var bool $schemaReady */
/** @var list<array<string,mixed>> $rows */
/** @var string $error */
$schemaReady = $schemaReady ?? false;
$rows = $rows ?? [];
$error = (string)($error ?? '');
?>

<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 派送列表</h2>
    <div class="muted">按派送客户聚合展示。派送件数=该客户当前“已入库”订单数量。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <div style="overflow:auto;">
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
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="muted">暂无可派送客户（当前无已入库货件）</td></tr>
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

