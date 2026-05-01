<?php
/** @var array $events */
/** @var string $monthInput */
/** @var array $eventStats */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
$ownerFilter = trim((string)($_GET['owner_filter'] ?? 'all'));
$completionFilter = trim((string)($_GET['completion_filter'] ?? 'all'));
$openEventId = (int)($_GET['open_event_id'] ?? 0);
?>
<div class="card">
    <div class="toolbar" style="justify-content:space-between;">
        <h2 class="page-title" style="margin:0;"><?php echo htmlspecialchars(t('calendar.events.title', '行事历 / 事件列表')); ?></h2>
        <span class="chip"><?php echo htmlspecialchars(t('calendar.events.tip', '可做月度回顾')); ?></span>
    </div>
    <form method="get" class="toolbar">
        <label><?php echo htmlspecialchars(t('calendar.month', '月份')); ?></label>
        <input type="month" name="month" value="<?php echo htmlspecialchars($monthInput); ?>">
        <label><?php echo htmlspecialchars(t('calendar.filter.owner', '归属')); ?></label>
        <select name="owner_filter">
            <option value="all" <?php echo $ownerFilter === 'all' ? 'selected' : ''; ?>>全部</option>
            <option value="created_by_me" <?php echo $ownerFilter === 'created_by_me' ? 'selected' : ''; ?>>我创建的</option>
            <option value="assigned_to_me" <?php echo $ownerFilter === 'assigned_to_me' ? 'selected' : ''; ?>>指派给我的</option>
        </select>
        <label><?php echo htmlspecialchars(t('calendar.filter.completion', '完成状态')); ?></label>
        <select name="completion_filter">
            <option value="all" <?php echo $completionFilter === 'all' ? 'selected' : ''; ?>>全部</option>
            <option value="unfinished" <?php echo $completionFilter === 'unfinished' ? 'selected' : ''; ?>>仅未完成</option>
        </select>
        <label>每页</label>
        <select name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit"><?php echo htmlspecialchars(t('calendar.query', '查询')); ?></button>
    </form>
</div>

<div class="card">
    <div class="stat-grid">
        <div class="stat-item">
            <div class="label"><?php echo htmlspecialchars(t('calendar.stats.total', '当月总事件')); ?></div>
            <div class="value"><?php echo (int)$eventStats['total']; ?></div>
        </div>
        <div class="stat-item">
            <div class="label"><?php echo htmlspecialchars(t('calendar.stats.today', '今日事件')); ?></div>
            <div class="value"><?php echo (int)$eventStats['today']; ?></div>
        </div>
        <div class="stat-item">
            <div class="label"><?php echo htmlspecialchars(t('calendar.stats.mine', '我创建的')); ?></div>
            <div class="value"><?php echo (int)$eventStats['created_by_me']; ?></div>
        </div>
        <div class="stat-item">
            <div class="label"><?php echo htmlspecialchars(t('calendar.stats.with_note', '有备注事件')); ?></div>
            <div class="value"><?php echo (int)$eventStats['with_note']; ?></div>
        </div>
    </div>
