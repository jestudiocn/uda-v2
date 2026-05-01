<?php
/** @var array $parties */
/** @var list<array<string, mixed>> $schemeRows */
/** @var array<string, string> $algoCatalogue */
/** @var int $partyId */
/** @var string $message */
/** @var string $error */
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 计费方式维护</h2>
    <p class="muted">按客户维护计费方案：固定单位与单价（或首续重规则）。保存后，在「新增费用记录」选择该方案时会自动带出单位与单价。首续重的「计费重量」目前由手工填写（KG），日后可与实重/泡重模组对接。</p>
    <p><a class="btn" style="background:#64748b;" href="/finance/ar/customers">返回客户计费档案</a></p>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="get" class="toolbar">
        <label>客户</label>
        <select name="party_id" onchange="this.form.submit()">
            <option value="0">请选择客户</option>
            <?php foreach ($parties as $party): ?>
                <?php $pid = (int)$party['id']; ?>
                <option value="<?php echo $pid; ?>" <?php echo $partyId === $pid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$party['party_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($partyId > 0): ?>
<div class="card">
    <h3>新增计费方式</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="party_id" value="<?php echo (int)$partyId; ?>">
        <label for="scheme_label">方案名称</label>
        <input id="scheme_label" type="text" name="scheme_label" maxlength="120" required placeholder="例如：国际件首续重">
        <label for="algorithm">算法</label>
        <select id="algorithm" name="algorithm" required>
            <?php foreach ($algoCatalogue as $k => $lab): ?>
                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lab); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="unit_name">计费单位</label>
        <input id="unit_name" type="text" name="unit_name" maxlength="40" required placeholder="如：票、KG、人天">
        <label for="unit_price" id="unit_price_label">单价（THB）</label>
        <input id="unit_price" type="number" step="0.0001" name="unit_price" value="0" min="0" required>
        <div id="base_fee_block" class="form-full" style="display:none;">
            <label for="base_fee">基础费用（THB）</label>
            <input id="base_fee" type="number" step="0.01" name="base_fee" value="0" min="0">
        </div>
        <div id="weight_block" class="form-full" style="display:none;border:1px solid #e2e8f0;border-radius:8px;padding:12px;background:#f8fafc;">
            <div style="font-weight:600;margin-bottom:8px;">首续重（KG）参数</div>
            <label>计费重依据（供日后模组使用）</label>
            <select name="chargeable_weight_basis">
                <option value="actual">以实重为主</option>
                <option value="volumetric">以泡重为主</option>
                <option value="max_of_both">实重与泡重取较大</option>
            </select>
            <div class="muted" style="margin:8px 0;">首重阶梯（最多 3 组；费用为在对应首重公斤内的一口价，超出部分按续重步长计费）</div>
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <label>首重 <?php echo $i; ?>（公斤）</label>
                <input type="number" step="0.0001" name="first_kg_<?php echo $i; ?>" value="" min="0" placeholder="如 1">
                <label>费用 <?php echo $i; ?>（THB）</label>
                <input type="number" step="0.01" name="first_fee_<?php echo $i; ?>" value="" min="0" placeholder="如 100">
            <?php endfor; ?>
            <label for="continue_step_kg">续重步长（公斤）</label>
            <input id="continue_step_kg" type="number" step="0.0001" name="continue_step_kg" value="0.5" min="0.0001">
            <label for="continue_fee_per_step">每步续重费用（THB）</label>
            <input id="continue_fee_per_step" type="number" step="0.01" name="continue_fee_per_step" value="0" min="0">
            <div class="muted">保存后「单价」字段会记录为续重每步费用，便于列表展示；完整规则存在 JSON 中。</div>
        </div>
        <div class="form-full">
            <button type="submit" name="add_ar_billing_scheme" value="1">保存方案</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>已有方案</h3>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>名称</th><th>算法</th><th>单位</th><th>单价/续重步价</th><th>基础费</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($schemeRows)): ?>
                <tr><td colspan="7" class="muted">暂无方案</td></tr>
            <?php else: ?>
                <?php foreach ($schemeRows as $sr): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($sr['scheme_label'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($algoCatalogue[(string)($sr['algorithm'] ?? '')] ?? ($sr['algorithm'] ?? ''))); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($sr['unit_name'] ?? '')); ?></td>
                        <td><?php echo number_format((float)($sr['unit_price'] ?? 0), 4); ?></td>
                        <td><?php echo number_format((float)($sr['base_fee'] ?? 0), 2); ?></td>
                        <td><?php echo ((int)($sr['status'] ?? 0) === 1) ? '启用' : '停用'; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="party_id" value="<?php echo (int)$partyId; ?>">
                                <input type="hidden" name="scheme_id" value="<?php echo (int)($sr['id'] ?? 0); ?>">
                                <button type="submit" name="toggle_ar_billing_scheme" value="1" class="btn" style="background:#64748b;padding:6px 10px;min-height:auto;">
                                    <?php echo ((int)($sr['status'] ?? 0) === 1) ? '停用' : '启用'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var algo = document.getElementById('algorithm');
    var wblk = document.getElementById('weight_block');
    var bblk = document.getElementById('base_fee_block');
    var upl = document.getElementById('unit_price_label');
    function sync() {
        var v = algo.value;
        wblk.style.display = (v === 'weight_first_continue') ? 'block' : 'none';
        bblk.style.display = (v === 'base_plus_line') ? 'block' : 'none';
        if (v === 'weight_first_continue') {
            upl.textContent = '续重每步费用（THB，与下方「每步续重费用」一致）';
        } else {
            upl.textContent = '单价（THB）';
        }
    }
    algo.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
