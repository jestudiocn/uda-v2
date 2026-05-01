<div class="card">
    <h2 style="margin:0 0 8px;"><?php echo htmlspecialchars(t('auth.login', '登录')); ?></h2>
    <p class="muted" style="margin:0 0 14px;"><?php echo htmlspecialchars(t('auth.login.subtitle', '请输入账号与密码')); ?></p>

    <?php if (!empty($error)): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:12px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="row">
            <label for="username"><?php echo htmlspecialchars(t('auth.username', '账号')); ?></label>
            <input id="username" type="text" name="username" required>
        </div>
        <div class="row">
            <label for="password"><?php echo htmlspecialchars(t('auth.password', '密码')); ?></label>
            <input id="password" type="password" name="password" required>
        </div>
        <button type="submit" style="min-width:74px;"><?php echo htmlspecialchars(t('auth.login.submit', '登录')); ?></button>
    </form>
</div>
