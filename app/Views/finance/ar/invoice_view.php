<?php
/** @var array $invoice */
/** @var array $lines */
/** @var array $pricingModeCatalogue */
?>
<div class="card">
    <div class="toolbar" style="justify-content:space-between;">
        <h2 class="page-title">财务管理 / 应收账单 / 账单详情</h2>
        <a class="btn" style="background:#64748b;" href="/finance/ar/invoices/list">返回账单列表</a>
    </div>
    <div class="muted">账单号：<?php echo htmlspecialchars((string)$invoice['invoice_no']); ?> ｜ 客户：<?php echo htmlspecialchars((string)$invoice['party_name']); ?> ｜ 状态：<?php echo htmlspecialchars((string)$invoice['status']); ?></div>
    <div class="muted">期间：<?php echo htmlspecialchars((string)$invoice['period_start']); ?> ~ <?php echo htmlspecialchars((string)$invoice['period_end']); ?> ｜ 总额（THB）：<?php echo number_format((float)$invoice['total_amount'], 2); ?></div>
</div>
<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead><tr><th>费用日</th><th>类目</th><th>项目</th><th>计费方式</th><th>单价</th><th>数量</th><th>单位</th><th>小计</th><th>备注</th></tr></thead>
            <tbody>
            <?php if (empty($lines)): ?>
                <tr><td colspan="9" class="muted">暂无账单明细</td></tr>
            <?php else: ?>
                <?php foreach ($lines as $line): ?>
                    <?php
                    $detail = json_decode((string)($line['line_detail_json'] ?? '{}'), true);
                    $pmKey = is_array($detail) ? (string)($detail['pricing_mode'] ?? 'line_only') : 'line_only';
                    $pmLabel = (string)(($pricingModeCatalogue ?? [])[$pmKey] ?? $pmKey);
                    $projCell = (string)($line['project_name'] ?? '');
                    if ($projCell === '' && is_array($detail)) {
                        $projCell = (string)($detail['project_name'] ?? '');
                    }
                    $schLab = '';
                    if (is_array($detail) && !empty($detail['billing_scheme_label'])) {
                        $schLab = (string)$detail['billing_scheme_label'];
                    }
                    $modeCell = $schLab !== '' ? $schLab : $pmLabel;
                    ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($line['billing_date'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($line['category_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($projCell); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($modeCell); ?></td>
                        <td><?php echo number_format((float)($line['unit_price'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($line['quantity'] ?? 0), 4); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($line['unit_name'] ?? '')); ?></td>
                        <td><?php echo number_format((float)($line['line_amount'] ?? 0), 2); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($line['remark'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
