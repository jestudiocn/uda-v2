<?php
/** @var bool $schemaReady */
/** @var list<array<string,mixed>> $rows */
/** @var string $error */
/** @var string $message */
$schemaReady = $schemaReady ?? false;
$rows = $rows ?? [];
$error = (string)($error ?? '');
$message = (string)($message ?? '');
?>

<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 绑带列表</h2>
    <div class="muted">按派送客户聚合展示。绑带件数=该客户当前“已入库”订单数量；点击完成后该客户会从本列表消失。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>客户编码</th>
                    <th>微信/Line号</th>
                    <th>主/副线路</th>
                    <th>绑带件数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="5" class="muted">暂无需绑带客户（当前无已入库货件）</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $wx = trim((string)($r['wechat_id'] ?? ''));
                        $ln = trim((string)($r['line_id'] ?? ''));
                        $wxLine = $wx === '' ? ($ln === '' ? '-' : $ln) : ($ln === '' ? $wx : ($wx . ' / ' . $ln));
                        $rp = trim((string)($r['route_primary'] ?? ''));
                        $rs = trim((string)($r['route_secondary'] ?? ''));
                        $routeText = $rp !== '' || $rs !== '' ? ($rp . '/' . $rs) : '-';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($r['customer_code'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($wxLine); ?></td>
                            <td><?php echo htmlspecialchars($routeText); ?></td>
                            <td><?php echo (int)($r['inbound_count'] ?? 0); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确认完成该客户绑带？');">
                                    <input type="hidden" name="action" value="complete_binding">
                                    <input type="hidden" name="delivery_customer_id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                    <button type="submit" class="btn" style="padding:3px 10px;min-height:auto;">完成</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

