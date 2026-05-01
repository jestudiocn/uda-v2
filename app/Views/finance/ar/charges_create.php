<?php
/** @var array $parties */
/** @var array<int, list<array<string, mixed>>> $partyBillingSelectMap */
/** @var array<int, string> $defaultBillingKeyByParty */
/** @var array $pricingModeCatalogue */
/** @var array<int, array{id:int,name:string,sort_order:int}> $categoryOpts */
/** @var array<int, array{id:int,name:string,sort_order:int}> $unitOpts */
/** @var array $formData */
/** @var string $message */
/** @var string $error */
?>
<div class="card">
    <h2>财务管理 / 应收账单 / 新增费用记录</h2>
    <div class="muted">金额以泰铢计。若客户已维护「计费方式」方案，请选择方案后系统将带出单位与单价；首续重（KG）时「数量」请填计费重量。</div>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card">
    <form method="post" class="form-grid" id="arChargeForm">
        <label for="party_id">客户</label>
        <select id="party_id" name="party_id" required>
            <option value="">请选择客户</option>
            <?php foreach ($parties as $party): ?>
                <?php $pid = (int)$party['id']; ?>
                <option value="<?php echo $pid; ?>" <?php echo ((int)($formData['party_id'] ?? 0) === $pid) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$party['party_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="billing_scheme_key">计费方式</label>
        <select id="billing_scheme_key" name="billing_scheme_key" required>
            <option value="">请先选择客户</option>
        </select>
        <label for="billing_date">费用日期</label>
        <input id="billing_date" type="date" name="billing_date" value="<?php echo htmlspecialchars((string)($formData['billing_date'] ?? '')); ?>" required>
        <label for="category_name">费用类目</label>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
            <select id="category_name" name="category_name" required style="flex:1;min-width:160px;">
                <option value="">请选择类目</option>
                <?php foreach ($categoryOpts as $co): ?>
                    <?php $cn = (string)$co['name']; ?>
                    <option value="<?php echo htmlspecialchars($cn); ?>" <?php echo ((string)($formData['category_name'] ?? '') === $cn) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cn); ?></option>
                <?php endforeach; ?>
            </select>
            <a href="/finance/ar/charges/options" target="_blank" rel="noopener" class="muted">维护类目与单位</a>
        </div>
        <label for="project_name">项目</label>
        <input id="project_name" type="text" name="project_name" maxlength="200" value="<?php echo htmlspecialchars((string)($formData['project_name'] ?? '')); ?>" placeholder="选填，例如项目名称或编号">
        <label for="unit_price">单价（THB）</label>
        <input id="unit_price" type="number" step="0.01" name="unit_price" value="<?php echo htmlspecialchars((string)($formData['unit_price'] ?? '')); ?>" required>
        <label for="quantity" id="quantity_label">数量</label>
        <input id="quantity" type="number" step="0.0001" name="quantity" value="<?php echo htmlspecialchars((string)($formData['quantity'] ?? '')); ?>" required>
        <label for="unit_name">计费单位</label>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
            <select id="unit_name" name="unit_name" required style="flex:1;min-width:160px;">
                <option value="">请选择单位</option>
                <?php foreach ($unitOpts as $uo): ?>
                    <?php $un = (string)$uo['name']; ?>
                    <option value="<?php echo htmlspecialchars($un); ?>" <?php echo ((string)($formData['unit_name'] ?? '') === $un) ? 'selected' : ''; ?>><?php echo htmlspecialchars($un); ?></option>
                <?php endforeach; ?>
            </select>
            <a href="/finance/ar/charges/options" target="_blank" rel="noopener" class="muted">维护类目与单位</a>
        </div>
        <label for="base_fee" id="base_fee_label">基础费用（THB）</label>
        <input id="base_fee" type="number" step="0.01" name="base_fee" value="<?php echo htmlspecialchars((string)($formData['base_fee'] ?? '0')); ?>">
        <div class="form-full muted" id="base_fee_hint" style="display:none;">用于「固定费用 + 按量」形态：金额 = 基础费用 + 单价×数量</div>
        <label for="source_ref">来源单号</label>
        <input id="source_ref" type="text" name="source_ref" value="<?php echo htmlspecialchars((string)($formData['source_ref'] ?? '')); ?>">
        <label for="remark">备注</label>
        <input id="remark" type="text" name="remark" value="<?php echo htmlspecialchars((string)($formData['remark'] ?? '')); ?>">
        <div class="form-full">
            <button type="submit" name="create_ar_charge" value="1">保存费用记录</button>
            <a class="btn" style="background:#64748b;margin-left:8px;" href="/finance/ar/charges/list">查看费用记录</a>
        </div>
    </form>
