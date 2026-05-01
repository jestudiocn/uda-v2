<?php
/** @var array $row */
/** @var array $accounts */
/** @var array $categories */
/** @var string $error */
?>
<div class="card">
    <h2>财务管理 / 编辑财务记录</h2>
</div>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<div class="card">
    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>">
        <label for="type">类型</label>
        <select id="type" name="type" required>
            <option value="income" <?php echo ((string)($row['type'] ?? '') === 'income') ? 'selected' : ''; ?>>收入</option>
            <option value="expense" <?php echo ((string)($row['type'] ?? '') === 'expense') ? 'selected' : ''; ?>>支出</option>
        </select>
        <label for="amount">金额</label>
        <input id="amount" type="number" name="amount" min="0.01" step="0.01" required value="<?php echo htmlspecialchars((string)($row['amount'] ?? '')); ?>">
        <label for="client">对象（客户/厂商）</label>
        <input id="client" type="text" name="client" value="<?php echo htmlspecialchars((string)($row['client'] ?? '')); ?>">
        <label for="category_id">类目</label>
        <select id="category_id" name="category_id" required>
            <option value="">请选择</option>
            <?php foreach ($categories as $cat): ?>
                <?php $catId = (int)($cat['id'] ?? 0); ?>
                <option value="<?php echo $catId; ?>" <?php echo ((int)($row['category_id'] ?? 0) === $catId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?>（<?php echo ((string)($cat['type'] ?? '') === 'income') ? '收入' : '支出'; ?>）
                </option>
            <?php endforeach; ?>
        </select>
        <label for="account_id">账户</label>
        <select id="account_id" name="account_id" required>
            <option value="">请选择</option>
            <?php foreach ($accounts as $acc): ?>
                <?php $accId = (int)($acc['id'] ?? 0); ?>
                <option value="<?php echo $accId; ?>" <?php echo ((int)($row['account_id'] ?? 0) === $accId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($acc['account_name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="description">说明</label>
        <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars((string)($row['description'] ?? '')); ?></textarea>
        <div class="form-full inline-actions">
            <button type="submit" name="update_transaction" value="1">保存变更</button>
            <a class="btn" style="background:#64748b;" href="/finance/transactions/list">返回列表</a>
        </div>
    </form>
</div>
