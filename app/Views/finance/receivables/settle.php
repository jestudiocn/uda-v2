<?php
/** @var array $receivable */
/** @var array $accounts */
/** @var array $categories */
/** @var string $error */
?>
<div class="card">
    <h2>财务管理 / 确认收款</h2>
    <div class="muted">确认后将自动生成一笔收入交易，并把该待收款标记为已收款。</div>
</div>

<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="stat-grid">
        <div class="stat-item"><div class="label">客户</div><div class="value" style="font-size:16px;"><?php echo htmlspecialchars((string)($receivable['client_name'] ?? '')); ?></div></div>
        <div class="stat-item"><div class="label">金额</div><div class="value" style="font-size:16px;"><?php echo number_format((float)($receivable['amount'] ?? 0), 2); ?></div></div>
        <div class="stat-item"><div class="label">预计收款日</div><div class="value" style="font-size:16px;"><?php echo htmlspecialchars((string)($receivable['expected_receive_date'] ?? '')); ?></div></div>
        <div class="stat-item"><div class="label">状态</div><div class="value" style="font-size:16px;"><?php echo htmlspecialchars((string)($receivable['status'] ?? '')); ?></div></div>
    </div>
</div>

<?php $receivableHasVoucher = trim((string)($receivable['voucher_path'] ?? '')) !== ''; ?>
<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="id" value="<?php echo (int)($receivable['id'] ?? 0); ?>">
        <label for="account_id">收款账户</label>
        <select id="account_id" name="account_id" required>
            <option value="">请选择账户</option>
            <?php foreach ($accounts as $acc): ?>
                <option value="<?php echo (int)($acc['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($acc['account_name'] ?? '')); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="category_id">收入类目</label>
        <select id="category_id" name="category_id" required>
            <option value="">请选择类目</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)($cat['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="settle_note">确认说明</label>
        <textarea id="settle_note" name="settle_note" rows="3" placeholder="可填写此次收款补充说明（可选）"></textarea>

        <?php if ($receivableHasVoucher): ?>
            <div class="form-full muted">此笔待收款已附凭证，无需再上传。</div>
            <div class="form-full">
                <button type="button" class="btn js-finance-voucher-open" data-url="/finance/voucher/view?kind=receivable&amp;id=<?php echo (int)($receivable['id'] ?? 0); ?>">查看凭证</button>
            </div>
        <?php else: ?>
            <label for="voucher">凭证图档（可选）</label>
            <input id="voucher" type="file" name="voucher" accept="image/jpeg,image/png,image/gif,image/webp">
        <?php endif; ?>

        <div class="form-full inline-actions">
            <button type="submit" name="settle_receivable" value="1">确认收款</button>
            <a class="btn" style="background:#64748b;" href="/finance/receivables/list">返回列表</a>
        </div>
    </form>
</div>

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
