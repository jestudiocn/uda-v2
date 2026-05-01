<?php
/** @var array $logs */
/** @var array $users */
/** @var string $error */
/** @var string $message */
/** @var bool $tableReady */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var int $userId */
/** @var string $keyword */
/** @var string $module */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */

$actionLabel = static function (string $actionKey): string {
    $map = [
        'dispatch.package_ops.arrival_scan.inbound' => '到件扫描后，订单已改为已入库',
        'dispatch.package_ops.arrival_scan.problem' => '到件扫描后，订单已改为问题件',
        'dispatch.package_ops.arrival_scan.blocked' => '到件扫描被拦截（订单已接近完成）',
        'dispatch.package_ops.self_pickup.submit' => '自取录入已提交',
        'dispatch.package_ops.status_fix.submit' => '货件状态修正已提交',
        'dispatch.waybill.customer_code.update' => '已修改订单客户编码，并重置为待入库',
        'finance.payables.settle' => '应付款已完成核销',
        'finance.receivables.settle' => '应收款已完成核销',
        'finance.transactions.create' => '已新增财务流水',
        'finance.ar.billing_scheme.add' => '已新增计费方式',
        'finance.ar.billing_scheme.toggle' => '已切换计费方式启用状态',
        'calendar.events.create' => '已新增行事历事件',
        'calendar.events.update_status' => '已更新行事历进度/完成状态',
        'users.create' => '已新增用户',
        'users.toggle' => '已切换用户启用状态',
        'users.reset_password' => '已重置用户密码',
        'notifications.rules.save' => '已保存通知规则',
        'notifications.inbox.mark_read' => '已标记通知为已读',
    ];
    if (isset($map[$actionKey])) {
        return $map[$actionKey];
    }
    $parts = array_values(array_filter(explode('.', $actionKey), static fn ($x) => $x !== ''));
    if (!$parts) return '系统操作';
    $last = (string)end($parts);
    $verbMap = [
        'create' => '新增',
        'add' => '新增',
        'save' => '保存',
        'update' => '更新',
        'edit' => '修改',
        'toggle' => '切换状态',
        'settle' => '核销',
        'submit' => '提交',
        'import' => '导入',
        'reset_password' => '重置密码',
        'mark_read' => '标记已读',
        'bind' => '绑定',
        'assign' => '分配',
    ];
    $verb = $verbMap[$last] ?? ('执行 ' . $last);
    $module = (string)($parts[0] ?? '系统');
    $moduleMap = ['finance' => '财务', 'dispatch' => '派送', 'calendar' => '行事历', 'notifications' => '通知', 'users' => '用户', 'roles' => '角色'];
    $moduleLabel = $moduleMap[$module] ?? $module;
    return $moduleLabel . '：' . $verb;
};

$detailLabel = static function (string $k): string {
    $map = [
        'party_name' => '对象名称',
        'amount' => '金额',
        'transaction_id' => '流水ID',
        'charge_count' => '计费条数',
        'line_count' => '账单行数',
        'ids' => '通知ID列表',
        'affected' => '影响条数',
        'customer_code' => '客户编码',
        'picked_count' => '成功件数',
        'skipped_count' => '跳过件数',
        'status' => '订单状态',
        'tracking_no' => '原始单号',
        'old_customer_code' => '原客户编码',
        'new_customer_code' => '新客户编码',
        'old_order_status' => '原订单状态',
        'new_order_status' => '新订单状态',
        'updates' => '变更明细',
        'selected_ids' => '勾选单号ID',
        'updated_ids' => '实际更新ID',
        'rule_count' => '规则数量',
        'title' => '标题',
        'event_type' => '类型',
        'start_date' => '开始日期',
        'end_date' => '结束日期',
        'assignee_ids' => '指派人员ID',
        'old_progress_percent' => '原进度',
        'new_progress_percent' => '新进度',
        'old_is_completed' => '原完成状态',
        'new_is_completed' => '新完成状态',
        'party_id' => '对象ID',
        'scheme_label' => '方案名称',
        'label' => '名称',
        'reason' => '原因',
    ];
    return $map[$k] ?? $k;
};

