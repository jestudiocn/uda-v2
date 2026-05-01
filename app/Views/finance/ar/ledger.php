<?php
/** @var array $rows */
/** @var array $parties */
/** @var int $partyId */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 应收台账</h2>
    <form method="get" class="toolbar">
        <label>客户</label>
        <select name="party_id">
            <option value="0">全部</option>
            <?php foreach ($parties as $party): ?>
                <?php $pid = (int)$party['id']; ?>
                <option value="<?php echo $pid; ?>" <?php echo $partyId === $pid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$party['party_name']); ?></option>
            <?php endforeach; ?>
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
            <thead><tr><th>时间</th><th>客户</th><th>账单号</th><th>分录类型</th><th>借方</th><th>贷方</th><th>余额</th><th>关联待收款</th><th>备注</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="muted">暂无台账记录</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)$row['created_at']); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['party_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['invoice_no'] ?? '')); ?></td>
                        <td><span class="chip"><?php echo htmlspecialchars((string)($row['entry_type'] ?? '')); ?></span></td>
                        <td><?php echo number_format((float)($row['debit_amount'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($row['credit_amount'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($row['balance_after'] ?? 0), 2); ?></td>
                        <td><?php echo (int)($row['receivable_id'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['note'] ?? '')); ?></td>
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
                    <a class="btn" style="<?php echo $p === $page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/ar/ledger?party_id=<?php echo (int)$partyId; ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
