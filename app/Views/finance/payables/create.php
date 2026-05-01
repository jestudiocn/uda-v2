<?php
/** @var array $formData */
/** @var string $error */
/** @var array $parties */
?>
<div class="card">
    <h2>财务管理 / 新增待付款</h2>
    <div class="muted">建立待付款事项，后续可在待付款列表进行确认付款。</div>
</div>

<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <label for="party_id">付款对象（可选）</label>
        <select id="party_id" name="party_id">
            <option value="">请选择对象（若无可在下方输入）</option>
            <?php foreach (($parties ?? []) as $party): ?>
                <?php $pid = (int)($party['id'] ?? 0); ?>
                <option value="<?php echo $pid; ?>" <?php echo ((int)($formData['party_id'] ?? 0) === $pid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($party['party_name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="vendor_name">厂商名称</label>
        <input id="vendor_name" type="text" name="vendor_name" required value="<?php echo htmlspecialchars((string)($formData['vendor_name'] ?? '')); ?>" placeholder="上方已选对象时，将以下拉对象为主">

        <label for="amount">金额</label>
        <input id="amount" type="number" name="amount" min="0.01" step="0.01" required value="<?php echo htmlspecialchars((string)($formData['amount'] ?? '')); ?>">

        <label for="expected_pay_date">预计付款日</label>
        <input id="expected_pay_date" type="date" name="expected_pay_date" required value="<?php echo htmlspecialchars((string)($formData['expected_pay_date'] ?? '')); ?>">

        <label for="remark">备注</label>
        <textarea id="remark" name="remark" rows="3"><?php echo htmlspecialchars((string)($formData['remark'] ?? '')); ?></textarea>

        <label for="voucher">凭证图档（可选）</label>
        <input id="voucher" type="file" name="voucher" accept="image/jpeg,image/png,image/gif,image/webp">

        <div class="form-full inline-actions">
            <button type="submit" name="create_payable" value="1">保存待付款</button>
            <a class="btn" style="background:#64748b;" href="/finance/payables/list">返回列表</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const partySelect = document.getElementById('party_id');
    const vendorInput = document.getElementById('vendor_name');
    if (!partySelect || !vendorInput) return;

    const syncByParty = function () {
        const selected = partySelect.options[partySelect.selectedIndex];
        const hasParty = partySelect.value !== '';
        if (hasParty && selected) {
            vendorInput.value = selected.text || '';
            vendorInput.readOnly = true;
            vendorInput.style.background = '#f3f4f6';
            vendorInput.title = '已选择对象时，此输入由下拉对象控制';
        } else {
            vendorInput.readOnly = false;
            vendorInput.style.background = '';
            vendorInput.title = '';
        }
    };
    partySelect.addEventListener('change', syncByParty);
    syncByParty();
});
</script>
