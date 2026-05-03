<?php
/** @var bool $ordersSchemaV2 */
/** @var string $migrationHint */
/** @var array $consigningOptions */
/** @var string $message */
/** @var string $error */
/** @var bool $hideConsigningSelectors */
/** @var bool $dispatchBoundClientMissing */
/** @var list<array{created_at:string,cnt:int}> $importBatchOptions */
/** @var list<array{line:int,reason:string}> $importFailureDetails */
$importBatchOptions = $importBatchOptions ?? [];
$importFailureDetails = $importFailureDetails ?? [];
$dash = t('dispatch.view.common.dash', '—');
$orderStatusDbOptions = [
    '待入库',
    '部分入库',
    '已入库',
    '待自取',
    '待转发',
    '已出库',
    '已自取',
    '已转发',
    '已派送',
    '问题件',
];
$orderStatusLabel = static function (string $s): string {
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
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('dispatch.view.order_import.title', '派送业务 / 订单导入')); ?></h2>
    <?php if ($hideConsigningSelectors): ?>
        <div class="muted"><?php echo t('dispatch.view.order_import.subtitle_client', '当前为<strong>委托派送客户账号</strong>，导入与录入仅作用于绑定委托客户；Excel/CSV 中 <code>consigning_client_code</code> 须与绑定编号一致。完成后可到 <a href="/dispatch">订单查询</a> 核对列表。'); ?></div>
    <?php else: ?>
        <div class="muted"><?php echo t('dispatch.view.order_import.subtitle_staff', '批量 Excel/CSV 与手工录入订单。完成后可到 <a href="/dispatch">订单查询</a> 查看与筛选。'); ?></div>
    <?php endif; ?>
</div>

<?php if (!$ordersSchemaV2 && $migrationHint !== ''): ?>
    <div class="card" style="border-left:4px solid #ca8a04;"><?php echo htmlspecialchars($migrationHint); ?></div>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!empty($importFailureDetails)): ?>
<div class="card" style="border-left:4px solid #ea580c;">
    <h3 style="margin:0 0 8px 0;font-size:15px;"><?php echo htmlspecialchars(t('dispatch.view.order_import.fail_title', '本次导入失败明细')); ?></h3>
    <p class="muted" style="margin:0 0 10px 0;font-size:13px;"><?php echo htmlspecialchars(t('dispatch.view.order_import.fail_hint', '「行号」为导入文件中的行序（第 1 行为表头，第 2 行起为数据）。请按原因修正后重新上传。')); ?></p>
    <div style="overflow:auto;max-height:min(360px,50vh);border:1px solid #e5e7eb;border-radius:6px;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:#f9fafb;text-align:left;">
                    <th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;width:72px;"><?php echo htmlspecialchars(t('dispatch.view.order_import.th_line', '行号')); ?></th>
                    <th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('dispatch.view.order_import.th_reason', '失败原因')); ?></th>
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
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('dispatch.view.index.bound_missing', '账号已绑定委托客户 ID，但该客户在系统中不存在或已被删除。请联系管理员在「系统管理 → 用户管理」中修正绑定，或恢复该委托客户。')); ?></div>
<?php endif; ?>

