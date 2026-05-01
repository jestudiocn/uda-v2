<div class="card">
    <h2 style="margin:0 0 12px;"><?php echo htmlspecialchars(t('auth.force.title')); ?></h2>
    <p class="muted" style="margin:0 0 14px;"><?php echo htmlspecialchars(t('auth.force.intro')); ?></p>

    <?php if (!empty($error)): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:12px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/force-profile">
        <div class="row">
            <label for="full_name"><?php echo htmlspecialchars(t('auth.force.field.full_name')); ?></label>
            <input id="full_name" type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['auth_full_name'] ?? ''); ?>" required>
        </div>
        <div class="row">
            <label for="phone"><?php echo htmlspecialchars(t('auth.force.field.phone')); ?></label>
            <input id="phone" type="text" name="phone" value="<?php echo htmlspecialchars($_SESSION['auth_phone'] ?? ''); ?>" required>
        </div>
        <div class="row">
            <label for="wechat"><?php echo htmlspecialchars(t('auth.force.field.wechat')); ?></label>
            <input id="wechat" type="text" name="wechat" value="<?php echo htmlspecialchars($_SESSION['auth_wechat'] ?? ''); ?>">
        </div>
        <div class="row">
            <label for="line_id"><?php echo htmlspecialchars(t('auth.force.field.line_id')); ?></label>
            <input id="line_id" type="text" name="line_id" value="<?php echo htmlspecialchars($_SESSION['auth_line_id'] ?? ''); ?>">
        </div>
        <div class="row">
            <label for="new_password"><?php echo htmlspecialchars(t('auth.force.field.new_password')); ?></label>
            <input id="new_password" type="password" name="new_password" required>
        </div>
        <div class="row">
            <label for="confirm_password"><?php echo htmlspecialchars(t('auth.force.field.confirm_password')); ?></label>
            <input id="confirm_password" type="password" name="confirm_password" required>
        </div>
        <button type="submit"><?php echo htmlspecialchars(t('auth.force.submit')); ?></button>
    </form>
</div>
