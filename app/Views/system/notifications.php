<?php
/** @var array $rules */
/** @var array $users */
/** @var array $recentNotifications */
/** @var bool $rulesReady */
/** @var bool $inboxReady */
/** @var string $message */
/** @var string $error */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2><?php echo htmlspecialchars(t('admin.notifications.heading')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('admin.notifications.intro')); ?></div>
</div>

<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h3>通知规则</h3>
    <?php if (!$rulesReady): ?>
        <div class="muted">通知规则表未建立，请先执行 migration：011_create_notification_tables.sql</div>
    <?php elseif (empty($rules)): ?>
        <div class="muted">暂无通知规则</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="save_notification_rules" value="1">
            <div style="overflow:auto;">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>事件</th>
                        <th>启用</th>
                        <th>接收人</th>
                        <th>自定义接收人</th>
                        <th>更新时间</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <?php
                        $eventKey = (string)$rule['event_key'];
                        $selectedMode = (string)$rule['recipients_mode'];
                        $customIds = array_map('intval', (array)($rule['custom_user_ids_arr'] ?? []));
                        ?>
                        <tr>
                            <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)$rule['rule_name'] . ' · ' . $eventKey)); ?></td>
                            <td>
                                <label style="display:flex;align-items:center;gap:6px;">
                                    <input type="checkbox" name="rules[<?php echo htmlspecialchars($eventKey); ?>][enabled]" value="1" <?php echo ((int)$rule['enabled'] === 1) ? 'checked' : ''; ?>>
                                    <span>启用</span>
                                </label>
                            </td>
                            <td>
                                <select name="rules[<?php echo htmlspecialchars($eventKey); ?>][recipients_mode]">
                                    <option value="creator" <?php echo $selectedMode === 'creator' ? 'selected' : ''; ?>>仅创建人</option>
                                    <option value="assignees" <?php echo $selectedMode === 'assignees' ? 'selected' : ''; ?>>仅指派成员</option>
                                    <option value="creator_and_assignees" <?php echo $selectedMode === 'creator_and_assignees' ? 'selected' : ''; ?>>创建人 + 指派成员</option>
                                    <option value="custom_users" <?php echo $selectedMode === 'custom_users' ? 'selected' : ''; ?>>自定义人员</option>
                                    <option value="all_active_users" <?php echo $selectedMode === 'all_active_users' ? 'selected' : ''; ?>>全体启用用户</option>
                                </select>
                            </td>
                            <td>
                                <select name="rules[<?php echo htmlspecialchars($eventKey); ?>][custom_user_ids][]" multiple size="5" style="min-width:220px;">
                                    <?php foreach ($users as $u): ?>
                                        <?php
                                        $uid = (int)$u['id'];
                                        $uname = trim((string)($u['full_name'] ?? '')) !== '' ? (string)$u['full_name'] : (string)$u['username'];
                                        ?>
                                        <option value="<?php echo $uid; ?>" <?php echo in_array($uid, $customIds, true) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($uname); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="cell-tip"><?php echo html_cell_tip_content((string)($rule['updated_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:10px;">
                <button type="submit">保存通知规则</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>最近站内通知</h3>
    <?php if (!$inboxReady): ?>
        <div class="muted">通知记录表未建立，请先执行 migration：011_create_notification_tables.sql</div>
    <?php elseif (empty($recentNotifications)): ?>
        <div class="muted">暂无通知记录</div>
    <?php else: ?>
        <form method="get" class="toolbar" style="margin-bottom:10px;">
            <label for="per_page">每页</label>
            <select id="per_page" name="per_page">
                <?php foreach ([20, 50, 100] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">应用</button>
        </form>
        <div style="overflow:auto;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>时间</th>
                    <th>接收人</th>
                    <th>标题</th>
                    <th>内容</th>
                    <th>来源</th>
                    <th>创建者</th>
                    <th>状态</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentNotifications as $n): ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($n['created_at'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($n['receiver_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($n['title'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($n['content'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($n['biz_type'] ?? '') . '#' . (int)($n['biz_id'] ?? 0))); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($n['creator_name'] ?? '')); ?></td>
                        <td><?php echo ((int)($n['is_read'] ?? 0) === 1) ? '已读' : '未读'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (($totalPages ?? 1) > 1): ?>
            <div class="toolbar" style="justify-content:space-between;margin-top:10px;">
                <span class="muted">共 <?php echo (int)($total ?? 0); ?> 条，第 <?php echo (int)($page ?? 1); ?> / <?php echo (int)($totalPages ?? 1); ?> 页</span>
                <div class="inline-actions">
                    <?php for ($p = 1; $p <= (int)$totalPages; $p++): ?>
                        <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/system/notifications?per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