<?php if (!$dispatchBoundClientMissing): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.order_import.section_bulk', '批量导入订单（Excel / CSV）')); ?></h3>
    <form method="post" enctype="multipart/form-data" action="/dispatch/order-import" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <a class="btn" href="/dispatch/order-import?export=order_csv_template"><?php echo htmlspecialchars(t('dispatch.view.order_import.download_template', '下载导入模板')); ?></a>
        <label for="orders_import_file" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.order_import.choose_file', '选择文件')); ?></label>
        <input id="orders_import_file" name="orders_import_file" type="file" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" style="max-width:min(360px,100%);">
        <button type="submit" name="import_orders" value="1"><?php echo htmlspecialchars(t('dispatch.view.order_import.upload', '上传导入')); ?></button>
    </form>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e5e7eb;">
        <h4 style="margin:0 0 8px 0;font-size:15px;"><?php echo htmlspecialchars(t('dispatch.view.order_import.delete_batch_title', '删除当日导入批次')); ?></h4>
        <div class="muted" style="margin-bottom:8px;font-size:13px;"><?php echo t('dispatch.view.order_import.delete_batch_hint', '仅列出<strong>今天</strong>、且来源为 CSV 导入（<code>import</code>）的批次；依「导入时间」整批删除该时间点写入的全部订单。手工录入若与某批次同秒写入，亦会被一并删除，请谨慎选择。'); ?></div>
        <?php if (empty($importBatchOptions)): ?>
            <div class="muted"><?php echo htmlspecialchars(t('dispatch.view.order_import.no_batch_today', '今日尚无 CSV 导入批次可删除。')); ?></div>
        <?php else: ?>
            <form method="post" action="/dispatch/order-import" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;" id="form-delete-import-batch">
                <label for="import_batch_created_at" style="margin:0;"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_import_time', '导入时间')); ?></label>
                <select id="import_batch_created_at" name="import_batch_created_at" required style="min-width:280px;">
                    <option value="" selected disabled><?php echo htmlspecialchars(t('dispatch.view.order_import.opt_select_batch', '请选择导入批次')); ?></option>
                    <?php foreach ($importBatchOptions as $b): ?>
                        <?php $t = (string)($b['created_at'] ?? ''); ?>
                        <?php
                        $batchLabel = str_replace(
                            ['{time}', '{count}'],
                            [$t, (string)(int)($b['cnt'] ?? 0)],
                            t('dispatch.view.order_import.batch_count', '{time}（{count} 条）')
                        );
                        ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($batchLabel); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="delete_import_batch" value="1" class="btn" style="background:#b91c1c;"><?php echo htmlspecialchars(t('dispatch.view.order_import.btn_delete_batch', '删除所选批次')); ?></button>
            </form>
            <?php
            $orderImportI18n = [
                'confirmDeleteBatch' => t('dispatch.view.order_import.js_confirm_delete_batch', '确定要删除导入时间「{time}」的整批订单吗？此操作无法还原。'),
            ];
            ?>
            <script>
            window.__dispatchOrderImportI18n = <?php echo json_encode($orderImportI18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            document.getElementById('form-delete-import-batch').addEventListener('submit', function (e) {
                var sel = document.getElementById('import_batch_created_at');
                var tv = sel ? sel.value : '';
                var tpl = (window.__dispatchOrderImportI18n && window.__dispatchOrderImportI18n.confirmDeleteBatch) ? String(window.__dispatchOrderImportI18n.confirmDeleteBatch) : '';
                var msg = tpl.split('{time}').join(tv);
                if (!confirm(msg)) {
                    e.preventDefault();
                }
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.order_import.manual_title', '手工录入订单')); ?></h3>
    <form method="post" action="/dispatch/order-import" class="form-grid">
        <?php if ($hideConsigningSelectors): ?>
            <?php $boundOpt = $consigningOptions[0] ?? null; ?>
            <div class="form-full muted" style="grid-column:1/-1;">
                <?php echo t('dispatch.view.order_import.bound_consigning', '委托客户（已绑定）：'); ?><?php echo $boundOpt ? htmlspecialchars(trim((string)($boundOpt['client_code'] ?? '') . ' ' . $dash . ' ' . (string)($boundOpt['client_name'] ?? ''))) : htmlspecialchars($dash); ?>
            </div>
        <?php else: ?>
        <label for="consigning_client_id_m"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_consigning', '委托客户')); ?></label>
        <select id="consigning_client_id_m" name="consigning_client_id" required>
            <option value=""><?php echo htmlspecialchars(t('dispatch.view.order_import.opt_please_select', '请选择')); ?></option>
            <?php foreach ($consigningOptions as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars((string)($o['client_code'] ?? '') . ' ' . $dash . ' ' . (string)($o['client_name'] ?? '')); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label for="original_tracking_no"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_track', '原始单号')); ?></label>
        <input id="original_tracking_no" name="original_tracking_no" maxlength="120" required>
        <label for="delivery_customer_code"><?php echo t('dispatch.view.order_import.label_delivery_code', '派送客户编号 <span class="muted">（可空）</span>'); ?></label>
        <input id="delivery_customer_code" name="delivery_customer_code" maxlength="60">
        <label for="weight_kg"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_weight', '重量（kg）')); ?></label>
        <input id="weight_kg" name="weight_kg" type="text" value="0">
        <label for="length_cm"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_length', '长（cm）')); ?></label>
        <input id="length_cm" name="length_cm" type="text" value="0">
        <label for="width_cm"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_width', '宽（cm）')); ?></label>
        <input id="width_cm" name="width_cm" type="text" value="0">
        <label for="height_cm"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_height', '高（cm）')); ?></label>
        <input id="height_cm" name="height_cm" type="text" value="0">
        <label for="volume_m3"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_volume', '体积（m³）')); ?></label>
        <input id="volume_m3" name="volume_m3" type="text" value="0">
        <label for="quantity"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_quantity', '数量')); ?></label>
        <input id="quantity" name="quantity" type="text" value="1">
        <label for="inbound_batch"><?php echo htmlspecialchars(t('dispatch.view.order_import.label_inbound_batch', '入库批次')); ?></label>
        <input id="inbound_batch" name="inbound_batch" maxlength="100">
        <label for="order_status"><?php echo t('dispatch.view.order_import.label_order_status', '订单状态 <span class="muted">（留空=待入库）</span>'); ?></label>
        <select id="order_status" name="order_status">
            <option value="" selected><?php echo htmlspecialchars(t('dispatch.view.order_import.opt_wait_default', '待入库（默认）')); ?></option>
            <?php foreach ($orderStatusDbOptions as $st): ?>
                <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($orderStatusLabel($st)); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-full">
            <button type="submit" name="add_waybill" value="1"><?php echo htmlspecialchars(t('dispatch.view.consigning.btn_save', '保存')); ?></button>
        </div>
    </form>
</div>
<?php endif; ?>
