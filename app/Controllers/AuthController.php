<?php

class AuthController
{
    private const SUPPORTED_LOCALES = ['zh-CN', 'th-TH'];
    private array $tableExistsCache = [];

    private function tableExists(mysqli $conn, string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }
        $safeTable = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $this->tableExistsCache[$table] = $exists;
        return $exists;
    }

    private function writeLoginNotification(
        mysqli $conn,
        int $userId,
        string $username,
        string $fullName
    ): void {
        if ($userId <= 0 || !$this->tableExists($conn, 'notifications_inbox')) {
            return;
        }
        $displayName = trim($fullName) !== '' ? trim($fullName) : $username;
        $title = '登入通知';
        $content = sprintf('账号 %s 于 %s 成功登入系统。', $displayName, date('Y-m-d H:i:s'));
        $bizType = 'auth_login';
        $createdBy = $userId;
        $recipientIds = [$userId];
        if ($this->tableExists($conn, 'notification_rules')) {
            $ruleStmt = $conn->prepare('
                SELECT enabled, recipients_mode, custom_user_ids
                FROM notification_rules
                WHERE event_key = ?
                LIMIT 1
            ');
            if ($ruleStmt) {
                $eventKey = 'auth.login';
                $ruleStmt->bind_param('s', $eventKey);
                $ruleStmt->execute();
                $rule = $ruleStmt->get_result()->fetch_assoc();
                $ruleStmt->close();
                if ($rule && (int)($rule['enabled'] ?? 0) === 1) {
                    $mode = (string)($rule['recipients_mode'] ?? 'creator');
                    $recipientIds = [];
                    if ($mode === 'all_active_users') {
                        $res = $conn->query('SELECT id FROM users WHERE status = 1');
                        while ($res && ($row = $res->fetch_assoc())) {
                            $rid = (int)($row['id'] ?? 0);
                            if ($rid > 0) {
                                $recipientIds[] = $rid;
                            }
                        }
                    } elseif ($mode === 'custom_users') {
                        $csv = trim((string)($rule['custom_user_ids'] ?? ''));
                        if ($csv !== '') {
                            foreach (explode(',', $csv) as $part) {
                                $rid = (int)trim($part);
                                if ($rid > 0) {
                                    $recipientIds[] = $rid;
                                }
                            }
                        }
                    } else {
                        $recipientIds[] = $userId;
                    }
                }
            }
        }
        $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), static fn ($v) => $v > 0)));
        if (empty($recipientIds)) {
            return;
        }
        $stmt = $conn->prepare('
            INSERT INTO notifications_inbox (user_id, title, content, biz_type, biz_id, created_by, is_read)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ');
        if ($stmt) {
            $bizId = 0;
            foreach ($recipientIds as $recipientId) {
                $stmt->bind_param('isssii', $recipientId, $title, $content, $bizType, $bizId, $createdBy);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    private function writeAuditLog(
        mysqli $conn,
        string $moduleKey,
        string $actionKey,
        ?string $targetType = null,
        ?int $targetId = null,
        array $detail = []
    ): void {
        $tblRes = $conn->query("SHOW TABLES LIKE 'system_audit_logs'");
        $ready = $tblRes instanceof mysqli_result && $tblRes->num_rows > 0;
        if ($tblRes instanceof mysqli_result) {
            $tblRes->free();
        }
        if (!$ready) {
            return;
        }
        $actorUserId = isset($_SESSION['auth_user_id']) ? (int)$_SESSION['auth_user_id'] : null;
        $actorName = trim((string)($_SESSION['auth_full_name'] ?? ''));
        if ($actorName === '') {
            $actorName = (string)($_SESSION['auth_username'] ?? ($detail['username'] ?? ''));
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $json = !empty($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $conn->prepare('
            INSERT INTO system_audit_logs (
                module_key, action_key, target_type, target_id, actor_user_id, actor_name, detail_json, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmt) {
            $stmt->bind_param(
                'sssissss',
                $moduleKey,
                $actionKey,
                $targetType,
                $targetId,
                $actorUserId,
                $actorName,
                $json,
                $ip
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    private function sanitizeInternalRedirect(string $raw): string
    {
        $raw = str_replace(["\r", "\n", "\0"], '', trim($raw));
        if ($raw === '' || $raw[0] !== '/') {
            return '/';
        }
        if (str_starts_with($raw, '//') || str_starts_with($raw, '/\\')) {
            return '/';
        }
        if (strlen($raw) > 2000) {
            return '/';
        }
        return $raw;
    }

    /**
     * POST /locale — 写入 session；已登录则同步 users.locale（需 migration 005）。
     */
    public function setLocale(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $loc = (string)($_POST['app_locale'] ?? '');
        $redirect = $this->sanitizeInternalRedirect((string)($_POST['redirect'] ?? '/'));

        if (!in_array($loc, self::SUPPORTED_LOCALES, true)) {
            header('Location: ' . $redirect);
            exit;
        }

        $_SESSION['app_locale'] = $loc;

        if (isset($_SESSION['auth_user_id'])) {
            try {
                $conn = require __DIR__ . '/../../config/database.php';
                $userId = (int)$_SESSION['auth_user_id'];
                $stmt = $conn->prepare('UPDATE users SET locale = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $loc, $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Throwable $e) {
                // 未执行 database/migrations/005_add_user_locale.sql 时无 locale 列：仍以 session 为准，不阻断切换。
            }
        }

        header('Location: ' . $redirect);
        exit;
    }

    public function login(): void
    {
        $conn = require __DIR__ . '/../../config/database.php';
        $error = '';
        $isLoggedIn = isset($_SESSION['auth_user_id']);

        if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ((int)($_SESSION['must_change_password'] ?? 0) === 1) {
                header('Location: /force-profile');
                exit;
            }
            // 已登录也允许打开登录页，便于切换账号。
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = t('auth.error.missing_credentials', '请输入账号和密码');
            } else {
                $user = null;
                try {
                    $stmt = $conn->prepare('
                        SELECT id, username, full_name, phone, wechat, line_id, role_id, status, must_change_password, password_hash, locale
                        FROM users
                        WHERE username = ?
                        LIMIT 1
                    ');
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } catch (Throwable $e) {
                    $stmt = $conn->prepare('
                        SELECT id, username, full_name, phone, wechat, line_id, role_id, status, must_change_password, password_hash
                        FROM users
                        WHERE username = ?
                        LIMIT 1
                    ');
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }

                if (!$user || (int)$user['status'] !== 1) {
                    $error = t('auth.error.disabled_or_missing', '账号不存在或已停用');
                    $this->writeAuditLog($conn, 'auth', 'auth.login.failed', 'user', null, ['username' => $username, 'reason' => 'missing_or_disabled']);
                } elseif (!password_verify($password, (string)$user['password_hash'])) {
                    $error = t('auth.error.bad_password', '账号或密码错误');
                    $this->writeAuditLog($conn, 'auth', 'auth.login.failed', 'user', (int)$user['id'], ['username' => $username, 'reason' => 'bad_password']);
                } else {
                    // 先清掉旧账号权限缓存，避免切换账号时残留。
                    unset(
                        $_SESSION['auth_permission_keys'],
                        $_SESSION['auth_role_name'],
                        $_SESSION['auth_role_id']
                    );
                    $_SESSION['auth_user_id'] = (int)$user['id'];
                    $_SESSION['auth_username'] = (string)$user['username'];
                    $_SESSION['auth_full_name'] = (string)($user['full_name'] ?? '');
                    $_SESSION['auth_phone'] = (string)($user['phone'] ?? '');
                    $_SESSION['auth_wechat'] = (string)($user['wechat'] ?? '');
                    $_SESSION['auth_line_id'] = (string)($user['line_id'] ?? '');
                    $_SESSION['auth_role_id'] = (int)$user['role_id'];
                    $_SESSION['must_change_password'] = (int)$user['must_change_password'];
                    $_SESSION['show_pending_todo_popup'] = 1;

                    if (array_key_exists('locale', $user)) {
                        $userLocale = trim((string)($user['locale'] ?? ''));
                        if ($userLocale !== '' && in_array($userLocale, self::SUPPORTED_LOCALES, true)) {
                            $_SESSION['app_locale'] = $userLocale;
                        }
                    }

                    if ((int)$user['must_change_password'] === 1) {
                        $this->writeLoginNotification(
                            $conn,
                            (int)$user['id'],
                            (string)$user['username'],
                            (string)($user['full_name'] ?? '')
                        );
                        $this->writeAuditLog($conn, 'auth', 'auth.login.success', 'user', (int)$user['id'], ['must_change_password' => 1]);
                        header('Location: /force-profile');
                        exit;
                    }

                    $this->writeLoginNotification(
                        $conn,
                        (int)$user['id'],
                        (string)$user['username'],
                        (string)($user['full_name'] ?? '')
                    );
                    $this->writeAuditLog($conn, 'auth', 'auth.login.success', 'user', (int)$user['id'], ['must_change_password' => 0]);
                    header('Location: /');
                    exit;
                }
            }
        }

        $title = t('auth.login', '登录');
        $contentView = __DIR__ . '/../Views/auth/login.php';
        require __DIR__ . '/../Views/auth/auth_layout.php';
    }

    public function forceProfile(): void
    {
        if (!isset($_SESSION['auth_user_id'])) {
            header('Location: /login');
            exit;
        }

        // 防止浏览器缓存导致完成后后退仍停留在此页
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        if ((int)($_SESSION['must_change_password'] ?? 0) !== 1) {
            header('Location: /');
            exit;
        }

        $conn = require __DIR__ . '/../../config/database.php';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $wechat = trim($_POST['wechat'] ?? '');
            $lineId = trim($_POST['line_id'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            if ($fullName === '' || $phone === '') {
                $error = t('auth.force.error.profile', '首次登录请填写姓名与电话');
            } elseif ($newPassword === '' || strlen($newPassword) < 6) {
                $error = t('auth.force.error.password_short', '新密码至少 6 位');
            } elseif ($newPassword !== $confirmPassword) {
                $error = t('auth.force.error.password_mismatch', '两次输入的密码不一致');
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $userId = (int)$_SESSION['auth_user_id'];
                $stmt = $conn->prepare('
                    UPDATE users
                    SET full_name = ?, phone = ?, wechat = ?, line_id = ?, password_hash = ?, must_change_password = 0
                    WHERE id = ?
                ');
                $stmt->bind_param('sssssi', $fullName, $phone, $wechat, $lineId, $newHash, $userId);
                $stmt->execute();
                $stmt->close();

                $_SESSION['auth_full_name'] = $fullName;
                $_SESSION['auth_phone'] = $phone;
                $_SESSION['auth_wechat'] = $wechat;
                $_SESSION['auth_line_id'] = $lineId;
                $_SESSION['must_change_password'] = 0;
                $this->writeAuditLog($conn, 'auth', 'auth.force_profile.completed', 'user', $userId);

                header('Location: /');
                exit;
            }
        }

        $title = t('auth.force.title', '首次登录：请完善资料并修改密码');
        $contentView = __DIR__ . '/../Views/auth/force_profile.php';
        require __DIR__ . '/../Views/auth/auth_layout.php';
    }

    public function logout(): void
    {
        $conn = require __DIR__ . '/../../config/database.php';
        $uid = isset($_SESSION['auth_user_id']) ? (int)$_SESSION['auth_user_id'] : null;
        $uname = (string)($_SESSION['auth_username'] ?? '');
        $this->writeAuditLog($conn, 'auth', 'auth.logout', 'user', $uid, ['username' => $uname]);

        $locale = (string)($_SESSION['app_locale'] ?? 'zh-CN');
        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'zh-CN';
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['app_locale'] = $locale;

        header('Location: /login');
        exit;
    }

    public function profile(): void
    {
        if (!isset($_SESSION['auth_user_id'])) {
            header('Location: /login');
            exit;
        }

        $conn = require __DIR__ . '/../../config/database.php';
        $userId = (int)$_SESSION['auth_user_id'];
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $wechat = trim((string)($_POST['wechat'] ?? ''));
            $lineId = trim((string)($_POST['line_id'] ?? ''));
            $currentPassword = trim((string)($_POST['current_password'] ?? ''));
            $newPassword = trim((string)($_POST['new_password'] ?? ''));
            $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));

            if ($fullName === '' || $phone === '') {
                $error = '姓名与电话不能为空';
            } else {
                $needChangePassword = ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '');
                $newHash = '';
                if ($needChangePassword) {
                    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                        $error = '修改密码时请完整填写当前密码、新密码、确认密码';
                    } elseif (strlen($newPassword) < 6) {
                        $error = '新密码至少 6 位';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = '两次输入的新密码不一致';
                    } else {
                        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                        $stmt->bind_param('i', $userId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $storedHash = (string)($row['password_hash'] ?? '');
                        if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
                            $error = '当前密码错误';
                        } else {
                            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        }
                    }
                }

                if ($error === '') {
                    if ($needChangePassword) {
                        $stmt = $conn->prepare('
                            UPDATE users
                            SET full_name = ?, phone = ?, wechat = ?, line_id = ?, password_hash = ?, must_change_password = 0
                            WHERE id = ?
                        ');
                        $stmt->bind_param('sssssi', $fullName, $phone, $wechat, $lineId, $newHash, $userId);
                    } else {
                        $stmt = $conn->prepare('
                            UPDATE users
                            SET full_name = ?, phone = ?, wechat = ?, line_id = ?
                            WHERE id = ?
                        ');
                        $stmt->bind_param('ssssi', $fullName, $phone, $wechat, $lineId, $userId);
                    }
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['auth_full_name'] = $fullName;
                    $_SESSION['auth_phone'] = $phone;
                    $_SESSION['auth_wechat'] = $wechat;
                    $_SESSION['auth_line_id'] = $lineId;
                    $_SESSION['must_change_password'] = 0;
                    $this->writeAuditLog($conn, 'auth', 'auth.profile.updated', 'user', $userId, [
                        'password_changed' => $needChangePassword ? 1 : 0,
                    ]);

                    header('Location: /profile?msg=saved');
                    exit;
                }
            }
        }

        if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
            $message = '个人信息已更新';
        }

        $profile = [
            'full_name' => (string)($_SESSION['auth_full_name'] ?? ''),
            'phone' => (string)($_SESSION['auth_phone'] ?? ''),
            'wechat' => (string)($_SESSION['auth_wechat'] ?? ''),
            'line_id' => (string)($_SESSION['auth_line_id'] ?? ''),
        ];

        $title = '个人设置';
        $contentView = __DIR__ . '/../Views/auth/profile.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }
}
