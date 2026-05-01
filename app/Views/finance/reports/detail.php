<?php
/** @var string $startDate */
/** @var string $endDate */
/** @var string $typeFilter */
/** @var array $rows */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <div class="toolbar" style="justify-content:space-between;">
        <h2 class="page-title">财务管理 / 报表明细</h2>
        <a class="btn" style="background:#64748b;" href="/finance/reports/overview?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">返回总览</a>
    </div>
    <form method="get" class="toolbar">
        <label for="start_date">开始日期</label>
        <input id="start_date" type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
        <label for="end_date">结束日期</label>
        <input id="end_date" type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
        <label for="type">类型</label>
        <select id="type" name="type">
            <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>全部</option>
            <option value="income" <?php echo $typeFilter === 'income' ? 'selected' : ''; ?>>收入</option>
            <option value="expense" <?php echo $typeFilter === 'expense' ? 'selected' : ''; ?>>支出</option>
        </select>
        <label for="per_page">每页</label>
        <select id="per_page" name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">查询</button>
    </form>
</div>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>类型</th>
                <th>金额</th>
                <th>对象</th>
                <th>类目</th>
                <th>账户</th>
                <th>创建人</th>
                <th>时间</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="muted">暂无明细</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo (int)($row['id'] ?? 0); ?></td>
                        <td><span class="chip"><?php echo ((string)($row['type'] ?? '') === 'income') ? '收入' : '支出'; ?></span></td>
                        <td><?php echo number_format((float)($row['amount'] ?? 0), 2); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['client'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['category_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['account_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['creator'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['created_at'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
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
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/reports/detail?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&type=<?php echo urlencode($typeFilter); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
