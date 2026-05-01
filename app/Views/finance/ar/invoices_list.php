<?php
/** @var array $rows */
/** @var array $parties */
/** @var string $message */
/** @var string $error */
/** @var string $statusFilter */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 账单列表</h2>
    <?php if ($message !== ''): ?><div style="border-left:4px solid #16a34a;padding-left:8px;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div style="border-left:4px solid #dc2626;padding-left:8px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
</div>

<div class="card">
    <h3>生成账单（自动转待收款）</h3>
    <form method="post" class="toolbar">
        <label>客户</label>
        <select name="party_id" required>
            <option value="">请选择客户</option>
            <?php foreach ($parties as $party): ?>
                <option value="<?php echo (int)$party['id']; ?>"><?php echo htmlspecialchars((string)$party['party_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <label>期间开始</label><input type="date" name="period_start" required>
        <label>期间结束</label><input type="date" name="period_end" required>
        <label>开票日</label><input type="date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
        <button type="submit" name="build_invoice" value="1">生成账单并转待收款</button>
    </form>
</div>

<div class="card">
    <form method="get" class="toolbar">
        <label>状态</label>
        <select name="status">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>全部</option>
            <option value="issued" <?php echo $statusFilter === 'issued' ? 'selected' : ''; ?>>未收款</option>
            <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>已收款</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>作废</option>
        </select>
        <label>每页</label>
        <select name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo $pp === (int)$perPage ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">筛选</button>
    </form>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>账单号</th><th>客户</th><th>期间</th><th>金额</th><th>状态</th><th>待收款ID</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="muted">暂无账单</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)$row['invoice_no']); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)$row['party_name']); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)$row['period_start'] . ' ~ ' . (string)$row['period_end'])); ?></td>
                        <td><?php echo number_format((float)$row['total_amount'], 2); ?></td>
                        <td><span class="chip"><?php echo htmlspecialchars((string)$row['status']); ?></span></td>
                        <td><?php echo (int)($row['receivable_id'] ?? 0); ?></td>
                        <td class="cell-actions">
                            <a class="btn" style="padding:6px 10px;min-height:auto;" href="/finance/ar/invoices/view?id=<?php echo (int)$row['id']; ?>">详情</a>
                            <?php if ((string)$row['status'] === 'issued'): ?>
                                <a class="btn" style="padding:6px 10px;min-height:auto;" href="/finance/ar/invoices/export-unpaid?party_id=<?php echo (int)$row['party_id']; ?>">导出 CSV</a>
                                <a class="btn" style="padding:6px 10px;min-height:auto;background:#0d9488;" href="#" onclick="openArUnpaidPrint(<?php echo (int)$row['party_id']; ?>); return false;">打印 / PDF</a>
                            <?php endif; ?>
                        </td>
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
                    <a class="btn" style="<?php echo $p === $page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/ar/invoices/list?status=<?php echo urlencode($statusFilter); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="arUnpaidPrintModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:9999;padding:12px;overflow:auto;">
    <div class="modal-inner" style="max-width:min(1040px,100%);height:min(90vh,840px);margin:2vh auto;background:#fff;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.25);display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:10px 14px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-shrink:0;">
            <strong>未收款明细 — 打印 / PDF</strong>
            <button type="button" class="btn" id="arUnpaidPrintModalClose" style="min-height:auto;padding:6px 12px;background:#64748b;">关闭窗口</button>
        </div>
        <iframe id="arUnpaidPrintFrame" title="未收款明细打印" style="flex:1;border:0;width:100%;min-height:400px;background:#f8fafc;"></iframe>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('arUnpaidPrintModal');
    var frame = document.getElementById('arUnpaidPrintFrame');
    var closeBtn = document.getElementById('arUnpaidPrintModalClose');
    if (!modal || !frame) {
        return;
    }
    function closeArUnpaidPrintModal() {
        modal.style.display = 'none';
        frame.src = 'about:blank';
    }
    window.openArUnpaidPrint = function (partyId) {
        frame.src = '/finance/ar/invoices/print-unpaid?party_id=' + encodeURIComponent(String(partyId));
        modal.style.display = 'block';
    };
    if (closeBtn) {
        closeBtn.addEventListener('click', closeArUnpaidPrintModal);
    }
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeArUnpaidPrintModal();
        }
    });
    window.addEventListener('message', function (e) {
        if (e.origin !== window.location.origin) {
            return;
        }
        var d = e.data;
        if (d && d.type === 'ar-unpaid-print-close') {
            closeArUnpaidPrintModal();
        }
    });
})();
</script>
