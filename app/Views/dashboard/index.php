<?php
/** @var string $monthInput */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var array $calendarCells */
/** @var string $message */
/** @var string $error */
/** @var bool $calendarTableReady */
/** @var bool $showPendingTodoPopup */
/** @var array $pendingTodoItems */
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 2px 0;">
                <?php echo htmlspecialchars(sprintf(t('dashboard.welcome', '欢迎回来，%s'), (string)($_SESSION['auth_full_name'] ?: $_SESSION['auth_username']))); ?>
            </h2>
            <div class="muted"><?php echo htmlspecialchars(t('dashboard.subtitle', '这里是你今天的关键工作台')); ?></div>
        </div>
        <div style="padding:6px 10px;background:#eef2ff;border-radius:999px;font-size:12px;color:#1e3a8a;font-weight:700;">
            <?php echo htmlspecialchars((string)($_SESSION['auth_role_name'] ?? '')); ?>
        </div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;"><?php echo htmlspecialchars(t('dashboard.month_calendar', '月行事历')); ?></h2>
            <div class="muted"><?php echo htmlspecialchars(sprintf(t('dashboard.current_month', '当前月份：%s'), $monthInput)); ?></div>
        </div>
        <div style="display:flex;gap:8px;">
            <a class="btn" href="/?month=<?php echo urlencode($prevMonth); ?>"><?php echo htmlspecialchars(t('dashboard.prev_month', '上个月')); ?></a>
            <?php
            $monthLabel = $monthInput;
            if (preg_match('/^(\d{4})-(\d{2})$/', (string)$monthInput, $m)) {
                $monthLabel = $m[1] . '年' . (int)$m[2] . '月';
            }
            ?>
            <span style="display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 14px;border-radius:10px;background:#eef2ff;color:#1e3a8a;font-size:14px;font-weight:700;">
                <?php echo htmlspecialchars($monthLabel); ?>
            </span>
            <a class="btn" href="/?month=<?php echo urlencode($nextMonth); ?>"><?php echo htmlspecialchars(t('dashboard.next_month', '下个月')); ?></a>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (!$calendarTableReady): ?>
    <div class="card">
        <div class="muted"><?php echo htmlspecialchars(t('dashboard.table_missing', '行事历资料表未建立，暂时无法新增事件。')); ?></div>
    </div>
<?php endif; ?>

