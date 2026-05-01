<?php
/** @var array $accounts */
/** @var array $categories */
/** @var array $formData */
/** @var string $error */
/** @var array $parties */
?>
<div class="card">
    <h2>财务管理 / 新增财务记录</h2>
</div>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <label for="type">类型</label>
        <select id="type" name="type" required>
            <option value="income" <?php echo (($formData['type'] ?? '') === 'income') ? 'selected' : ''; ?>>收入</option>
            <option value="expense" <?php echo (($formData['type'] ?? '') === 'expense') ? 'selected' : ''; ?>>支出</option>
        </select>
        <label for="amount">金额</label>
        <input id="amount" type="number" name="amount" min="0.01" step="0.01" required value="<?php echo htmlspecialchars((string)($formData['amount'] ?? '')); ?>">
        <label for="party_id">付款收款对象（可选）</label>
        <select id="party_id" name="party_id">
            <option value="">不选择</option>
            <?php foreach (($parties ?? []) as $party): ?>
                <?php $pid = (int)($party['id'] ?? 0); ?>
                <option value="<?php echo $pid; ?>" <?php echo ((int)($formData['party_id'] ?? 0) === $pid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($party['party_name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="client">对象（客户/厂商）</label>
        <input id="client" type="text" name="client" value="<?php echo htmlspecialchars((string)($formData['client'] ?? '')); ?>" placeholder="若上方已选择对象，将以选单对象为主">
        <label for="category_id">类目</label>
        <select id="category_id" name="category_id" required>
            <option value="">请选择</option>
            <?php foreach ($categories as $cat): ?>
                <?php $catId = (int)($cat['id'] ?? 0); ?>
                <option value="<?php echo $catId; ?>" <?php echo ((int)($formData['category_id'] ?? 0) === $catId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?>（<?php echo ((string)($cat['type'] ?? '') === 'income') ? '收入' : '支出'; ?>）
                </option>
            <?php endforeach; ?>
        </select>
        <label for="account_id">账户</label>
        <select id="account_id" name="account_id" required>
            <option value="">请选择</option>
            <?php foreach ($accounts as $acc): ?>
                <?php $accId = (int)($acc['id'] ?? 0); ?>
                <option value="<?php echo $accId; ?>" <?php echo ((int)($formData['account_id'] ?? 0) === $accId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($acc['account_name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="description">说明</label>
        <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars((string)($formData['description'] ?? '')); ?></textarea>
        <label for="voucher">凭证图档（可选）</label>
        <input id="voucher" type="file" name="voucher" accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="form-full inline-actions">
            <button type="submit" name="create_transaction" value="1">保存记录</button>
            <a class="btn" style="background:#64748b;" href="/finance/transactions/list">返回列表</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const partySelect = document.getElementById('party_id');
    const clientInput = document.getElementById('client');
    if (!partySelect || !clientInput) return;

    const syncByParty = function () {
        const selected = partySelect.options[partySelect.selectedIndex];
        const hasParty = partySelect.value !== '';
        if (hasParty && selected) {
            clientInput.value = selected.text || '';
            clientInput.readOnly = true;
            clientInput.style.background = '#f3f4f6';
            clientInput.title = '已选择对象时，此输入由下拉对象控制';
        } else {
            clientInput.readOnly = false;
            clientInput.style.background = '';
            clientInput.title = '';
        }
    };
    partySelect.addEventListener('change', syncByParty);
    syncByParty();
});
</script>
