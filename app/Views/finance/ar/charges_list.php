<?php
/** @var array $rows */
/** @var array $parties */
/** @var array $pricingModeCatalogue */
/** @var string $statusFilter */
/** @var int $partyId */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 费用记录列表</h2>
    <form method="get" class="toolbar">
        <label>客户</label>
        <select name="party_id">
            <option value="0">全部</option>
            <?php foreach ($parties as $party): ?>
                <?php $pid = (int)$party['id']; ?>
                <option value="<?php echo $pid; ?>" <?php echo $partyId === $pid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$party['party_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <label>状态</label>
        <select name="status">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>全部</option>
            <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>待开票</option>
            <option value="invoiced" <?php echo $statusFilter === 'invoiced' ? 'selected' : ''; ?>>已开票</option>
            <option value="void" <?php echo $statusFilter === 'void' ? 'selected' : ''; ?>>作废</option>
        </select>
        <label>每页</label>
        <select name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo $pp === (int)$perPage ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">筛选</button>
    </form>
</div>
<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>ID</th><th>客户</th><th>费用日期</th><th>类目</th><th>项目</th><th>计费方式</th><th>单价</th><th>数量</th><th>单位</th><th>金额</th><th>状态</th><th>创建人</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="12" class="muted">暂无费用记录</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $pmKey = (string)($row['pricing_mode'] ?? 'line_only');
                    $pmLabel = (string)(($pricingModeCatalogue ?? [])[$pmKey] ?? $pmKey);
                    $bsLab = trim((string)($row['billing_scheme_label'] ?? ''));
                    $modeCell = $bsLab !== '' ? $bsLab : $pmLabel;
                    ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['party_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['billing_date'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['category_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['project_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($modeCell); ?></td>
                        <td><?php echo number_format((float)($row['unit_price'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($row['quantity'] ?? 0), 4); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['unit_name'] ?? '')); ?></td>
                        <td><?php echo number_format((float)($row['calculated_amount'] ?? 0), 2); ?></td>
                        <td><span class="chip"><?php echo htmlspecialchars((string)($row['status'] ?? '')); ?></span></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['creator'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($totalPages > 1): ?>
    <div class="card">
        <div class="toolbar" style="justify-content:space-between;">
            <span class="muted">共 <?php echo (int)$total; ?> 条，第 <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?> 页</span>
            <div class="inline-actions">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="btn" style="<?php echo $p === $page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/ar/charges/list?party_id=<?php echo (int)$partyId; ?>&status=<?php echo urlencode($statusFilter); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
