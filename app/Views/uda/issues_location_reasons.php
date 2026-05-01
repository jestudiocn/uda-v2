<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
/** @var array $locations */
/** @var array $reasons */
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">UDA快件 / 问题订单 / 地点原因维护</h2>
    <div class="muted">已将 V1 的「问题原因管理 + 地点管理」合并到同页。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">相关表不存在，请先执行 `031_uda_express_problem_orders_tables.sql`。</div>
    <?php return; ?>
<?php endif; ?>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;">新增地点</h3>
    <form method="post" class="form-grid" style="grid-template-columns:repeat(3,minmax(220px,1fr));gap:10px;align-items:end;">
        <input type="hidden" name="action" value="add_location">
        <div><label>地点名称</label><input name="location_name" required></div>
        <div><label>排序</label><input type="number" name="sort_order" value="0" min="0"></div>
        <div><button type="submit">新增地点</button></div>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;">新增问题原因</h3>
    <form method="post" class="form-grid" style="grid-template-columns:repeat(4,minmax(220px,1fr));gap:10px;align-items:end;">
        <input type="hidden" name="action" value="add_reason">
        <div>
            <label>所属地点</label>
            <select name="location_id" required>
                <option value="">请选择地点</option>
                <?php foreach ($locations as $loc): ?>
                    <?php if ((int)($loc['is_active'] ?? 0) === 1): ?>
                        <option value="<?php echo (int)($loc['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($loc['location_name'] ?? '')); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>原因名称</label><input name="reason_name" required></div>
        <div><label>排序</label><input type="number" name="sort_order" value="0" min="0"></div>
        <div><button type="submit">新增原因</button></div>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;">地点列表</h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>ID</th><th>地点名称</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($locations)): ?>
                <tr><td colspan="5" class="muted">暂无数据</td></tr>
            <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                    <?php $active = (int)($loc['is_active'] ?? 0) === 1; ?>
                    <tr>
                        <td><?php echo (int)($loc['id'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($loc['location_name'] ?? '')); ?></td>
                        <td><?php echo (int)($loc['sort_order'] ?? 0); ?></td>
                        <td><?php echo $active ? '启用' : '停用'; ?></td>
                        <td>
                            <?php if ($active): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定停用这个地点吗？');">
                                    <input type="hidden" name="action" value="disable_location">
                                    <input type="hidden" name="id" value="<?php echo (int)($loc['id'] ?? 0); ?>">
                                    <button type="submit">停用</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">已停用</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0;">问题原因列表</h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>ID</th><th>地点</th><th>原因名称</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($reasons)): ?>
                <tr><td colspan="6" class="muted">暂无数据</td></tr>
            <?php else: ?>
                <?php foreach ($reasons as $row): ?>
                    <?php $active = (int)($row['is_active'] ?? 0) === 1; ?>
                    <tr>
                        <td><?php echo (int)($row['id'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['location_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['reason_name'] ?? '')); ?></td>
                        <td><?php echo (int)($row['sort_order'] ?? 0); ?></td>
                        <td><?php echo $active ? '启用' : '停用'; ?></td>
                        <td>
                            <?php if ($active): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定停用这个问题原因吗？');">
                                    <input type="hidden" name="action" value="disable_reason">
                                    <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                    <button type="submit">停用</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">已停用</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
