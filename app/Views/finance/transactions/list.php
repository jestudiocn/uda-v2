<?php
/** @var array $rows */
/** @var string $message */
/** @var string $typeFilter */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <div class="toolbar" style="justify-content:space-between;">
        <h2 class="page-title">财务管理 / 财务记录列表</h2>
        <a class="btn" href="/finance/transactions/create">新增财务记录</a>
    </div>
    <form method="get" class="toolbar">
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
        <button type="submit">筛选</button>
    </form>
</div>
<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

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
                <th>凭证</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="muted">暂无记录</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo (int)($row['id'] ?? 0); ?></td>
                        <td><span class="chip"><?php echo ((string)($row['type'] ?? '') === 'income') ? '收入' : '支出'; ?></span></td>
                        <td><?php echo number_format((float)($row['amount'] ?? 0), 2); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['client_display'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['category_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['account_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['creator'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['created_at'] ?? '')); ?></td>
                        <td>
                            <?php if (trim((string)($row['voucher_path'] ?? '')) !== ''): ?>
                                <button type="button" class="btn js-finance-voucher-open" style="padding:6px 10px;min-height:auto;" data-url="/finance/voucher/view?kind=transaction&amp;id=<?php echo (int)($row['id'] ?? 0); ?>">查看</button>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="btn" style="padding:6px 10px;min-height:auto;" href="/finance/transactions/edit?id=<?php echo (int)($row['id'] ?? 0); ?>">编辑</a></td>
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
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/transactions/list?type=<?php echo urlencode((string)$typeFilter); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="finance-voucher-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;" role="dialog" aria-modal="true">
    <div style="background:#fff;border-radius:8px;max-width:min(920px,96vw);max-height:92vh;overflow:auto;position:relative;padding:16px 16px 20px;">
        <button type="button" class="btn js-fv-close" style="position:absolute;top:10px;right:10px;z-index:1;">关闭</button>
        <img id="finance-voucher-modal-img" src="" alt="凭证" style="max-width:100%;height:auto;display:block;margin-top:36px;">
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('finance-voucher-modal');
    var img = document.getElementById('finance-voucher-modal-img');
    if (!modal || !img) return;
    function openModal(url) {
        img.src = url;
        modal.style.display = 'flex';
    }
    function closeModal() {
        modal.style.display = 'none';
        img.src = '';
    }
    document.querySelectorAll('.js-finance-voucher-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var u = btn.getAttribute('data-url');
            if (u) openModal(u);
        });
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    modal.querySelectorAll('.js-fv-close').forEach(function (b) {
        b.addEventListener('click', closeModal);
    });
});
</script>
