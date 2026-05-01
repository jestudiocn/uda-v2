<?php
/** @var array<int, array<string, mixed>> $allOptions */
/** @var string $message */
/** @var string $error */
$byGroup = ['category' => [], 'unit' => []];
foreach ($allOptions as $opt) {
    $g = (string)($opt['option_group'] ?? '');
    if (isset($byGroup[$g])) {
        $byGroup[$g][] = $opt;
    }
}
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 类目与单位维护</h2>
    <p class="muted">此处维护的选项会出现在「新增费用记录」的类目与计费单位下拉选单中；停用的选项不会出现在下拉中，但已保存的费用记录不受影响。</p>
    <p><a class="btn" style="background:#64748b;" href="/finance/ar/charges/create">返回新增费用记录</a></p>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <h3>新增选项</h3>
    <form method="post" class="toolbar" style="flex-wrap:wrap;align-items:flex-end;gap:12px;">
        <div>
            <label for="option_group">分组</label>
            <select id="option_group" name="option_group" required>
                <option value="category">费用类目</option>
                <option value="unit">计费单位</option>
            </select>
        </div>
        <div>
            <label for="opt_name">名称</label>
            <input id="opt_name" type="text" name="name" maxlength="100" required placeholder="例如：差旅">
        </div>
        <button type="submit" name="add_ar_dropdown_option" value="1">添加</button>
    </form>
</div>

<?php foreach (['category' => '费用类目', 'unit' => '计费单位'] as $gk => $glabel): ?>
<div class="card">
    <h3><?php echo htmlspecialchars($glabel); ?></h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>名称</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($byGroup[$gk])): ?>
                <tr><td colspan="4" class="muted">暂无记录</td></tr>
            <?php else: ?>
                <?php foreach ($byGroup[$gk] as $opt): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($opt['name'] ?? '')); ?></td>
                        <td><?php echo (int)($opt['sort_order'] ?? 0); ?></td>
                        <td><?php echo ((int)($opt['status'] ?? 0) === 1) ? '启用' : '停用'; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="option_id" value="<?php echo (int)($opt['id'] ?? 0); ?>">
                                <button type="submit" name="toggle_ar_dropdown_option" value="1" class="btn" style="background:#64748b;padding:6px 10px;min-height:auto;">
                                    <?php echo ((int)($opt['status'] ?? 0) === 1) ? '停用' : '启用'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
