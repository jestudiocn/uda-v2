<?php
/** @var string $startDate */
/** @var string $endDate */
/** @var array $summary */
/** @var array $trendLabels */
/** @var array $trendIncome */
/** @var array $trendExpense */
/** @var array $expenseCategories */
/** @var array $pipeline */
/** @var array $monthlyCategoryAnalysis */
?>
<div class="card">
    <div class="toolbar" style="justify-content:space-between;">
        <h2 class="page-title">财务管理 / 报表总览</h2>
        <div class="inline-actions">
            <a class="btn" style="background:#64748b;" href="/finance/reports/detail?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">查看明细</a>
            <a class="btn" href="/finance/reports/export?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">导出CSV</a>
        </div>
    </div>
    <form method="get" class="toolbar">
        <label for="start_date">开始日期</label>
        <input id="start_date" type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
        <label for="end_date">结束日期</label>
        <input id="end_date" type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
        <button type="submit">查询</button>
    </form>
</div>

<div class="card">
    <div class="stat-grid">
        <div class="stat-item">
            <div class="label">总收入</div>
            <div class="value" style="color:#166534;"><?php echo number_format((float)($summary['income'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-item">
            <div class="label">总支出</div>
            <div class="value" style="color:#991b1b;"><?php echo number_format((float)($summary['expense'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-item">
            <div class="label">净利润</div>
            <div class="value"><?php echo number_format((float)($summary['profit'] ?? 0), 2); ?></div>
        </div>
        <div class="stat-item">
            <div class="label">期间</div>
            <div class="value" style="font-size:16px;"><?php echo htmlspecialchars($startDate . ' ~ ' . $endDate); ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h3>每月收支类目分析</h3>
    <div class="muted" style="margin-bottom:10px;">按当前查询区间展示每个月收入与支出的类目构成（金额/占比）。</div>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
            <tr>
                <th>月份</th>
                <th>收入类目分析</th>
                <th>支出类目分析</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($monthlyCategoryAnalysis ?? [])): ?>
                <tr><td colspan="3" class="muted">当前区间暂无可分析数据</td></tr>
            <?php else: ?>
                <?php foreach (($monthlyCategoryAnalysis ?? []) as $ym => $m): ?>
                    <?php
                    $incomeRows = (array)($m['income'] ?? []);
                    $expenseRows = (array)($m['expense'] ?? []);
                    $incomeTotal = 0.0;
                    $expenseTotal = 0.0;
                    foreach ($incomeRows as $r) { $incomeTotal += (float)($r['value'] ?? 0); }
                    foreach ($expenseRows as $r) { $expenseTotal += (float)($r['value'] ?? 0); }
                    if ($incomeTotal <= 0) { $incomeTotal = 1; }
                    if ($expenseTotal <= 0) { $expenseTotal = 1; }
                    ?>
                    <tr>
                        <td><span class="chip"><?php echo htmlspecialchars((string)$ym); ?></span></td>
                        <td>
                            <?php if (empty($incomeRows)): ?>
                                <span class="muted">无收入记录</span>
                            <?php else: ?>
                                <?php foreach ($incomeRows as $r): ?>
                                    <?php
                                    $val = (float)($r['value'] ?? 0);
                                    $pct = (int)round(($val / $incomeTotal) * 100);
                                    ?>
                                    <div style="margin-bottom:6px;">
                                        <div style="display:flex;justify-content:space-between;">
                                            <span style="font-size:12px;"><?php echo htmlspecialchars((string)($r['label'] ?? '未分类')); ?></span>
                                            <span class="muted"><?php echo number_format($val, 0); ?> (<?php echo $pct; ?>%)</span>
                                        </div>
                                        <div style="height:7px;background:#dcfce7;border-radius:999px;">
                                            <div style="height:100%;width:<?php echo max(2, $pct); ?>%;background:#16a34a;border-radius:999px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($expenseRows)): ?>
                                <span class="muted">无支出记录</span>
                            <?php else: ?>
                                <?php foreach ($expenseRows as $r): ?>
                                    <?php
                                    $val = (float)($r['value'] ?? 0);
                                    $pct = (int)round(($val / $expenseTotal) * 100);
                                    ?>
                                    <div style="margin-bottom:6px;">
                                        <div style="display:flex;justify-content:space-between;">
                                            <span style="font-size:12px;"><?php echo htmlspecialchars((string)($r['label'] ?? '未分类')); ?></span>
                                            <span class="muted"><?php echo number_format($val, 0); ?> (<?php echo $pct; ?>%)</span>
                                        </div>
                                        <div style="height:7px;background:#fee2e2;border-radius:999px;">
                                            <div style="height:100%;width:<?php echo max(2, $pct); ?>%;background:#dc2626;border-radius:999px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>财务图表看板</h3>
    <div class="muted" style="margin-bottom:10px;">
        建议优先关注：收支趋势、支出结构、应付应收逾期风险。
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(240px,1fr));gap:12px;">
        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
            <div style="font-weight:700;margin-bottom:8px;">最近6个月收支趋势</div>
            <?php
            $trendMax = 0.0;
            foreach (($trendIncome ?? []) as $v) { $trendMax = max($trendMax, (float)$v); }
            foreach (($trendExpense ?? []) as $v) { $trendMax = max($trendMax, (float)$v); }
            if ($trendMax <= 0) { $trendMax = 1; }
            ?>
            <?php foreach (($trendLabels ?? []) as $idx => $label): ?>
                <?php
                $in = (float)($trendIncome[$idx] ?? 0);
                $out = (float)($trendExpense[$idx] ?? 0);
                $inW = max(2, (int)round(($in / $trendMax) * 100));
                $outW = max(2, (int)round(($out / $trendMax) * 100));
                ?>
                <div style="margin-bottom:8px;">
                    <div class="muted" style="margin-bottom:3px;"><?php echo htmlspecialchars((string)$label); ?></div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="width:34px;font-size:11px;color:#166534;">收入</span>
                        <div style="height:8px;background:#dcfce7;border-radius:999px;flex:1;">
                            <div style="height:100%;width:<?php echo $inW; ?>%;background:#16a34a;border-radius:999px;"></div>
                        </div>
                        <span style="font-size:11px;"><?php echo number_format($in, 0); ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
                        <span style="width:34px;font-size:11px;color:#991b1b;">支出</span>
                        <div style="height:8px;background:#fee2e2;border-radius:999px;flex:1;">
                            <div style="height:100%;width:<?php echo $outW; ?>%;background:#dc2626;border-radius:999px;"></div>
                        </div>
                        <span style="font-size:11px;"><?php echo number_format($out, 0); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
            <div style="font-weight:700;margin-bottom:8px;">支出类目前5（本查询区间）</div>
            <?php
            $catTotal = 0.0;
            foreach (($expenseCategories ?? []) as $c) { $catTotal += (float)($c['value'] ?? 0); }
            if ($catTotal <= 0) { $catTotal = 1; }
            ?>
            <?php if (empty($expenseCategories)): ?>
                <div class="muted">暂无支出数据</div>
            <?php else: ?>
                <?php foreach ($expenseCategories as $c): ?>
                    <?php
                    $label = (string)($c['label'] ?? '未分类');
                    $value = (float)($c['value'] ?? 0);
                    $pct = (int)round(($value / $catTotal) * 100);
                    ?>
                    <div style="margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;gap:6px;">
                            <span style="font-size:12px;"><?php echo htmlspecialchars($label); ?></span>
                            <span class="muted"><?php echo number_format($value, 0); ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <div style="height:8px;background:#e5e7eb;border-radius:999px;">
                            <div style="height:100%;width:<?php echo max(2, $pct); ?>%;background:#2563eb;border-radius:999px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
            <div style="font-weight:700;margin-bottom:8px;">应付应收风险看板</div>
            <div style="padding:8px;background:#f8fafc;border-radius:8px;margin-bottom:8px;">
                <div style="font-weight:700;margin-bottom:4px;">待付款</div>
                <div class="muted">待处理：<?php echo (int)($pipeline['payables_pending_count'] ?? 0); ?> 笔 / <?php echo number_format((float)($pipeline['payables_pending_amount'] ?? 0), 2); ?></div>
                <div style="color:#991b1b;font-size:12px;">逾期：<?php echo (int)($pipeline['payables_overdue_count'] ?? 0); ?> 笔 / <?php echo number_format((float)($pipeline['payables_overdue_amount'] ?? 0), 2); ?></div>
            </div>
            <div style="padding:8px;background:#f8fafc;border-radius:8px;">
                <div style="font-weight:700;margin-bottom:4px;">待收款</div>
                <div class="muted">待处理：<?php echo (int)($pipeline['receivables_pending_count'] ?? 0); ?> 笔 / <?php echo number_format((float)($pipeline['receivables_pending_amount'] ?? 0), 2); ?></div>
                <div style="color:#991b1b;font-size:12px;">逾期：<?php echo (int)($pipeline['receivables_overdue_count'] ?? 0); ?> 笔 / <?php echo number_format((float)($pipeline['receivables_overdue_amount'] ?? 0), 2); ?></div>
            </div>
        </div>
    </div>
</div>
