<?php
/** @var array $pendingItems */
/** @var array $moduleStats */
/** @var string $selectedModule */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
$moduleLabels = [
    'all' => '全部模块',
    'calendar' => '行事历',
    'payables' => '待付款',
    'receivables' => '待收款',
    'issues' => '问题件',
];
$allCount = (int)($moduleStats['calendar'] ?? 0) + (int)($moduleStats['payables'] ?? 0) + (int)($moduleStats['receivables'] ?? 0) + (int)($moduleStats['issues'] ?? 0);
?>
<div class="card">
    <div class="toolbar" style="justify-content:space-between;">
        <h2 class="page-title" style="margin:0;">待处理事件列表</h2>
        <a class="btn" href="/">返回首页</a>
    </div>
    <div class="muted">此页面为多模块聚合中心，目前已接入行事历、待付款、待收款，问题件模块预留中。</div>
</div>

<div class="card">
    <div class="toolbar" style="margin-bottom:10px;">
        <?php foreach ($moduleLabels as $moduleKey => $moduleLabel): ?>
            <?php
            $cnt = $moduleKey === 'all'
                ? $allCount
                : (int)($moduleStats[$moduleKey] ?? 0);
            $isActive = $selectedModule === $moduleKey;
            ?>
            <a
                class="btn"
                href="/pending-tasks?module=<?php echo urlencode($moduleKey); ?>"
                style="<?php echo $isActive ? '' : 'background:#64748b;'; ?>min-height:auto;padding:6px 10px;"
            >
                <?php echo htmlspecialchars($moduleLabel); ?> (<?php echo $cnt; ?>)
            </a>
        <?php endforeach; ?>
    </div>
    <form method="get" class="toolbar" style="margin-bottom:10px;">
        <input type="hidden" name="module" value="<?php echo htmlspecialchars($selectedModule); ?>">
        <label for="per_page">每页</label>
        <select id="per_page" name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">应用</button>
    </form>
    <?php if ($selectedModule === 'issues'): ?>
        <div class="card" style="margin:0;padding:12px;background:#f8fafc;">
            <div style="font-weight:700;margin-bottom:4px;"><?php echo htmlspecialchars($moduleLabels[$selectedModule] ?? '该模块'); ?></div>
            <div class="muted">该模块待处理清单尚未接入，后续功能上线后会自动显示在这里。</div>
        </div>
    <?php endif; ?>
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
            <tr>
                <th>来源</th>
                <th>状态</th>
                <th>类型</th>
                <th>事项标题</th>
                <th>日期</th>
                <th>创建人</th>
                <th>指派成员</th>
                <th>进度</th>
                <th>处理</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($pendingItems)): ?>
                <tr><td colspan="9" class="muted">暂无待处理事项</td></tr>
            <?php else: ?>
                <?php foreach ($pendingItems as $item): ?>
                    <?php
                    $startDate = (string)($item['start_date'] ?? '');
                    $endDate = (string)($item['end_date'] ?? $startDate);
                    $dateText = ($startDate !== '' && $startDate !== $endDate) ? ($startDate . ' ~ ' . $endDate) : $startDate;
                    $eventId = (int)($item['id'] ?? 0);
                    $month = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ? substr($startDate, 0, 7) : date('Y-m');
                    $moduleKey = (string)($item['module_key'] ?? 'calendar');
                    $actionHref = '/calendar/events?month=' . urlencode($month) . '&completion_filter=unfinished&open_event_id=' . $eventId;
                    $dueLevel = (string)($item['due_level'] ?? 'normal');
                    $statusStyle = '';
                    if ($dueLevel === 'overdue') {
                        $statusStyle = 'background:#fee2e2;color:#991b1b;';
                    } elseif ($dueLevel === 'due_today') {
                        $statusStyle = 'background:#ffedd5;color:#9a3412;';
                    }
                    if ($moduleKey === 'payables') {
                        $actionHref = '/finance/payables/settle?id=' . $eventId;
                    } elseif ($moduleKey === 'receivables') {
                        $actionHref = '/finance/receivables/settle?id=' . $eventId;
                    }
                    ?>
                    <tr>
                        <td><span class="chip"><?php echo htmlspecialchars((string)($item['module_label'] ?? '行事历')); ?></span></td>
                        <td><span class="chip" style="<?php echo $statusStyle; ?>"><?php echo htmlspecialchars((string)($item['status_label'] ?? '待处理')); ?></span></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($item['event_type_label'] ?? '提醒')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($item['title'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($dateText); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($item['creator'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($item['assignees'] ?? '')); ?></td>
                        <td><?php echo (int)($item['progress_percent'] ?? 0); ?>%</td>
                        <td>
                            <a class="btn" style="padding:6px 10px;min-height:auto;" href="<?php echo htmlspecialchars($actionHref); ?>">前往处理</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (($totalPages ?? 1) > 1): ?>
    <div class="card">
        <div class="toolbar" style="justify-content:space-between;">
            <span class="muted">共 <?php echo (int)($total ?? 0); ?> 条，第 <?php echo (int)($page ?? 1); ?> / <?php echo (int)($totalPages ?? 1); ?> 页</span>
            <div class="inline-actions">
                <?php for ($p = 1; $p <= (int)$totalPages; $p++): ?>
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/pending-tasks?module=<?php echo urlencode($selectedModule); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
