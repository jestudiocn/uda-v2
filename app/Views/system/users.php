<?php
/** @var array $users */
/** @var array $roles */
/** @var string $message */
/** @var string $error */
/** @var bool $canUserCreate */
/** @var bool $canUserManage */
/** @var bool $canUserToggle */
/** @var bool $canUserReset */
/** @var bool $hasUserDispatchBindCol */
/** @var array<int, array<string, mixed>> $dispatchClientsForBind */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2><?php echo htmlspecialchars(t('admin.users.heading')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('admin.users.intro')); ?></div>
</div>

<?php if (!empty($message)): ?>
<div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.users.create.heading')); ?></h3>
    <?php if (!$canUserCreate): ?>
        <div class="muted"><?php echo htmlspecialchars(t('admin.users.create.no_permission')); ?></div>
    <?php elseif (empty($roles)): ?>
        <div class="muted"><?php echo htmlspecialchars(t('admin.users.create.need_roles')); ?></div>
    <?php else: ?>
        <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px;">
            <input type="hidden" name="create_user" value="1">
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.username')); ?></label><br>
                <input type="text" name="username" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.password')); ?></label><br>
                <input type="password" name="password" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.full_name')); ?></label><br>
                <input type="text" name="full_name">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.phone')); ?></label><br>
                <input type="text" name="phone">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.wechat')); ?></label><br>
                <input type="text" name="wechat">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.line_id')); ?></label><br>
                <input type="text" name="line_id">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.users.field.role')); ?></label><br>
                <select name="role_id" required>
                    <option value=""><?php echo htmlspecialchars(t('admin.users.field.role_placeholder')); ?></option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo (int)$role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($hasUserDispatchBindCol)): ?>
            <div style="grid-column:1 / -1;">
                <label>派送数据范围（可选）</label><br>
                <select name="dispatch_consigning_client_id">
                    <option value="0">不绑定（公司内部：可查看全部委托客户订单）</option>
                    <?php foreach ($dispatchClientsForBind as $dcc): ?>
                        <option value="<?php echo (int)$dcc['id']; ?>"><?php echo htmlspecialchars((string)($dcc['client_code'] ?? '') . ' — ' . (string)($dcc['client_name'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="muted" style="margin-top:4px;">若绑定某一委托客户，该用户登录后「派送 / 订单查询」仅显示该客户订单，且不再出现委托客户筛选。<?php if (empty($dispatchClientsForBind)): ?>当前尚无启用中的委托客户，可先留空稍后在列表中绑定。<?php endif; ?></div>
            </div>
            <?php endif; ?>
            <div style="grid-column:1 / -1;" class="muted"><?php echo htmlspecialchars(t('admin.users.hint.first_login')); ?></div>
            <div style="grid-column:1 / -1;">
                <button type="submit"><?php echo htmlspecialchars(t('admin.users.submit.create')); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.users.list.heading')); ?></h3>
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
        <table class="table-valign-middle" style="width:100%;border-collapse:collapse;">
            <thead>
            <tr>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.id')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.username')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.full_name')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.phone')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.role')); ?></th>
                <?php if (!empty($hasUserDispatchBindCol)): ?>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">派送绑定</th>
                <?php endif; ?>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.status')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.must_change')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.created_at')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.users.col.actions')); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="<?php echo !empty($hasUserDispatchBindCol) ? 10 : 9; ?>" style="padding:10px;" class="muted"><?php echo htmlspecialchars(t('admin.users.empty')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo (int)$user['id']; ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($user['username']); ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($user['full_name'] ?? ''); ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($user['phone'] ?? ''); ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($user['role_name'] ?? ''); ?></td>
                        <?php if (!empty($hasUserDispatchBindCol)): ?>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;vertical-align:top;">
                            <?php if (!empty($user['dispatch_consigning_client_id'])): ?>
                                <div class="cell-tip"><?php echo html_cell_tip_content(trim((string)($user['dispatch_client_code'] ?? '') . ' — ' . (string)($user['dispatch_client_name'] ?? ''))); ?></div>
                            <?php else: ?>
                                <span class="muted">未绑定（全量）</span>
                            <?php endif; ?>
                            <?php if (!empty($canUserManage) && !empty($hasUserDispatchBindCol)): ?>
                                <form method="post" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                                    <input type="hidden" name="bind_user_dispatch_cc" value="1">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$user['id']; ?>">
                                    <select name="dispatch_consigning_client_id" style="max-width:220px;">
                                        <option value="0">清除绑定</option>
                                        <?php foreach ($dispatchClientsForBind as $dcc): ?>
                                            <option value="<?php echo (int)$dcc['id']; ?>"<?php echo ((int)($user['dispatch_consigning_client_id'] ?? 0) === (int)$dcc['id']) ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string)($dcc['client_code'] ?? '') . ' — ' . (string)($dcc['client_name'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn" style="min-height:auto;padding:4px 8px;">保存</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo ((int)$user['status'] === 1) ? htmlspecialchars(t('admin.users.status.active')) : htmlspecialchars(t('admin.users.status.disabled')); ?></td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo ((int)$user['must_change_password'] === 1) ? htmlspecialchars(t('admin.users.must_change.yes')) : htmlspecialchars(t('admin.users.must_change.no')); ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($user['created_at'] ?? ''); ?></td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                            <div class="cell-actions">
                            <?php if ($canUserToggle): ?>
                                <form method="post" action="/system/users" style="display:inline;">
                                    <input type="hidden" name="toggle_user" value="1">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$user['id']; ?>">
                                    <button class="btn" type="submit"><?php echo htmlspecialchars(t('admin.users.action.toggle')); ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canUserReset): ?>
                                <form method="post" action="/system/users" style="display:inline;">
                                    <input type="hidden" name="reset_user_password" value="1">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$user['id']; ?>">
                                    <button class="btn" type="submit"><?php echo htmlspecialchars(t('admin.users.action.reset')); ?></button>
                                </form>
                            <?php endif; ?>
                            <button
                                type="button"
                                class="btn user-detail-btn"
                                data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                data-full-name="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES); ?>"
                                data-phone="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES); ?>"
                                data-wechat="<?php echo htmlspecialchars($user['wechat'] ?? '', ENT_QUOTES); ?>"
                                data-line-id="<?php echo htmlspecialchars($user['line_id'] ?? '', ENT_QUOTES); ?>"
                                data-role-name="<?php echo htmlspecialchars($user['role_name'] ?? '', ENT_QUOTES); ?>"
                            ><?php echo htmlspecialchars(t('admin.users.action.detail')); ?></button>
                            </div>
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
                <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/system/users?per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="userDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div class="modal-inner" style="position:relative;max-width:520px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="closeUserDetailModalX" style="position:absolute;top:10px;right:12px;border:none;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">×</button>
        <h3><?php echo htmlspecialchars(t('admin.users.modal.title')); ?></h3>
        <div style="display:grid;grid-template-columns:100px 1fr;row-gap:8px;">
            <div class="muted"><?php echo htmlspecialchars(t('admin.users.modal.username')); ?></div><div id="detailUsername"></div>
            <div class="muted"><?php echo htmlspecialchars(t('admin.users.modal.full_name')); ?></div><div id="detailFullName"></div>
            <div class="muted"><?php echo htmlspecialchars(t('admin.users.modal.phone')); ?></div><div id="detailPhone"></div>
            <div class="muted"><?php echo htmlspecialchars(t('admin.users.modal.wechat')); ?></div><div id="detailWechat"></div>
            <div class="muted"><?php echo htmlspecialchars(t('admin.users.modal.line_id')); ?></div><div id="detailLineId"></div>
            <div class="muted"><?php echo htmlspecialchars(t('admin.users.modal.role')); ?></div><div id="detailRoleName"></div>
        </div>
        <div style="text-align:right;margin-top:14px;">
            <button type="button" class="btn" id="closeUserDetailModal"><?php echo htmlspecialchars(t('admin.users.modal.close')); ?></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('userDetailModal');
    const closeBtn = document.getElementById('closeUserDetailModal');
    const fields = {
        username: document.getElementById('detailUsername'),
        fullName: document.getElementById('detailFullName'),
        phone: document.getElementById('detailPhone'),
        wechat: document.getElementById('detailWechat'),
        lineId: document.getElementById('detailLineId'),
        roleName: document.getElementById('detailRoleName')
    };

    document.querySelectorAll('.user-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fields.username.textContent = btn.dataset.username || '';
            fields.fullName.textContent = btn.dataset.fullName || '';
            fields.phone.textContent = btn.dataset.phone || '';
            fields.wechat.textContent = btn.dataset.wechat || '';
            fields.lineId.textContent = btn.dataset.lineId || '';
            fields.roleName.textContent = btn.dataset.roleName || '';
            modal.style.display = 'flex';
        });
    });

    closeBtn.addEventListener('click', function () {
        modal.style.display = 'none';
    });
    const closeBtnX = document.getElementById('closeUserDetailModalX');
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
});
</script>
