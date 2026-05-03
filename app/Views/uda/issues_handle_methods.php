<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
/** @var array $rows */
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.title', 'UDA快件 / 问题订单 / 处理方式管理')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.subtitle', '维护问题订单处理方式字典。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.schema', '表 `problem_order_handle_methods` 不存在，请先执行 `032_problem_order_handle_methods.sql`。')); ?></div>
    <?php return; ?>
<?php endif; ?>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.add_title', '新增处理方式')); ?></h3>
    <form method="post" class="form-grid" style="grid-template-columns:repeat(3,minmax(220px,1fr));gap:10px;align-items:end;">
        <input type="hidden" name="action" value="add_method">
        <div><label><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.method_name', '处理方式名称')); ?></label><input name="method_name" required></div>
        <div><label><?php echo htmlspecialchars(t('uda.common.sort', '排序')); ?></label><input type="number" name="sort_order" value="0" min="0"></div>
        <div><button type="submit"><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.add_btn', '新增处理方式')); ?></button></div>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.list_title', '处理方式列表')); ?></h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th><?php echo htmlspecialchars(t('uda.common.id', 'ID')); ?></th><th><?php echo htmlspecialchars(t('uda.page.issues_handle_methods.col_method', '处理方式')); ?></th><th><?php echo htmlspecialchars(t('uda.common.sort', '排序')); ?></th><th><?php echo htmlspecialchars(t('uda.common.status', '状态')); ?></th><th><?php echo htmlspecialchars(t('uda.common.actions', '操作')); ?></th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="muted"><?php echo htmlspecialchars(t('uda.common.no_data', '暂无数据')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php $active = (int)($r['is_active'] ?? 0) === 1; ?>
                    <tr>
                        <td><?php echo (int)($r['id'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['method_name'] ?? '')); ?></td>
                        <td><?php echo (int)($r['sort_order'] ?? 0); ?></td>
                        <td><?php echo $active ? htmlspecialchars(t('uda.common.active', '启用')) : htmlspecialchars(t('uda.common.inactive', '停用')); ?></td>
                        <td>
                            <?php if ($active): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm(<?php echo json_encode(t('uda.confirm.disable_method', '确定停用这个处理方式吗？'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);">
                                    <input type="hidden" name="action" value="disable_method">
                                    <input type="hidden" name="id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                    <button type="submit"><?php echo htmlspecialchars(t('uda.common.inactive', '停用')); ?></button>
                                </form>
                            <?php else: ?>
                                <span class="muted"><?php echo htmlspecialchars(t('uda.common.disabled', '已停用')); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
