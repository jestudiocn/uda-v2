<?php
/** @var array $formData */
/** @var string $error */
/** @var array $parties */
?>
<div class="card">
    <h2>财务管理 / 新增待收款</h2>
    <div class="muted">建立待收款事项，后续可在待收款列表进行确认收款。</div>
</div>

<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <label for="party_id">收款对象（可选）</label>
        <select id="party_id" name="party_id">
            <option value="">请选择对象（若无可在下方输入）</option>
            <?php foreach (($parties ?? []) as $party): ?>
                <?php $pid = (int)($party['id'] ?? 0); ?>
                <option value="<?php echo $pid; ?>" <?php echo ((int)($formData['party_id'] ?? 0) === $pid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($party['party_name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="client_name">客户名称</label>
        <input id="client_name" type="text" name="client_name" required value="<?php echo htmlspecialchars((string)($formData['client_name'] ?? '')); ?>" placeholder="上方已选对象时，将以下拉对象为主">

        <label for="amount">金额</label>
        <input id="amount" type="number" name="amount" min="0.01" step="0.01" required value="<?php echo htmlspecialchars((string)($formData['amount'] ?? '')); ?>">

        <label for="expected_receive_date">预计收款日</label>
        <input id="expected_receive_date" type="date" name="expected_receive_date" required value="<?php echo htmlspecialchars((string)($formData['expected_receive_date'] ?? '')); ?>">

        <label for="remark">备注</label>
        <textarea id="remark" name="remark" rows="3"><?php echo htmlspecialchars((string)($formData['remark'] ?? '')); ?></textarea>

        <label for="voucher">凭证图档（可选）</label>
        <input id="voucher" type="file" name="voucher" accept="image/jpeg,image/png,image/gif,image/webp">

        <div class="form-full inline-actions">
            <button type="submit" name="create_receivable" value="1">保存待收款</button>
            <a class="btn" style="background:#64748b;" href="/finance/receivables/list">返回列表</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const partySelect = document.getElementById('party_id');
    const clientInput = document.getElementById('client_name');
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
