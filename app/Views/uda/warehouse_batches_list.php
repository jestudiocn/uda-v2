<?php
/** @var bool $schemaReady */
/** @var array<int,array<string,mixed>> $rows */
/** @var string $message */
/** @var string $error */
$rows = (isset($warehouseRows) && is_array($warehouseRows)) ? $warehouseRows : ($rows ?? []);
$qDateNo = (string)($_GET['q_date_no'] ?? '');
$qBillNo = (string)($_GET['q_bill_no'] ?? '');
$qTrackingNo = (string)($_GET['q_tracking_no'] ?? '');
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.title', 'UDA快件 / 批次操作 / 批次列表')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.subtitle', '列表支持按日期号、提单号、面单号筛选，操作含查/改/删。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.schema', '数据表未就绪，请执行 <code>039_uda_warehouse_batch_tables.sql</code>。')); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="get" style="display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:10px;align-items:end;">
        <div><label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?></label><input name="q_date_no" value="<?php echo htmlspecialchars($qDateNo); ?>"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.bill_no', '提单号')); ?></label><input name="q_bill_no" value="<?php echo htmlspecialchars($qBillNo); ?>"></div>
        <div><label><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.tracking', '面单号')); ?></label><input name="q_tracking_no" value="<?php echo htmlspecialchars($qTrackingNo); ?>"></div>
        <div class="inline-actions"><button type="submit"><?php echo htmlspecialchars(t('uda.common.query', '查询')); ?></button><a class="btn" href="/uda/warehouse/bundles"><?php echo htmlspecialchars(t('uda.common.reset', '重置')); ?></a><a class="btn" href="/uda/warehouse/create-bundle"><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.create_link', '批次录入')); ?></a></div>
    </form>
</div>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.date_no', '日期号')); ?></th><th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.bill_no', '提单号')); ?></th><th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.col_uda', 'UDA件数')); ?></th><th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.col_jd', 'JD件数')); ?></th><th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.col_total', '总件数')); ?></th><th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.col_flight', '航班日期')); ?></th><th><?php echo htmlspecialchars(t('uda.page.warehouse_batches_list.col_customs', '清关完成提货日期')); ?></th><th><?php echo htmlspecialchars(t('uda.common.actions', '操作')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="muted"><?php echo htmlspecialchars(t('uda.common.no_data', '暂无数据')); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $id = (int)($r['id'] ?? 0); ?>
                        <?php $udaCount = array_key_exists('uda_count', $r) ? $r['uda_count'] : null; ?>
                        <?php $jdCount = array_key_exists('jd_count', $r) ? $r['jd_count'] : null; ?>
                        <?php $totalCount = array_key_exists('total_count', $r) ? $r['total_count'] : null; ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($r['date_no'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['bill_no'] ?? '')); ?></td>
                            <td><?php echo $udaCount === null ? '-' : (int)$udaCount; ?></td>
                            <td><?php echo $jdCount === null ? '-' : (int)$jdCount; ?></td>
                            <td><?php echo $totalCount === null ? '-' : (int)$totalCount; ?></td>
                            <td><?php echo htmlspecialchars((string)($r['flight_date'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['customs_pickup_date'] ?? '')); ?></td>
                            <td style="white-space:nowrap;">
                                <a class="btn" style="padding:4px 8px;min-height:auto;" href="/uda/warehouse/batch-view?id=<?php echo $id; ?>"><?php echo htmlspecialchars(t('uda.common.view', '查')); ?></a>
                                <a class="btn" style="padding:4px 8px;min-height:auto;" href="/uda/warehouse/batch-edit?id=<?php echo $id; ?>"><?php echo htmlspecialchars(t('uda.common.edit', '改')); ?></a>
                                <form method="post" style="display:inline;" onsubmit="return confirm(<?php echo json_encode(t('uda.confirm.delete_batch', '确定删除该批次及全部面单？'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn" style="padding:4px 8px;min-height:auto;background:#b91c1c;color:#fff;"><?php echo htmlspecialchars(t('uda.common.delete', '删')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
