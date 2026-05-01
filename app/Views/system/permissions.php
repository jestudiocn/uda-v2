<?php
/** @var string $scope */
/** @var array $roles */
/** @var array $users */
/** @var int $selectedRoleId */
/** @var int $selectedUserId */
/** @var array $rolePermissionIds */
/** @var array $userPermissionIds */
/** @var array $groupedPermissions */
/** @var array $moduleLabels */
/** @var string $message */
/** @var string $error */
/** @var bool $canPermissionSave */
$roleSaveScope = 'page';
$userSaveScope = (string)$scope;
?>
<div class="card">
    <h2><?php echo htmlspecialchars(t('admin.permissions.heading')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('admin.permissions.intro')); ?></div>
</div>

<?php if (!empty($message)): ?>
<div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.permissions.role_section')); ?></h3>
    <form method="get" action="/system/permissions" style="margin-bottom:12px;">
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
        <label><?php echo htmlspecialchars(t('admin.permissions.role_label')); ?></label>
        <select name="role_id" onchange="this.form.submit()">
            <?php foreach ($roles as $role): ?>
                <option value="<?php echo (int)$role['id']; ?>" <?php echo ((int)$role['id'] === $selectedRoleId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($role['role_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (!$canPermissionSave): ?>
        <div class="muted"><?php echo htmlspecialchars(t('admin.permissions.readonly')); ?></div>
    <?php endif; ?>
    <form method="post" action="<?php echo htmlspecialchars('/system/permissions?scope=' . rawurlencode((string)$roleSaveScope) . '&role_id=' . (int)$selectedRoleId . '&user_id=' . (int)$selectedUserId); ?>">
        <input type="hidden" name="target_type" value="role">
        <input type="hidden" name="target_id" value="<?php echo (int)$selectedRoleId; ?>">
        <?php foreach ($groupedPermissions as $moduleKey => $items): ?>
            <?php $roleGroupClass = 'role-group-' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$moduleKey); ?>
            <div style="margin:14px 0; border:1px solid #e5e7eb; border-radius:10px; padding:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div style="font-weight:700;"><?php echo htmlspecialchars($moduleLabels[$moduleKey] ?? $moduleKey); ?></div>
                    <label class="muted" style="font-size:12px;">
                        <input type="checkbox" class="module-check-all" data-target-class="<?php echo htmlspecialchars($roleGroupClass); ?>" <?php echo $canPermissionSave ? '' : 'disabled'; ?>>
                        <?php echo htmlspecialchars(t('admin.permissions.select_all')); ?>
                    </label>
                </div>
                <div style="display:grid; grid-template-columns:repeat(2,minmax(200px,1fr)); gap:8px;">
                    <?php foreach ($items as $permission): ?>
                        <label>
                            <input type="checkbox" class="<?php echo htmlspecialchars($roleGroupClass); ?>" name="permission_ids[]" value="<?php echo (int)$permission['id']; ?>" <?php echo in_array((int)$permission['id'], $rolePermissionIds, true) ? 'checked' : ''; ?> <?php echo $canPermissionSave ? '' : 'disabled'; ?>>
                            <?php echo htmlspecialchars($permission['permission_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($canPermissionSave): ?>
            <button type="submit"><?php echo htmlspecialchars(t('admin.permissions.submit.role')); ?></button>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.permissions.user_section')); ?></h3>
    <form method="get" action="/system/permissions" style="margin-bottom:12px;">
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
        <input type="hidden" name="role_id" value="<?php echo (int)$selectedRoleId; ?>">
        <label><?php echo htmlspecialchars(t('admin.permissions.user_label')); ?></label>
        <select name="user_id" onchange="this.form.submit()">
            <?php foreach ($users as $user): ?>
                <?php $displayUserName = trim((string)($user['full_name'] ?? '')) !== '' ? (string)$user['full_name'] : (string)$user['username']; ?>
                <option value="<?php echo (int)$user['id']; ?>" <?php echo ((int)$user['id'] === $selectedUserId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($displayUserName . (empty($user['role_name']) ? '' : ' [' . $user['role_name'] . ']')); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" action="<?php echo htmlspecialchars('/system/permissions?scope=' . rawurlencode((string)$userSaveScope) . '&role_id=' . (int)$selectedRoleId . '&user_id=' . (int)$selectedUserId); ?>">
        <input type="hidden" name="target_type" value="user">
        <input type="hidden" name="target_id" value="<?php echo (int)$selectedUserId; ?>">
        <?php foreach ($groupedPermissions as $moduleKey => $items): ?>
            <?php $userGroupClass = 'user-group-' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$moduleKey); ?>
            <div style="margin:14px 0; border:1px solid #e5e7eb; border-radius:10px; padding:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div style="font-weight:700;"><?php echo htmlspecialchars($moduleLabels[$moduleKey] ?? $moduleKey); ?></div>
                    <label class="muted" style="font-size:12px;">
                        <input type="checkbox" class="module-check-all" data-target-class="<?php echo htmlspecialchars($userGroupClass); ?>" <?php echo $canPermissionSave ? '' : 'disabled'; ?>>
                        <?php echo htmlspecialchars(t('admin.permissions.select_all')); ?>
                    </label>
                </div>
                <div style="display:grid; grid-template-columns:repeat(2,minmax(200px,1fr)); gap:8px;">
                    <?php foreach ($items as $permission): ?>
                        <label>
                            <input type="checkbox" class="<?php echo htmlspecialchars($userGroupClass); ?>" name="permission_ids[]" value="<?php echo (int)$permission['id']; ?>" <?php echo in_array((int)$permission['id'], $userPermissionIds, true) ? 'checked' : ''; ?> <?php echo $canPermissionSave ? '' : 'disabled'; ?>>
                            <?php echo htmlspecialchars($permission['permission_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($canPermissionSave): ?>
            <button type="submit"><?php echo htmlspecialchars(t('admin.permissions.submit.user')); ?></button>
        <?php endif; ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.module-check-all').forEach(function (toggle) {
        const targetClass = toggle.getAttribute('data-target-class');
        if (!targetClass) return;

        const targets = Array.from(document.querySelectorAll('input.' + targetClass));
        if (!targets.length) return;

        // 初始化全选框状态
        toggle.checked = targets.every(function (item) { return item.checked; });

        toggle.addEventListener('change', function () {
            targets.forEach(function (item) {
                item.checked = toggle.checked;
            });
        });

        targets.forEach(function (item) {
            item.addEventListener('change', function () {
                toggle.checked = targets.every(function (cb) { return cb.checked; });
            });
        });
    });
});
</script>
