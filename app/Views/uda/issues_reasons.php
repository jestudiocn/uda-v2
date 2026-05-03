<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
/** @var array $locations */
/** @var array $rows */
?>
<div class="card"><h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.issues_reasons.title', 'UDA快件 / 问题订单 / 问题原因管理')); ?></h2></div>
<?php if (!$schemaReady): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.issues_reasons.schema', '问题原因相关表不存在。')); ?></div><?php return; endif; ?>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('uda.page.issues_reasons.add_title', '新增问题原因')); ?></h3>
    <form method="post" class="form-grid" style="grid-template-columns:repeat(4,minmax(220px,1fr));gap:10px;align-items:end;">
        <input type="hidden" name="action" value="add_reason">
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.issues_reasons.parent_location', '所属地点')); ?></label>
            <select name="location_id" required>
                <option value=""><?php echo htmlspecialchars(t('uda.common.select_location', '请选择地点')); ?></option>
                <?php foreach ($locations as $loc): ?>
                    <?php if ((int)($loc['is_active'] ?? 0) === 1): ?>
                        <option value="<?php echo (int)($loc['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($loc['location_name'] ?? '')); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_reasons.reason_name', '原因名称')); ?></label><input name="reason_name" required></div>
        <div><label><?php echo htmlspecialchars(t('uda.common.sort', '排序')); ?></label><input type="number" name="sort_order" value="0" min="0"></div>
        <div><button type="submit"><?php echo htmlspecialchars(t('uda.page.issues_reasons.add_btn', '新增原因')); ?></button></div>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('uda.page.issues_reasons.list_title', '原因列表')); ?></h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th><?php echo htmlspecialchars(t('uda.common.id', 'ID')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_reasons.col_location', '地点')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_reasons.reason_name', '原因名称')); ?></th><th><?php echo htmlspecialchars(t('uda.common.sort', '排序')); ?></th><th><?php echo htmlspecialchars(t('uda.common.status', '状态')); ?></th><th><?php echo htmlspecialchars(t('uda.common.actions', '操作')); ?></th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="muted"><?php echo htmlspecialchars(t('uda.common.no_data', '暂无数据')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): $active=(int)($r['is_active']??0)===1; ?>
                    <tr>
                        <td><?php echo (int)($r['id'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['location_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['reason_name'] ?? '')); ?></td>
                        <td><?php echo (int)($r['sort_order'] ?? 0); ?></td>
                        <td><?php echo $active ? htmlspecialchars(t('uda.common.active', '启用')) : htmlspecialchars(t('uda.common.inactive', '停用')); ?></td>
                        <td>
                            <?php if ($active): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm(<?php echo json_encode(t('uda.confirm.disable_reason', '确定停用这个问题原因吗？'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);">
                                    <input type="hidden" name="action" value="disable_reason"><input type="hidden" name="id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                    <button type="submit"><?php echo htmlspecialchars(t('uda.common.inactive', '停用')); ?></button>
                                </form>
                            <?php else: ?><span class="muted"><?php echo htmlspecialchars(t('uda.common.disabled', '已停用')); ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
