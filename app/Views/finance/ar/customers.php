<?php
/** @var array $rows */
/** @var array $parties */
/** @var array<int, list<array<string, mixed>>> $partySchemesForForm */
/** @var string $message */
/** @var string $error */
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 客户计费档案</h2>
    <div class="muted">全站应收以泰铢（THB）计价。若已在「计费方式维护」中为客户建立方案，此处需选择默认计费方式；否则可使用下方兼容模式（旧版单笔形态）。</div>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="post" class="form-grid" id="arCustomerProfileForm">
        <label for="party_id">客户</label>
        <select id="party_id" name="party_id" required>
            <option value="">请选择客户</option>
            <?php foreach ($parties as $party): ?>
                <option value="<?php echo (int)$party['id']; ?>"><?php echo htmlspecialchars((string)$party['party_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="tax_mode">税模式</label>
        <select id="tax_mode" name="tax_mode">
            <option value="excluded">未税</option>
            <option value="included">含税</option>
        </select>
        <label for="billing_cycle">结算周期</label>
        <select id="billing_cycle" name="billing_cycle">
            <option value="monthly">月结</option>
            <option value="per_order">次结</option>
        </select>
        <label for="default_billing_scheme_id">默认计费方式</label>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
            <select id="default_billing_scheme_id" name="default_billing_scheme_id" style="flex:1;min-width:200px;">
                <option value="0">请先选择客户</option>
            </select>
            <a href="/finance/ar/billing-schemes" target="_blank" rel="noopener" class="muted" id="billing_scheme_maint_link">计费方式维护</a>
        </div>
        <div class="form-full" id="legacy_mode_wrap" style="display:none;">
            <label for="legacy_pricing_mode">兼容计费形态（无计费方案时使用）</label>
            <select id="legacy_pricing_mode" name="legacy_pricing_mode">
                <option value="line_only">按量计价（单价 × 数量）</option>
                <option value="base_plus_line">固定费用 + 按量</option>
            </select>
        </div>
        <div class="form-full">
            <button type="submit" name="save_ar_customer" value="1">保存客户计费档案</button>
        </div>
    </form>
</div>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>客户</th><th>税模式</th><th>周期</th><th>计费方式</th><th>更新时间</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="muted">暂无客户计费档案</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['party_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['tax_mode'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['billing_cycle'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['pricing_mode_labels'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['updated_at'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    const partySchemes = <?php echo json_encode($partySchemesForForm ?? [], JSON_UNESCAPED_UNICODE); ?>;
    const partySelect = document.getElementById('party_id');
    const schemeSelect = document.getElementById('default_billing_scheme_id');
    const legacyWrap = document.getElementById('legacy_mode_wrap');
    const maintLink = document.getElementById('billing_scheme_maint_link');

    function syncSchemeDropdown() {
        const pid = String(partySelect.value || '');
        const list = partySchemes[pid] || [];
        schemeSelect.innerHTML = '';
        if (!pid) {
            const o = document.createElement('option');
            o.value = '0';
            o.textContent = '请先选择客户';
            schemeSelect.appendChild(o);
            schemeSelect.disabled = true;
            legacyWrap.style.display = 'none';
            return;
        }
        schemeSelect.disabled = false;
        if (list.length === 0) {
            const o = document.createElement('option');
            o.value = '0';
            o.textContent = '该客户尚无计费方案，请先到「计费方式维护」新增';
            schemeSelect.appendChild(o);
            legacyWrap.style.display = 'block';
        } else {
            const o0 = document.createElement('option');
            o0.value = '0';
            o0.textContent = '请选择默认方案';
            schemeSelect.appendChild(o0);
            list.forEach(function (s) {
                const o = document.createElement('option');
                o.value = String(s.id);
                o.textContent = s.scheme_label;
                schemeSelect.appendChild(o);
            });
            legacyWrap.style.display = 'none';
            if (list[0]) {
                schemeSelect.value = String(list[0].id);
            }
        }
        if (maintLink) {
            maintLink.href = '/finance/ar/billing-schemes?party_id=' + encodeURIComponent(pid);
        }
    }
    partySelect.addEventListener('change', syncSchemeDropdown);
    syncSchemeDropdown();
})();
</script>
