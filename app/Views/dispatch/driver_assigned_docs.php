<?php
/** @var bool $schemaReady */
/** @var string $error */
/** @var string $message */
/** @var list<array<string,mixed>> $rows */
$schemaReady = $schemaReady ?? false;
$error = (string)($error ?? '');
$message = (string)($message ?? '');
$rows = $rows ?? [];
$byDoc = [];
foreach ($rows as $r) {
    $d = (string)($r['delivery_doc_no'] ?? '');
    if ($d === '') {
        continue;
    }
    if (!isset($byDoc[$d])) {
        $byDoc[$d] = [];
    }
    $byDoc[$d][] = $r;
}
$schemaErr = t('dispatch.view.common.schema_not_ready', '数据表未就绪');
?>
<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : $schemaErr); ?></div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.title', '派送业务 / 派送操作 / 司机派送')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.subtitle', '仅显示已指派给当前账号的派送单分段链接。整单送完后请点「本单派送完成」。')); ?></div>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <?php if ($byDoc === []): ?>
        <p class="muted"><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.empty', '暂无指派给您的分段派送链接。')); ?></p>
    <?php else: ?>
        <?php foreach ($byDoc as $docNo => $segs): ?>
            <?php
            $first = $segs[0] ?? [];
            $planned = (string)($first['planned_delivery_date'] ?? '');
            ?>
            <div style="margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e2e8f0;">
                <div style="font-weight:600;margin-bottom:8px;"><?php echo htmlspecialchars($docNo); ?>
                    <span class="muted" style="font-weight:400;">（<?php echo htmlspecialchars($planned); ?>）</span>
                </div>
                <div class="ud-table-scroll" style="margin-bottom:10px;">
                <table class="data-table">
                    <thead><tr><th><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.th_seg', '段号')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.th_link', '地图 / 签收')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.th_exp', '链接有效期')); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($segs as $seg): ?>
                        <?php
                        $tok = (string)($seg['token'] ?? '');
                        $si = (int)($seg['segment_index'] ?? 0);
                        $exp = (string)($seg['expires_at'] ?? '');
                        $mapsJumpUrl = '/dispatch/driver/segment-maps?t=' . rawurlencode($tok);
                        $podUrl = '/dispatch/driver/run?t=' . rawurlencode($tok);
                        ?>
                        <tr>
                            <td><?php echo $si + 1; ?></td>
                            <td>
                                <a class="btn" href="<?php echo htmlspecialchars($mapsJumpUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(sprintf(t('dispatch.view.driver_assigned.open_seg_maps', 'Google 地图 · 第 %d 段'), $si + 1)); ?></a>
                                <div style="margin-top:8px;">
                                    <a href="<?php echo htmlspecialchars($podUrl); ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars(sprintf(t('dispatch.view.driver_assigned.open_seg_pod', '签收页 · 第 %d 段'), $si + 1)); ?></a>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($exp); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <form method="post" style="display:inline;" onsubmit="return confirm(<?php echo json_encode(sprintf(t('dispatch.view.driver_assigned.confirm_complete', '确认本单 %s 已全部派送完成？'), $docNo), JSON_UNESCAPED_UNICODE); ?>);">
                    <input type="hidden" name="action" value="driver_doc_mark_complete">
                    <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                    <button type="submit" style="background:#0f766e;"><?php echo htmlspecialchars(t('dispatch.view.driver_assigned.btn_complete', '本单派送完成')); ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
