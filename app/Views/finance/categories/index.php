<?php
/** @var array $rows */
/** @var string $message */
/** @var string $error */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2>财务管理 / 类目管理</h2>
</div>
<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <label for="name">类目名称</label>
        <input id="name" type="text" name="name" required>
        <label for="type">类型</label>
        <select id="type" name="type" required>
            <option value="income">收入</option>
            <option value="expense">支出</option>
        </select>
        <div class="form-full">
            <button type="submit" name="create_category" value="1">新增类目</button>
        </div>
    </form>
</div>

<div class="card">
    <form method="get" class="toolbar" style="margin-bottom:10px;">
        <label for="per_page">每页</label>
        <select id="per_page" name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">应用</button>
    </form>
    <div style="overflow:auto;">
        <table class="data-table table-valign-middle">
            <thead>
            <tr>
                <th>ID</th>
                <th>类目名称</th>
                <th>类型</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo (int)($row['id'] ?? 0); ?></td>
                    <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['name'] ?? '')); ?></td>
                    <td><?php echo ((string)($row['type'] ?? 'income') === 'income') ? '收入' : '支出'; ?></td>
                    <td><span class="chip"><?php echo ((int)($row['status'] ?? 0) === 1) ? '启用' : '停用'; ?></span></td>
                    <td>
                        <form method="post" action="/finance/categories" style="display:inline;">
                            <input type="hidden" name="toggle_category" value="1">
                            <input type="hidden" name="category_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                            <button class="btn" style="padding:6px 10px;min-height:auto;" type="submit">切换状态</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (($totalPages ?? 1) > 1): ?>
    <div class="card">
        <div class="toolbar" style="justify-content:space-between;">
            <span class="muted">共 <?php echo (int)($total ?? 0); ?> 条，第 <?php echo (int)($page ?? 1); ?> / <?php echo (int)($totalPages ?? 1); ?> 页</span>
            <div class="inline-actions">
                <?php for ($p = 1; $p <= (int)$totalPages; $p++): ?>
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/categories?per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