</div>
<script>
(function () {
    const partyBilling = <?php echo json_encode($partyBillingSelectMap ?? [], JSON_UNESCAPED_UNICODE); ?>;
    const defaultKeys = <?php echo json_encode($defaultBillingKeyByParty ?? [], JSON_UNESCAPED_UNICODE); ?>;
    const partySelect = document.getElementById('party_id');
    const modeSelect = document.getElementById('billing_scheme_key');
    const baseFeeInput = document.getElementById('base_fee');
    const baseFeeHint = document.getElementById('base_fee_hint');
    const baseFeeLabel = document.getElementById('base_fee_label');
    const unitPriceInput = document.getElementById('unit_price');
    const unitSelect = document.getElementById('unit_name');
    const qtyInput = document.getElementById('quantity');
    const qtyLabel = document.getElementById('quantity_label');

    function ensureUnitOption(name) {
        if (!name) return;
        let found = false;
        for (let i = 0; i < unitSelect.options.length; i++) {
            if (unitSelect.options[i].value === name) {
                found = true;
                break;
            }
        }
        if (!found) {
            const o = document.createElement('option');
            o.value = name;
            o.textContent = name;
            unitSelect.appendChild(o);
        }
        unitSelect.value = name;
    }

    function applySchemeFields(opt) {
        const sch = opt && opt.scheme;
        if (!sch) {
            unitPriceInput.readOnly = false;
            baseFeeInput.readOnly = false;
            qtyLabel.textContent = '数量';
            return;
        }
        unitPriceInput.readOnly = true;
        baseFeeInput.readOnly = (sch.algorithm === 'base_plus_line');
        ensureUnitOption(sch.unit_name || '');
        unitPriceInput.value = String(sch.unit_price != null ? sch.unit_price : '');
        if (sch.algorithm === 'base_plus_line') {
            baseFeeInput.value = String(sch.base_fee != null ? sch.base_fee : '0');
        } else {
            baseFeeInput.value = '0';
        }
        if (sch.algorithm === 'weight_first_continue') {
            qtyLabel.textContent = '计费重量（KG）';
        } else {
            qtyLabel.textContent = '数量';
        }
    }

    function syncModeOptions() {
        const pid = String(partySelect.value || '');
        const opts = partyBilling[pid] || [];
        modeSelect.innerHTML = '';
        if (opts.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '该客户未建档，请先到「客户计费档案」或「计费方式维护」配置';
            modeSelect.appendChild(opt);
            modeSelect.disabled = true;
            toggleBaseFeeLegacy('');
            applySchemeFields(null);
            return;
        }
        modeSelect.disabled = false;
        opts.forEach(function (m) {
            const o = document.createElement('option');
            o.value = m.key;
            o.textContent = m.label;
            o.dataset.scheme = m.scheme ? JSON.stringify(m.scheme) : '';
            modeSelect.appendChild(o);
        });
        const wanted = <?php echo json_encode((string)($formData['billing_scheme_key'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
        const defK = defaultKeys[pid] || '';
        const pick = wanted && opts.some(function (x) { return x.key === wanted; }) ? wanted : (defK && opts.some(function (x) { return x.key === defK; }) ? defK : (opts[0] ? opts[0].key : ''));
        if (pick) {
            modeSelect.value = pick;
        }
        onModeChange();
    }

    function toggleBaseFeeLegacy(modeKey) {
        const show = modeKey === 'L:base_plus_line';
        baseFeeInput.style.display = show ? '' : 'none';
        baseFeeLabel.style.display = show ? '' : 'none';
        baseFeeHint.style.display = show ? '' : 'none';
        if (!show) {
            baseFeeInput.value = '0';
        }
    }

    function onModeChange() {
        const v = modeSelect.value || '';
        const opt = [...modeSelect.options].find(function (o) { return o.value === v; });
        let sch = null;
        if (opt && opt.dataset.scheme) {
            try {
                sch = JSON.parse(opt.dataset.scheme);
            } catch (e) {
                sch = null;
            }
        }
        if (v.indexOf('S:') === 0) {
            applySchemeFields({ scheme: sch });
            toggleBaseFeeLegacy('');
            if (sch && sch.algorithm === 'base_plus_line') {
                baseFeeInput.style.display = '';
                baseFeeLabel.style.display = '';
                baseFeeHint.style.display = '';
            } else {
                baseFeeInput.style.display = 'none';
                baseFeeLabel.style.display = 'none';
                baseFeeHint.style.display = 'none';
            }
        } else {
            applySchemeFields(null);
            toggleBaseFeeLegacy(v);
        }
    }

    partySelect.addEventListener('change', syncModeOptions);
    modeSelect.addEventListener('change', onModeChange);
    syncModeOptions();
})();
</script>