</div>
<?php if (($totalPages ?? 1) > 1): ?>
    <div class="card">
        <div class="toolbar" style="justify-content:space-between;">
            <span class="muted">共 <?php echo (int)($total ?? 0); ?> 条，第 <?php echo (int)($page ?? 1); ?> / <?php echo (int)($totalPages ?? 1); ?> 页</span>
            <div class="inline-actions">
                <?php for ($p = 1; $p <= (int)$totalPages; $p++): ?>
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/calendar/events?month=<?php echo urlencode($monthInput); ?>&owner_filter=<?php echo urlencode($ownerFilter); ?>&completion_filter=<?php echo urlencode($completionFilter); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
            <tr>
                <th><?php echo htmlspecialchars(t('calendar.table.date', '日期')); ?></th>
                <th><?php echo htmlspecialchars(t('calendar.table.type', '类型')); ?></th>
                <th><?php echo htmlspecialchars(t('calendar.table.title', '标题')); ?></th>
                <th><?php echo htmlspecialchars(t('calendar.table.note', '备注')); ?></th>
                <th><?php echo htmlspecialchars(t('calendar.table.creator', '创建人')); ?></th>
                <th><?php echo htmlspecialchars(t('calendar.table.assignees', '指派成员')); ?></th>
                <th><?php echo htmlspecialchars(t('calendar.table.progress', '进度')); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($events)): ?>
                <tr><td colspan="7" style="padding:10px;" class="muted"><?php echo htmlspecialchars(t('calendar.table.empty', '本月暂无事件')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php $completed = (int)($event['is_completed'] ?? 0) === 1; ?>
                    <?php $progress = max(0, min(100, (int)($event['progress_percent'] ?? 0))); ?>
                    <?php
                    $startDate = (string)($event['start_date'] ?? '');
                    $endDate = (string)($event['end_date'] ?? $startDate);
                    $eventType = (string)($event['event_type'] ?? 'reminder');
                    $eventTypeLabel = $eventType === 'meeting' ? '会议' : ($eventType === 'todo' ? '待办' : '提醒');
                    ?>
                    <tr>
                        <td>
                            <?php $dateText = ($startDate !== '' && $startDate !== $endDate) ? ($startDate . ' ~ ' . $endDate) : $startDate; ?>
                            <span class="chip" style="background:#fff7d6;color:#7c5d00;"><?php echo htmlspecialchars($dateText); ?></span>
                        </td>
                        <td>
                            <span class="chip" style="background:#eef2ff;color:#1e3a8a;">
                                <?php echo htmlspecialchars((string)($event['event_type_label'] ?? '提醒')); ?>
                            </span>
                        </td>
                        <td style="<?php echo $completed ? 'text-decoration:line-through;color:#6b7280;' : ''; ?>">
                            <button
                                type="button"
                                class="btn event-detail-btn"
                                data-id="<?php echo (int)($event['id'] ?? 0); ?>"
                                data-title="<?php echo htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES); ?>"
                                data-note="<?php echo htmlspecialchars((string)($event['note'] ?? ''), ENT_QUOTES); ?>"
                                data-start-date="<?php echo htmlspecialchars($startDate, ENT_QUOTES); ?>"
                                data-end-date="<?php echo htmlspecialchars($endDate, ENT_QUOTES); ?>"
                                data-type-label="<?php echo htmlspecialchars($eventTypeLabel, ENT_QUOTES); ?>"
                                data-creator="<?php echo htmlspecialchars((string)($event['creator'] ?? ''), ENT_QUOTES); ?>"
                                data-assignees="<?php echo htmlspecialchars((string)($event['assignees'] ?? ''), ENT_QUOTES); ?>"
                                data-progress="<?php echo $progress; ?>"
                                data-completed="<?php echo $completed ? '1' : '0'; ?>"
                                style="background:transparent;color:inherit;padding:0;min-height:auto;border:none;border-radius:0;font-weight:700;text-align:left;justify-content:flex-start;<?php echo $completed ? 'text-decoration:line-through;' : ''; ?>"
                            >
                                <?php echo htmlspecialchars((string)$event['title']); ?>
                            </button>
                        </td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($event['note'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($event['creator'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($event['assignees'] ?? '')); ?></td>
                        <td style="min-width:140px;">
                            <div style="height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                                <div style="height:100%;width:<?php echo $progress; ?>%;background:<?php echo $completed ? '#16a34a' : '#2563eb'; ?>;"></div>
                            </div>
                            <div class="muted" style="margin-top:4px;"><?php echo $progress; ?>%</div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="eventDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div class="modal-inner" style="position:relative;max-width:620px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="closeEventDetailModalX" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 10px 0;">事件详情</h3>
        <div style="display:grid;grid-template-columns:110px 1fr;row-gap:8px;">
            <div class="muted">标题</div><div id="detailTitle"></div>
            <div class="muted">类型</div><div id="detailType"></div>
            <div class="muted">日期</div><div id="detailDateRange"></div>
            <div class="muted">创建人</div><div id="detailCreator"></div>
            <div class="muted">指派成员</div><div id="detailAssignees"></div>
            <div class="muted">备注</div><div id="detailNote" style="white-space:pre-wrap;"></div>
        </div>
        <form method="post" action="/calendar/event-status" style="margin-top:12px;border-top:1px solid #e5e7eb;padding-top:12px;">
            <input type="hidden" name="event_id" id="detailEventId">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>">
            <div style="display:grid;grid-template-columns:110px 1fr;align-items:center;row-gap:8px;">
                <label for="detailProgress" class="muted">进度 (%)</label>
                <input id="detailProgress" type="number" name="progress_percent" min="0" max="100" step="1" required>
                <label for="detailCompleted" class="muted">完成状态</label>
                <label style="display:flex;align-items:center;gap:6px;font-weight:500;">
                    <input id="detailCompleted" type="checkbox" name="is_completed" value="1">
                    <span>标记为已完成</span>
                </label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button type="button" class="btn" id="closeEventDetailModal" style="background:#64748b;">关闭</button>
                <button type="submit" class="btn">保存状态</button>
            </div>
        </form>
        <div style="margin-top:12px;border-top:1px solid #e5e7eb;padding-top:12px;">
            <div style="font-weight:700;margin-bottom:6px;">状态变更记录</div>
            <div id="detailStatusLogs" class="muted">加载中...</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('eventDetailModal');
    const closeBtn = document.getElementById('closeEventDetailModal');
    const eventButtons = document.querySelectorAll('.event-detail-btn');
    const logsBox = document.getElementById('detailStatusLogs');
    const setText = function (id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '';
    };
    const renderLogs = function (logs) {
        if (!logsBox) return;
        if (!logs || logs.length === 0) {
            logsBox.innerHTML = '<div class="muted">暂无状态变更记录</div>';
            return;
        }
        const html = logs.map(function (log) {
            const who = log.changed_by_name || '-';
            const when = log.created_at || '';
            const oldDone = Number(log.old_is_completed) === 1 ? '已完成' : '未完成';
            const newDone = Number(log.new_is_completed) === 1 ? '已完成' : '未完成';
            return '<div style="padding:6px 0;border-bottom:1px dashed #e5e7eb;">'
                + '<div style="font-size:12px;color:#6b7280;">用户：' + who + '</div>'
                + '<div style="font-size:12px;color:#6b7280;">时间：' + when + '</div>'
                + '<div style="font-size:13px;">进度 ' + Number(log.old_progress_percent) + '% → ' + Number(log.new_progress_percent) + '%，状态 ' + oldDone + ' → ' + newDone + '</div>'
                + '</div>';
        }).join('');
        logsBox.innerHTML = html;
    };
    const loadLogs = function (eventId) {
        if (!logsBox) return;
        logsBox.innerHTML = '<div class="muted">加载中...</div>';
        fetch('/calendar/event-status-logs?event_id=' + encodeURIComponent(eventId), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        }).then(function (resp) {
            return resp.json();
        }).then(function (data) {
            if (!data || data.ok !== true) {
                logsBox.innerHTML = '<div class="muted">加载失败</div>';
                return;
            }
            if (data.missing_log_table) {
                logsBox.innerHTML = '<div class="muted">' + (data.message || '未启用状态日志表') + '</div>';
                return;
            }
            renderLogs(data.logs || []);
        }).catch(function () {
            logsBox.innerHTML = '<div class="muted">加载失败</div>';
        });
    };

    eventButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const startDate = btn.dataset.startDate || '';
            const endDate = btn.dataset.endDate || startDate;
            const dateText = (startDate && endDate && startDate !== endDate) ? (startDate + ' ~ ' + endDate) : startDate;
            setText('detailTitle', btn.dataset.title || '');
            setText('detailType', btn.dataset.typeLabel || '');
            setText('detailDateRange', dateText);
            setText('detailCreator', btn.dataset.creator || '');
            setText('detailAssignees', btn.dataset.assignees || '');
            setText('detailNote', btn.dataset.note || '');
            document.getElementById('detailEventId').value = btn.dataset.id || '';
            document.getElementById('detailProgress').value = btn.dataset.progress || '0';
            document.getElementById('detailCompleted').checked = (btn.dataset.completed === '1');
            loadLogs(btn.dataset.id || '');
            modal.style.display = 'flex';
        });
    });

    closeBtn.addEventListener('click', function () {
        modal.style.display = 'none';
    });
    const closeBtnX = document.getElementById('closeEventDetailModalX');
    if (closeBtnX) {
        closeBtnX.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    const openEventId = <?php echo $openEventId > 0 ? $openEventId : 0; ?>;
    if (openEventId > 0) {
        const targetBtn = document.querySelector('.event-detail-btn[data-id="' + String(openEventId) + '"]');
        if (targetBtn) {
            targetBtn.click();
        }
    }
});
</script>
