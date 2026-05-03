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
/** @var array $menuPermissionTree */
/** @var string $actionTab */
/** @var array $actionTabIds */
/** @var array $actionTabCounts */
/** @var array $actionTabLabels */

$roleSaveScope = (string)$scope;
$userSaveScope = (string)$scope;
$menuPermissionTree = $menuPermissionTree ?? [];
$actionTabIds = $actionTabIds ?? [];
$actionTabCounts = $actionTabCounts ?? [];
$actionTabLabels = $actionTabLabels ?? [];
$actionTab = (string)($actionTab ?? '');

/**
 * @param list<array<string,mixed>> $nodes
 */
function uda_render_menu_perm_nodes(array $nodes, string $inputClass, array $selectedIds, bool $canSave, int $depth = 0): void
{
    foreach ($nodes as $node) {
        $permId = isset($node['perm_id']) ? (int)$node['perm_id'] : 0;
        $key = isset($node['key']) ? (string)$node['key'] : '';
        $labelKey = isset($node['label']) ? (string)$node['label'] : '';
        $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
        $hasChildren = $children !== [];
        $title = '';
        if ($labelKey !== '') {
            $title = t($labelKey);
        } elseif ($key !== '') {
            $title = (string)($node['display_name'] ?? $key);
        } elseif ($hasChildren) {
            $title = t('admin.permissions.menu_group');
        }
        $pad = 8 + $depth * 16;
        echo '<div class="menu-tree-node" style="margin:4px 0;padding-left:' . (int)$pad . 'px;">';
        if ($title !== '' || $permId > 0 || $hasChildren) {
            echo '<label style="display:flex;align-items:center;gap:8px;font-size:13px;flex-wrap:wrap;">';
            if ($permId > 0) {
                $checked = in_array($permId, $selectedIds, true) ? ' checked' : '';
                $dis = $canSave ? '' : ' disabled';
                echo '<input type="checkbox" class="menu-tree-cb ' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '" name="permission_ids[]" value="' . $permId . '"' . $checked . $dis . '>';
            } elseif ($hasChildren) {
                $dis = $canSave ? '' : ' disabled';
                echo '<input type="checkbox" class="menu-tree-parent-virtual ' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '"' . $dis . '>';
            } elseif ($key !== '' && $permId <= 0) {
                echo '<span class="muted" style="font-size:12px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' — ' . htmlspecialchars(t('admin.permissions.menu_missing_seed', '未入库（请执行 database/seeders/012_menu_nav_permissions_seed.sql）'), ENT_QUOTES, 'UTF-8') . '</span>';
                echo '</label></div>';
                continue;
            }
            if ($title !== '') {
                echo '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '</label>';
        }
        if ($hasChildren) {
            echo '<div class="menu-tree-children">';
            uda_render_menu_perm_nodes($children, $inputClass, $selectedIds, $canSave, $depth + 1);
            echo '</div>';
        }
        echo '</div>';
    }
}
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

<?php if ($scope === 'action' && !empty($actionTabIds)): ?>
<div class="card" style="padding:10px 12px;">
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <?php foreach ($actionTabIds as $tid): ?>
            <?php
            $cnt = (int)($actionTabCounts[$tid] ?? 0);
            $lab = (string)($actionTabLabels[$tid] ?? $tid);
            $isActive = $tid === $actionTab;
            $href = '/system/permissions?scope=action&action_tab=' . rawurlencode((string)$tid)
                . '&role_id=' . (int)$selectedRoleId . '&user_id=' . (int)$selectedUserId;
            ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
               style="padding:6px 10px;border-radius:8px;border:1px solid #e5e7eb;text-decoration:none;font-size:13px;<?php echo $isActive ? 'background:#2563eb;color:#fff;border-color:#2563eb;' : 'background:#fff;color:#111827;'; ?>">
                <?php echo htmlspecialchars($lab . ' (' . $cnt . ')'); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.permissions.role_section')); ?></h3>
    <form method="get" action="/system/permissions" style="margin-bottom:12px;">
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
        <?php if ($scope === 'action'): ?>
            <input type="hidden" name="action_tab" value="<?php echo htmlspecialchars($actionTab); ?>">
        <?php endif; ?>
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
    <form method="post" action="<?php echo htmlspecialchars('/system/permissions?scope=' . rawurlencode($roleSaveScope) . '&role_id=' . (int)$selectedRoleId . '&user_id=' . (int)$selectedUserId . ($scope === 'action' ? '&action_tab=' . rawurlencode($actionTab) : '')); ?>">
        <input type="hidden" name="target_type" value="role">
        <input type="hidden" name="target_id" value="<?php echo (int)$selectedRoleId; ?>">
        <?php if ($scope === 'page' && !empty($menuPermissionTree)): ?>
            <div class="muted" style="margin-bottom:10px;font-size:13px;"><?php echo htmlspecialchars(t('admin.permissions.menu_tree_hint', '勾选上级将联动下级所有菜单项；无数据库 id 的分组框仅用于批量勾选。')); ?></div>
            <div id="role-menu-tree-root" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;">
                <?php uda_render_menu_perm_nodes($menuPermissionTree, 'role-menu-cb', $rolePermissionIds, $canPermissionSave); ?>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
        <?php if ($canPermissionSave): ?>
            <button type="submit" style="margin-top:12px;"><?php echo htmlspecialchars(t('admin.permissions.submit.role')); ?></button>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.permissions.user_section')); ?></h3>
    <form method="get" action="/system/permissions" style="margin-bottom:12px;">
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
        <?php if ($scope === 'action'): ?>
            <input type="hidden" name="action_tab" value="<?php echo htmlspecialchars($actionTab); ?>">
        <?php endif; ?>
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

    <form method="post" action="<?php echo htmlspecialchars('/system/permissions?scope=' . rawurlencode($userSaveScope) . '&role_id=' . (int)$selectedRoleId . '&user_id=' . (int)$selectedUserId . ($scope === 'action' ? '&action_tab=' . rawurlencode($actionTab) : '')); ?>">
        <input type="hidden" name="target_type" value="user">
        <input type="hidden" name="target_id" value="<?php echo (int)$selectedUserId; ?>">
        <?php if ($scope === 'page' && !empty($menuPermissionTree)): ?>
            <div id="user-menu-tree-root" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;">
                <?php uda_render_menu_perm_nodes($menuPermissionTree, 'user-menu-cb', $userPermissionIds, $canPermissionSave); ?>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
        <?php if ($canPermissionSave): ?>
            <button type="submit" style="margin-top:12px;"><?php echo htmlspecialchars(t('admin.permissions.submit.user')); ?></button>
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

    function wireMenuTree(root) {
        if (!root) return;
        root.querySelectorAll('.menu-tree-node').forEach(function (node) {
            const kids = node.querySelector(':scope > .menu-tree-children');
            if (!kids) return;
            const named = kids.querySelectorAll('input[name="permission_ids[]"]');
            if (!named.length) return;
            const vParent = node.querySelector(':scope > label > .menu-tree-parent-virtual');
            const realParent = node.querySelector(':scope > label > .menu-tree-cb[name="permission_ids[]"]');
            function syncParentFromKids(cb) {
                const allOn = Array.from(named).every(function (x) { return x.checked; });
                const anyOn = Array.from(named).some(function (x) { return x.checked; });
                if (vParent) {
                    vParent.checked = allOn;
                    vParent.indeterminate = !allOn && anyOn;
                }
                if (realParent) {
                    realParent.checked = allOn;
                    realParent.indeterminate = !allOn && anyOn;
                }
            }
            named.forEach(function (inp) {
                inp.addEventListener('change', function () { syncParentFromKids(); });
            });
            if (vParent) {
                vParent.addEventListener('change', function () {
                    named.forEach(function (inp) { inp.checked = vParent.checked; });
                    if (realParent) { realParent.checked = vParent.checked; realParent.indeterminate = false; }
                });
            }
            if (realParent) {
                realParent.addEventListener('change', function () {
                    named.forEach(function (inp) { inp.checked = realParent.checked; });
                    if (vParent) { vParent.checked = realParent.checked; vParent.indeterminate = false; }
                });
            }
            syncParentFromKids();
        });
    }
    wireMenuTree(document.getElementById('role-menu-tree-root'));
    wireMenuTree(document.getElementById('user-menu-tree-root'));
});
</script>