<div class="card">
    <div style="display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:6px;">
        <?php
        $weekLabels = [
            t('dashboard.week.1', '一'),
            t('dashboard.week.2', '二'),
            t('dashboard.week.3', '三'),
            t('dashboard.week.4', '四'),
            t('dashboard.week.5', '五'),
            t('dashboard.week.6', '六'),
            t('dashboard.week.7', '日'),
        ];
        foreach ($weekLabels as $weekLabel):
        ?>
            <div style="font-weight:700;text-align:center;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:5px 0;font-size:12px;"><?php echo $weekLabel; ?></div>
        <?php endforeach; ?>
        <?php foreach ($calendarCells as $cell): ?>
            <?php if ($cell === null): ?>
                <div style="min-height:104px;border:1px solid #e5e7eb;border-radius:8px;background:#fafafa;"></div>
            <?php else: ?>
                <div style="min-height:104px;border:1px solid #e5e7eb;border-radius:8px;padding:6px;background:<?php echo $cell['is_today'] ? '#eef6ff' : '#fff'; ?>;">
                    <div style="font-weight:700;margin-bottom:4px;font-size:12px;display:flex;justify-content:space-between;align-items:center;">
                        <span><?php echo (int)$cell['day']; ?></span>
                        <?php if ($cell['is_today']): ?><span style="display:inline-block;min-width:18px;height:18px;padding:0 3px;border-radius:999px;background:#0f172a;color:#fff;text-align:center;line-height:18px;font-size:10px;"><?php echo htmlspecialchars(t('dashboard.today', '今')); ?></span><?php endif; ?>
                    </div>
                    <?php if (empty($cell['events'])): ?>
                        <div class="muted" style="font-size:11px;">&nbsp;</div>
                    <?php else: ?>
                        <?php foreach ($cell['events'] as $ev): ?>
                            <?php
                                $title = (string)$ev['title'];
                                $bg = '#fff8dc';
                                $bd = '#f7e7a0';
                                $titleLower = strtolower($title);
                                if (strpos($title, '完成') !== false || strpos($titleLower, 'done') !== false) {
                                    $bg = '#dcfce7';
                                    $bd = '#86efac';
                                } elseif (strpos($title, '进行') !== false || strpos($title, '处理中') !== false || strpos($titleLower, 'in progress') !== false) {
                                    $bg = '#dbeafe';
                                    $bd = '#93c5fd';
                                }
                            ?>
                            <?php
                                $isCompleted = (int)($ev['is_completed'] ?? 0) === 1;
                                $progress = max(0, min(100, (int)($ev['progress_percent'] ?? 0)));
                                if ($isCompleted) {
                                    $bg = '#dcfce7';
                                    $bd = '#86efac';
                                }
                                $eventType = (string)($ev['event_type'] ?? 'reminder');
                                $eventTypeLabel = $eventType === 'meeting' ? '会议' : ($eventType === 'todo' ? '待办' : '提醒');
                            ?>
                            <button
                                type="button"
                                class="calendar-event-pill"
                                data-id="<?php echo (int)($ev['id'] ?? 0); ?>"
                                data-title="<?php echo htmlspecialchars((string)($ev['title'] ?? ''), ENT_QUOTES); ?>"
                                data-note="<?php echo htmlspecialchars((string)($ev['note'] ?? ''), ENT_QUOTES); ?>"
                                data-start-date="<?php echo htmlspecialchars((string)($ev['start_date'] ?? $ev['event_date'] ?? ''), ENT_QUOTES); ?>"
                                data-end-date="<?php echo htmlspecialchars((string)($ev['end_date'] ?? $ev['event_date'] ?? ''), ENT_QUOTES); ?>"
                                data-type-label="<?php echo htmlspecialchars($eventTypeLabel, ENT_QUOTES); ?>"
                                data-creator="<?php echo htmlspecialchars((string)($ev['creator'] ?? ''), ENT_QUOTES); ?>"
                                data-assignees="<?php echo htmlspecialchars((string)($ev['assignees'] ?? ''), ENT_QUOTES); ?>"
                                data-progress="<?php echo $progress; ?>"
                                data-completed="<?php echo $isCompleted ? '1' : '0'; ?>"
                                style="width:100%;text-align:left;background:<?php echo $bg; ?>;border-radius:6px;padding:3px 6px;margin-bottom:3px;font-size:11px;border:1px solid <?php echo $bd; ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#111827;<?php echo $isCompleted ? 'text-decoration:line-through;color:#6b7280;' : ''; ?>"
                            >
                                <?php echo htmlspecialchars((string)$ev['title']); ?> (<?php echo $progress; ?>%)
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<div id="pendingTodoPopup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10020;">
    <div style="width:min(720px,92vw);max-height:78vh;overflow:auto;margin:10vh auto;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.22);padding:16px 18px;">
        <h3 style="margin:0 0 8px 0;">待处理事项提醒</h3>
        <div class="muted" style="margin-bottom:10px;">以下为你本人创建或被指派、尚未完成的事项。</div>
        <?php if (empty($pendingTodoItems)): ?>
            <div class="card" style="margin:0;padding:12px;background:#f8fafc;">
                太棒了，目前没有未完成事项。
            </div>
        <?php else: ?>
            <div style="overflow:auto;max-height:46vh;border:1px solid #e5e7eb;border-radius:10px;">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>状态</th>
                        <th>事项标题</th>
                        <th>日期</th>
                        <th>进度</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingTodoItems as $todoItem): ?>
                        <?php
                        $d1 = (string)($todoItem['start_date'] ?? '');
                        $d2 = (string)($todoItem['end_date'] ?? $d1);
                        $dateText = ($d1 !== '' && $d1 !== $d2) ? ($d1 . ' ~ ' . $d2) : $d1;
                        ?>
                        <tr>
                            <td><span class="chip"><?php echo htmlspecialchars((string)$todoItem['status_label']); ?></span></td>
                            <td class="cell-tip"><?php echo html_cell_tip_content((string)$todoItem['title']); ?></td>
                            <td class="cell-tip"><?php echo html_cell_tip_content($dateText); ?></td>
                            <td><?php echo (int)$todoItem['progress_percent']; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button type="button" id="pendingPopupCloseBtn" class="btn" style="background:#64748b;">稍后处理</button>
            <button type="button" id="pendingPopupGotoBtn" class="btn">前往处理事项</button>
        </div>
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
    const eventButtons = document.querySelectorAll('.calendar-event-pill');
    const setText = function (id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '';
    };
    const logsBox = document.getElementById('detailStatusLogs');

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

    const pendingPopup = document.getElementById('pendingTodoPopup');
    const pendingCloseBtn = document.getElementById('pendingPopupCloseBtn');
    const pendingGotoBtn = document.getElementById('pendingPopupGotoBtn');
    const shouldShowPendingPopup = <?php echo (!empty($showPendingTodoPopup)) ? 'true' : 'false'; ?>;
    const pendingMonth = <?php echo json_encode((string)$monthInput, JSON_UNESCAPED_UNICODE); ?>;
    if (shouldShowPendingPopup && pendingPopup) {
        pendingPopup.style.display = 'block';
    }
    if (pendingCloseBtn) {
        pendingCloseBtn.addEventListener('click', function () {
            if (pendingPopup) pendingPopup.style.display = 'none';
        });
    }
    if (pendingGotoBtn) {
        pendingGotoBtn.addEventListener('click', function () {
            if (pendingPopup) pendingPopup.style.display = 'none';
            window.location.href = '/pending-tasks?month=' + encodeURIComponent(pendingMonth);
        });
    }
    if (pendingPopup) {
        pendingPopup.addEventListener('click', function (e) {
            if (e.target === pendingPopup) {
                pendingPopup.style.display = 'none';
            }
        });
    }
});
</script>
