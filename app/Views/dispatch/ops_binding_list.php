<?php
/** @var bool $schemaReady */
/** @var list<array<string,mixed>> $rows */
/** @var string $error */
/** @var string $message */
$schemaReady = $schemaReady ?? false;
$rows = $rows ?? [];
$error = (string)($error ?? '');
$message = (string)($message ?? '');
$schemaErr = t('dispatch.view.common.schema_not_ready', '数据表未就绪');
?>

<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('dispatch.view.ops_binding.title', '派送业务 / 派送操作 / 绑带列表')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('dispatch.view.ops_binding.subtitle', '按派送客户聚合展示。绑带件数=该客户当前「已入库」「待转发」「待自取」且未在本页点过「完成」的运单件数之和；客户业务状态不限。点「完成」后从本列表消失；该客户若有新扫描产生的上述状态运单会再次出现。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : $schemaErr); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_binding.th_code', '客户编码')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_binding.th_wxline', '微信/Line号')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_binding.th_route', '主/副线路')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_binding.th_count', '绑带件数')); ?></th>
                    <th><?php echo htmlspecialchars(t('dispatch.view.ops_binding.th_op', '操作')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="5" class="muted"><?php echo htmlspecialchars(t('dispatch.view.ops_binding.empty', '暂无需绑带客户（当前无已入库/待转发/待自取待处理货件）')); ?></td></tr>
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
                            <td><?php echo (int)round((float)($r['inbound_count'] ?? 0)); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm(<?php echo json_encode(t('dispatch.view.ops_binding.confirm_complete', '确认完成该客户绑带？'), JSON_UNESCAPED_UNICODE); ?>);">
                                    <input type="hidden" name="action" value="complete_binding">
                                    <input type="hidden" name="delivery_customer_id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                    <button type="submit" class="btn" style="padding:3px 10px;min-height:auto;"><?php echo htmlspecialchars(t('dispatch.view.ops_binding.btn_done', '完成')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