$targetTypeLabel = static function (string $targetType): string {
    $map = [
        'user' => '用户',
        'event' => '日历事件',
        'waybill' => '订单',
        'waybill_batch' => '订单批次',
        'transaction' => '财务流水',
        'payable' => '应付款',
        'receivable' => '应收款',
        'party' => '对象',
        'account' => '账户',
        'transaction_category' => '类目',
        'finance_party' => '对象',
        'notification_rules' => '通知规则',
        'notifications_inbox_batch' => '通知批次',
        'ar_party_billing_schemes' => '计费方案',
        'ar_charge_dropdown_options' => '计费下拉选项',
    ];
    return $map[$targetType] ?? ($targetType !== '' ? $targetType : '-');
};

$detailValueText = static function ($v): string {
    if (is_array($v)) {
        $isSeq = array_keys($v) === range(0, count($v) - 1);
        if ($isSeq) {
            if (count($v) <= 6) {
                return implode(', ', array_map(static fn($x) => is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE), $v));
            }
            return '共 ' . count($v) . ' 项';
        }
        $pairs = [];
        foreach ($v as $k => $vv) {
            $pairs[] = (string)$k . ':' . (is_scalar($vv) ? (string)$vv : '[对象]');
            if (count($pairs) >= 4) break;
        }
        return implode('，', $pairs) . (count($v) > 4 ? ' 等' : '');
    }
    return is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
};

$summaryFromTemplate = static function (string $actionKey, array $detailArr) use ($detailLabel, $detailValueText): string {
    $get = static function (array $arr, string $k, string $def = ''): string {
        if (!array_key_exists($k, $arr)) return $def;
        $v = $arr[$k];
        if (is_scalar($v)) return trim((string)$v);
        return '';
    };
    $asInt = static function (array $arr, string $k, int $def = 0): int {
        if (!array_key_exists($k, $arr)) return $def;
        return (int)$arr[$k];
    };

    if ($actionKey === 'permissions.assign') {
        $scope = $get($detailArr, 'scope', 'action');
        $count = $asInt($detailArr, 'permission_count', 0);
        $scopeLabel = $scope === 'page' ? '页面权限' : '操作权限';
        return '已调整权限分配（' . $scopeLabel . '，共 ' . $count . ' 项）';
    }
    if ($actionKey === 'users.create') {
        $username = $get($detailArr, 'username', '');
        return $username !== '' ? ('已新增用户：' . $username) : '已新增用户';
    }
    if ($actionKey === 'users.reset_password') {
        return '已将用户密码重置为初始密码，并要求下次登录修改';
    }
    if ($actionKey === 'auth.login.success') {
        return '用户登录成功';
    }
    if ($actionKey === 'auth.login.failed') {
        return '用户登录失败';
    }
    if ($actionKey === 'auth.force_change_password') {
        return '用户已修改初始密码';
    }
    if ($actionKey === 'auth.logout') {
        return '用户已退出登录';
    }
    if ($actionKey === 'finance.ar.charges.options.toggle') {
        return '已切换应收计费下拉选项的启用状态';
    }
    if ($actionKey === 'finance.ar.charges.options.add') {
        return '已新增应收计费下拉选项';
    }
    if ($actionKey === 'finance.ar.invoice.create') {
        $lineCount = $asInt($detailArr, 'line_count', 0);
        return $lineCount > 0 ? ('已生成应收账单（' . $lineCount . ' 条计费明细）') : '已生成应收账单';
    }
    if ($actionKey === 'finance.ar.charge.create') {
        $amount = $get($detailArr, 'amount', '');
        return $amount !== '' ? ('已新增应收计费记录（金额：' . $amount . '）') : '已新增应收计费记录';
    }
    if ($actionKey === 'dispatch.package_ops.status_fix.submit') {
        $updated = $asInt($detailArr, 'updated_count', 0);
        return '已提交货件状态修正（成功更新 ' . $updated . ' 条）';
    }
    if ($actionKey === 'dispatch.package_ops.self_pickup.submit') {
        $picked = $asInt($detailArr, 'picked_count', 0);
        $skipped = $asInt($detailArr, 'skipped_count', 0);
        return '已提交自取录入（成功 ' . $picked . ' 条，跳过 ' . $skipped . ' 条）';
    }

    // 未命中专用模板时返回空字符串，交给通用白话逻辑。
    return '';
};
?>
<div class="card">
    <h2><?php echo htmlspecialchars(t('admin.logs.heading')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('admin.logs.intro')); ?></div>
