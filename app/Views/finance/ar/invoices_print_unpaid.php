<?php
/** @var string $partyName */
/** @var list<array<string, mixed>> $rows */
/** @var float $sum */
/** @var string $generatedAt */
/** @var int $partyId */
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDA-V2 内部管理系统</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", "Microsoft YaHei", system-ui, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 24px;
            font-size: 14px;
            line-height: 1.45;
        }
        .toolbar {
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .toolbar button, .toolbar a.btn-link {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        .toolbar button {
            background: #2563eb;
            color: #fff;
        }
        .toolbar button:hover { background: #1d4ed8; }
        .toolbar a.btn-link {
            background: #64748b;
            color: #fff;
        }
        .toolbar a.btn-link:hover { background: #475569; }
        .hint { color: #64748b; font-size: 13px; margin: 0; flex: 1 1 220px; }
        h1 {
            font-size: 1.35rem;
            font-weight: 600;
            margin: 0 0 8px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
        }
        .meta { color: #475569; margin-bottom: 20px; }
        .meta strong { color: #0f172a; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }
        th {
            background: #e2e8f0;
            font-weight: 600;
            white-space: nowrap;
        }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tfoot td {
            font-weight: 600;
            background: #e2e8f0;
            border-top: 2px solid #64748b;
        }
        .empty { text-align: center; color: #64748b; padding: 28px !important; }
        @media print {
            body { padding: 12px; }
            .no-print { display: none !important; }
            .toolbar { display: none !important; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">打印 / 另存为 PDF</button>
        <a class="btn-link" href="#" id="arPrintBackLink">关闭</a>
        <p class="hint">在打印对话框中将目标打印机选为「另存为 PDF」或「Microsoft Print to PDF」即可保存为 PDF。打印对话框关闭后，外层弹窗会自动关闭。</p>
    </div>

    <h1>未收款费用明细</h1>
    <div class="meta">
        <div><strong>客户名称：</strong><?php echo htmlspecialchars($partyName !== '' ? $partyName : '—'); ?></div>
        <div><strong>导出时间：</strong><?php echo htmlspecialchars($generatedAt); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>账单号</th>
                <th>费用日</th>
                <th>类目</th>
                <th>项目</th>
                <th class="num">数量</th>
                <th>单位</th>
                <th class="num">单价（THB）</th>
                <th class="num">小计（THB）</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="empty">暂无未收款明细</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($row['invoice_no'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['billing_date'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['category_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['project_name'] ?? '')); ?></td>
                    <td class="num"><?php echo htmlspecialchars(number_format((float)($row['quantity'] ?? 0), 4, '.', '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['unit_name'] ?? '')); ?></td>
                    <td class="num"><?php echo htmlspecialchars(number_format((float)($row['unit_price'] ?? 0), 2, '.', '')); ?></td>
                    <td class="num"><?php echo htmlspecialchars(number_format((float)($row['line_amount'] ?? 0), 2, '.', '')); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <?php if (!empty($rows)): ?>
        <tfoot>
            <tr>
                <td colspan="7" class="num" style="text-align:right;">总价</td>
                <td class="num"><?php echo htmlspecialchars(number_format($sum, 2, '.', '')); ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <script>
    (function () {
        var inFrame = false;
        try {
            inFrame = window.self !== window.top;
        } catch (e) {
            inFrame = true;
        }
        function notifyParentClose() {
            if (inFrame && window.parent) {
                window.parent.postMessage({ type: 'ar-unpaid-print-close' }, window.location.origin);
            }
        }
        window.addEventListener('afterprint', notifyParentClose);
        var back = document.getElementById('arPrintBackLink');
        if (back) {
            back.addEventListener('click', function (e) {
                e.preventDefault();
                if (inFrame) {
                    notifyParentClose();
                } else {
                    window.location.href = '/finance/ar/invoices/list';
                }
            });
        }
    })();
    </script>
</body>
</html>
