<?php
/** @var string $message */
/** @var string $error */
/** @var array $assignableUsers */
/** @var array $formData */
?>
<div class="card">
    <h2 class="page-title"><?php echo htmlspecialchars(t('calendar.create.title', '日程管理 / 新增事件')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('calendar.create.subtitle', '在这里维护事件；控制台只展示月历，不做新增。')); ?></div>
</div>

<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid ud-calendar-form-grid" style="max-width:1080px;grid-template-columns:repeat(4,minmax(170px,1fr));gap:12px;">
        <input type="hidden" name="create_calendar_event" value="1">
        <div>
            <label><?php echo htmlspecialchars(t('calendar.start_date', '开始日期')); ?></label><br>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars((string)($formData['start_date'] ?? '')); ?>" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('calendar.end_date', '结束日期')); ?></label><br>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars((string)($formData['end_date'] ?? '')); ?>" required>
        </div>
        <div style="grid-column:3 / span 2;">
            <label><?php echo htmlspecialchars(t('calendar.title', '事件标题')); ?></label><br>
            <input type="text" name="title" maxlength="160" value="<?php echo htmlspecialchars((string)($formData['title'] ?? '')); ?>" required>
        </div>
        <div style="grid-column:1 / span 2;">
            <label><?php echo htmlspecialchars(t('calendar.type', '事项类型')); ?></label><br>
            <select name="event_type" required>
                <?php $selectedType = (string)($formData['event_type'] ?? 'reminder'); ?>
                <option value="reminder" <?php echo $selectedType === 'reminder' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('calendar.type.reminder', '提醒')); ?></option>
                <option value="todo" <?php echo $selectedType === 'todo' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('calendar.type.todo', '待办')); ?></option>
                <option value="meeting" <?php echo $selectedType === 'meeting' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('calendar.type.meeting', '会议')); ?></option>
            </select>
        </div>
        <div class="form-full">
            <label><?php echo htmlspecialchars(t('calendar.assignees', '指派成员（可多选）')); ?></label><br>
            <?php $selectedAssignees = array_map('intval', (array)($formData['assignee_ids'] ?? [])); ?>
            <?php if (empty($assignableUsers)): ?>
                <div class="muted"><?php echo htmlspecialchars(t('calendar.assignees.empty', '暂无可指派成员')); ?></div>
            <?php else: ?>
                <?php
                $selectedNames = [];
                foreach ($assignableUsers as $user) {
                    $uid = (int)$user['id'];
                    if (in_array($uid, $selectedAssignees, true)) {
                        $selectedNames[] = trim((string)($user['full_name'] ?? '')) !== '' ? (string)$user['full_name'] : (string)$user['username'];
                    }
                }
                $summaryText = empty($selectedNames)
                    ? t('calendar.assignees.placeholder', '请选择成员（可多选）')
                    : implode('、', $selectedNames);
                ?>
                <details style="max-width:720px;position:relative;">
                    <summary style="list-style:none;cursor:pointer;border:1px solid #d1d5db;border-radius:10px;padding:8px 10px;background:#fff;min-height:36px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($summaryText); ?></span>
                        <span class="muted">▼</span>
                    </summary>
                    <div style="position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:20;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 8px 22px rgba(15,23,42,.12);max-height:220px;overflow:auto;padding:8px;">
                        <?php foreach ($assignableUsers as $user): ?>
                            <?php
                            $uid = (int)$user['id'];
                            $name = trim((string)($user['full_name'] ?? '')) !== '' ? (string)$user['full_name'] : (string)$user['username'];
                            ?>
                            <label style="display:flex;align-items:center;justify-content:flex-start;gap:8px;padding:5px 2px;font-weight:500;">
                                <input type="checkbox" name="assignee_ids[]" value="<?php echo $uid; ?>" <?php echo in_array($uid, $selectedAssignees, true) ? 'checked' : ''; ?>>
                                <span style="text-align:left;"><?php echo htmlspecialchars($name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <div class="muted" style="margin-top:6px;"><?php echo htmlspecialchars(t('calendar.assignees.hint', '点击下拉框勾选成员；提醒可不指派，待办和会议至少指派一人。')); ?></div>
            <?php endif; ?>
        </div>
        <div class="form-full">
            <label><?php echo htmlspecialchars(t('calendar.note_optional', '备注（选填）')); ?></label><br>
            <textarea name="note" rows="4" maxlength="500" style="width:100%;"><?php echo htmlspecialchars((string)($formData['note'] ?? '')); ?></textarea>
        </div>
        <div class="form-full">
            <button type="submit"><?php echo htmlspecialchars(t('calendar.create.submit', '新增事件')); ?></button>
        </div>
    </form>
</div>
