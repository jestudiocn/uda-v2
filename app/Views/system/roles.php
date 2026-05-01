<?php
/** @var array $roles */
/** @var ?array $editRole */
/** @var int $selectedRoleId */
/** @var string $message */
/** @var string $error */
/** @var bool $canRoleCreate */
/** @var bool $canRoleEdit */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2><?php echo htmlspecialchars(t('admin.roles.heading')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('admin.roles.intro')); ?></div>
</div>

<?php if (!empty($message)): ?>
<div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.roles.create.heading')); ?></h3>
    <?php if (!$canRoleCreate): ?>
        <div class="muted"><?php echo htmlspecialchars(t('admin.roles.create.no_permission')); ?></div>
    <?php else: ?>
        <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px;">
            <input type="hidden" name="create_role" value="1">
            <div>
                <label><?php echo htmlspecialchars(t('admin.roles.field.name')); ?></label><br>
                <input type="text" name="role_name" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('admin.roles.field.description')); ?></label><br>
                <input type="text" name="description">
            </div>
            <div style="grid-column:1 / -1;">
                <button type="submit"><?php echo htmlspecialchars(t('admin.roles.submit.create')); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if ($editRole && $canRoleEdit): ?>
<div class="card">
    <h3><?php echo htmlspecialchars(sprintf(t('admin.roles.edit.heading'), (string)$editRole['role_name'])); ?></h3>
    <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px;">
        <input type="hidden" name="update_role" value="1">
        <input type="hidden" name="role_id" value="<?php echo (int)$editRole['id']; ?>">
        <div>
            <label><?php echo htmlspecialchars(t('admin.roles.field.name')); ?></label><br>
            <input type="text" name="role_name" required value="<?php echo htmlspecialchars($editRole['role_name']); ?>">
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('admin.roles.field.description')); ?></label><br>
            <input type="text" name="description" value="<?php echo htmlspecialchars($editRole['description'] ?? ''); ?>">
        </div>
        <div class="inline-actions" style="grid-column:1 / -1;">
            <button type="submit"><?php echo htmlspecialchars(t('admin.roles.submit.save')); ?></button>
            <a class="btn" href="<?php echo htmlspecialchars('/system/permissions?scope=page&role_id=' . (int)$editRole['id']); ?>"><?php echo htmlspecialchars(t('admin.roles.action.permissions')); ?></a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3><?php echo htmlspecialchars(t('admin.roles.list.heading')); ?></h3>
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
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.roles.col.id')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.roles.col.name')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.roles.col.description')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.roles.col.user_count')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.roles.col.created_at')); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars(t('admin.roles.col.actions')); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($roles)): ?>
                <tr><td colspan="6" class="muted" style="padding:10px;"><?php echo htmlspecialchars(t('admin.roles.empty')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo (int)$role['id']; ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($role['role_name']); ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($role['description'] ?? ''); ?></td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo (int)$role['user_count']; ?></td>
                        <td class="cell-tip" style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo html_cell_tip_content($role['created_at'] ?? ''); ?></td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                            <div class="cell-actions">
                            <?php if ($canRoleEdit): ?>
                                <a class="btn" href="<?php echo htmlspecialchars('/system/roles?role_id=' . (int)$role['id']); ?>"><?php echo htmlspecialchars(t('admin.roles.action.edit')); ?></a>
                            <?php endif; ?>
                            <a class="btn" href="<?php echo htmlspecialchars('/system/permissions?scope=page&role_id=' . (int)$role['id']); ?>"><?php echo htmlspecialchars(t('admin.roles.action.permissions_short')); ?></a>
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
                <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/system/roles?role_id=<?php echo (int)($selectedRoleId ?? 0); ?>&per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>
