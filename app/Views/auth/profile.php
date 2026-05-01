<?php
/** @var array $profile */
/** @var string $message */
/** @var string $error */
?>
<div class="card">
    <h2><?php echo htmlspecialchars(t('auth.profile.title', '个人设置')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('auth.profile.subtitle', '可在此修改个人信息与登录密码')); ?></div>
</div>

<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid" style="max-width:760px;grid-template-columns:repeat(2,minmax(220px,1fr));gap:12px;">
        <div>
            <label><?php echo htmlspecialchars(t('auth.force.field.full_name', '姓名')); ?></label><br>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars((string)($profile['full_name'] ?? '')); ?>" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('auth.force.field.phone', '电话')); ?></label><br>
            <input type="text" name="phone" value="<?php echo htmlspecialchars((string)($profile['phone'] ?? '')); ?>" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('auth.force.field.wechat', '微信')); ?></label><br>
            <input type="text" name="wechat" value="<?php echo htmlspecialchars((string)($profile['wechat'] ?? '')); ?>">
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('auth.force.field.line_id', 'Line ID')); ?></label><br>
            <input type="text" name="line_id" value="<?php echo htmlspecialchars((string)($profile['line_id'] ?? '')); ?>">
        </div>

        <div class="form-full" style="margin-top:6px;padding-top:10px;border-top:1px solid #e5e7eb;">
            <div style="font-weight:700;margin-bottom:8px;">修改密码（可选）</div>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('auth.profile.current_password', '当前密码')); ?></label><br>
            <input type="password" name="current_password" autocomplete="current-password">
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('auth.force.field.new_password', '新密码')); ?></label><br>
            <input type="password" name="new_password" autocomplete="new-password">
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('auth.force.field.confirm_password', '确认新密码')); ?></label><br>
            <input type="password" name="confirm_password" autocomplete="new-password">
        </div>
        <div class="form-full inline-actions">
            <button type="submit"><?php echo htmlspecialchars(t('auth.profile.save', '保存设置')); ?></button>
        </div>
    </form>
</div>