</div>

<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="get" class="toolbar">
        <label>模块</label>
        <select name="module">
            <option value="all" <?php echo $module === 'all' ? 'selected' : ''; ?>>全部</option>
            <option value="calendar" <?php echo $module === 'calendar' ? 'selected' : ''; ?>>行事历</option>
            <option value="system" <?php echo $module === 'system' ? 'selected' : ''; ?>>系统操作</option>
            <option value="auth" <?php echo $module === 'auth' ? 'selected' : ''; ?>>登录认证</option>
        </select>
        <label>开始日期</label>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        <label>结束日期</label>
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
        <label>操作人</label>
        <select name="user_id">
            <option value="0">全部</option>
            <?php foreach ($users as $u): ?>
                <?php $uid = (int)$u['id']; ?>
                <?php $uname = trim((string)($u['full_name'] ?? '')) !== '' ? (string)$u['full_name'] : (string)$u['username']; ?>
                <option value="<?php echo $uid; ?>" <?php echo $userId === $uid ? 'selected' : ''; ?>><?php echo htmlspecialchars($uname); ?></option>
            <?php endforeach; ?>
        </select>
        <label>关键字</label>
        <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="事件标题/备注/人员">
        <label>每页</label>
        <select name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">查询</button>
    </form>
</div>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
            <tr>
                <th>时间</th>
                <th>模块</th>
                <th>事件</th>
                <th>操作人</th>
                <th>变更摘要</th>
                <th>详情</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$tableReady): ?>
                <tr><td colspan="6" class="muted" style="padding:10px;">日志表未建立</td></tr>
            <?php elseif (empty($logs)): ?>
                <tr><td colspan="6" class="muted" style="padding:10px;">暂无日志</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $moduleKey = (string)($log['module_key'] ?? 'system');
                    $oldDone = ((int)($log['old_is_completed'] ?? 0) === 1) ? '已完成' : '未完成';
                    $newDone = ((int)($log['new_is_completed'] ?? 0) === 1) ? '已完成' : '未完成';
                    $changer = trim((string)($log['changed_by_full_name'] ?? '')) !== '' ? (string)$log['changed_by_full_name'] : (string)($log['changed_by_username'] ?? '-');
                    $detailArr = [];
                    if (!empty($log['detail_json'])) {
                        $parsed = json_decode((string)$log['detail_json'], true);
                        if (is_array($parsed)) {
                            $detailArr = $parsed;
                        }
                    }
                    $detailText = '';
                    foreach ($detailArr as $k => $v) {
                        $display = $detailValueText($v);
                        $detailText .= ($detailText === '' ? '' : '；') . $detailLabel((string)$k) . '：' . $display;
                    }
                    $actionKeyRaw = (string)($log['action_key'] ?? 'system.action');
                    $templateSummary = $summaryFromTemplate($actionKeyRaw, $detailArr);
                    if ($moduleKey === 'calendar') {
                        $summary = '进度 ' . (int)($log['old_progress_percent'] ?? 0) . '% → ' . (int)($log['new_progress_percent'] ?? 0) . '%，状态 ' . $oldDone . ' → ' . $newDone;
                    } elseif ($templateSummary !== '') {
                        $summary = $templateSummary . ($detailText !== '' ? ('（' . $detailText . '）') : '');
                    } else {
                        $summary = $actionLabel($actionKeyRaw) . ($detailText !== '' ? ('（' . $detailText . '）') : '');
                    }
                    $moduleLabel = $moduleKey === 'calendar' ? '行事历' : ($moduleKey === 'auth' ? '登录认证' : '系统操作');
                    $eventDisplay = (string)($log['event_title'] ?? '');
                    if ($eventDisplay === '') {
                        $eventDisplay = $targetTypeLabel((string)($log['target_type'] ?? ''));
                    }
                    ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($log['created_at'] ?? '')); ?></td>
                        <td><span class="chip"><?php echo htmlspecialchars($moduleLabel); ?></span></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($eventDisplay); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($changer); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($summary); ?></td>
                        <td>
                            <button
                                type="button"
                                class="btn log-detail-btn"
                                data-module="<?php echo htmlspecialchars($moduleLabel, ENT_QUOTES); ?>"
                                data-time="<?php echo htmlspecialchars((string)($log['created_at'] ?? ''), ENT_QUOTES); ?>"
                                data-event-title="<?php echo htmlspecialchars($eventDisplay, ENT_QUOTES); ?>"
                                data-event-note="<?php echo htmlspecialchars((string)($log['event_note'] ?? ''), ENT_QUOTES); ?>"
                                data-changer="<?php echo htmlspecialchars($changer, ENT_QUOTES); ?>"
                                data-progress-old="<?php echo (int)($log['old_progress_percent'] ?? 0); ?>"
                                data-progress-new="<?php echo (int)($log['new_progress_percent'] ?? 0); ?>"
                                data-done-old="<?php echo htmlspecialchars($oldDone, ENT_QUOTES); ?>"
                                data-done-new="<?php echo htmlspecialchars($newDone, ENT_QUOTES); ?>"
                                data-action="<?php echo htmlspecialchars((string)($log['action_key'] ?? ''), ENT_QUOTES); ?>"
                                data-detail="<?php echo htmlspecialchars($detailText, ENT_QUOTES); ?>"
                                style="padding:4px 10px;min-height:30px;"
                            >查看</button>
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
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/system/logs?module=<?php echo urlencode($module); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&user_id=<?php echo (int)$userId; ?>&keyword=<?php echo urlencode($keyword); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="logDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div class="modal-inner" style="position:relative;max-width:620px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="closeLogDetailModalX" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3 style="margin:0 0 10px 0;">日志详情</h3>
        <div style="display:grid;grid-template-columns:110px 1fr;row-gap:8px;">
            <div class="muted">模块</div><div id="logDetailModule"></div>
            <div class="muted">时间</div><div id="logDetailTime"></div>
            <div class="muted">操作人</div><div id="logDetailChanger"></div>
            <div class="muted">事件标题</div><div id="logDetailTitle"></div>
            <div class="muted">动作</div><div id="logDetailAction"></div>
            <div class="muted">进度变更</div><div id="logDetailProgress">-</div>
            <div class="muted">完成状态</div><div id="logDetailDone">-</div>
            <div class="muted">附加信息</div><div id="logDetailExtra"></div>
            <div class="muted">事件备注</div><div id="logDetailNote" style="white-space:pre-wrap;"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
            <button type="button" class="btn" id="closeLogDetailModal" style="background:#64748b;">关闭</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('logDetailModal');
    const closeBtn = document.getElementById('closeLogDetailModal');
    const setText = function (id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '';
    };
    document.querySelectorAll('.log-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setText('logDetailModule', btn.dataset.module || '');
            setText('logDetailTime', btn.dataset.time || '');
            setText('logDetailChanger', btn.dataset.changer || '');
            setText('logDetailTitle', btn.dataset.eventTitle || '');
            setText('logDetailAction', btn.dataset.action || '');
            setText('logDetailProgress', (btn.dataset.progressOld || '0') + '% → ' + (btn.dataset.progressNew || '0') + '%');
            setText('logDetailDone', (btn.dataset.doneOld || '') + ' → ' + (btn.dataset.doneNew || ''));
            setText('logDetailExtra', btn.dataset.detail || '');
            setText('logDetailNote', btn.dataset.eventNote || '');
            modal.style.display = 'flex';
        });
    });
    closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
    const closeBtnX = document.getElementById('closeLogDetailModalX');
    if (closeBtnX) closeBtnX.addEventListener('click', function () { modal.style.display = 'none'; });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });
});
</script>
