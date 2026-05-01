<?php
require_once __DIR__ . '/Concerns/AuditLogTrait.php';

class SystemController
{
    use AuditLogTrait;
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

    private function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $safeT = $conn->real_escape_string($table);
        $safeC = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeT}` LIKE '{$safeC}'");
        $ok = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $ok;
    }

    private function writeAuditLog(
        mysqli $conn,
        string $moduleKey,
        string $actionKey,
        ?string $targetType = null,
        ?int $targetId = null,
        array $detail = []
    ): void {
        $this->writeStandardAuditLog($conn, $moduleKey, $actionKey, $targetType, $targetId, $detail);
    }

    private function withLang(string $path): string
    {
        return $path;
    }

    private function permTranslationKey(string $permissionKey): string
    {
        // 与 lang/zh-CN.php、lang/th-TH.php 中 perm.* 键一致（保留 permission_key 里的点号）
        return 'perm.' . $permissionKey;
    }

    private function hasAnyPermission(array $keys): bool
    {
        if (!function_exists('hasPermissionKey')) {
            return false;
        }
        foreach ($keys as $key) {
            if (hasPermissionKey((string)$key)) {
                return true;
            }
        }
        return false;
    }

    private function isPagePermissionKey(string $permissionKey): bool
    {
        return str_starts_with($permissionKey, 'menu.') || str_contains($permissionKey, '.page.');
    }

    private function denyNoPermission(?string $messageKey = null): void
    {
        http_response_code(403);
        $key = $messageKey ?? 'admin.deny.default';
        if (function_exists('t')) {
            echo t($key);
        } else {
            echo $key;
        }
        exit;
    }

    private function resolvePerPage(array $allowed = [20, 50, 100], int $default = 20): int
    {
        $perPage = (int)($_GET['per_page'] ?? $default);
        if (!in_array($perPage, $allowed, true)) {
            $perPage = $default;
        }
        return $perPage;
    }

    private function resolvePage(): int
    {
        $page = (int)($_GET['page'] ?? 1);
        return $page > 0 ? $page : 1;
    }

    public function users(): void
    {
        if (!function_exists('hasPermissionKey') || !hasPermissionKey('menu.users')) {
            $this->denyNoPermission('admin.deny.users_page');
        }

        $canUserCreate = $this->hasAnyPermission(['users.create', 'users.manage']);
        $canUserToggle = $this->hasAnyPermission(['users.toggle', 'users.manage']);
        $canUserReset = $this->hasAnyPermission(['users.reset_password', 'users.manage']);
        $canUserManage = $this->hasAnyPermission(['users.manage']);

        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user']) && is_numeric((string)($_POST['target_user_id'] ?? ''))) {
            if (!$canUserToggle) {
                $this->denyNoPermission();
            }
            $userId = (int)($_POST['target_user_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE users SET status = IF(status = 1, 0, 1) WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            $this->writeAuditLog($conn, 'users', 'users.toggle', 'user', $userId);
            header('Location: ' . $this->withLang('/system/users?msg=toggled'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_password']) && is_numeric((string)($_POST['target_user_id'] ?? ''))) {
            if (!$canUserReset) {
                $this->denyNoPermission();
            }
            $userId = (int)($_POST['target_user_id'] ?? 0);
            $defaultPassword = '123456';
            $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?');
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $stmt->close();
            $this->writeAuditLog($conn, 'users', 'users.reset_password', 'user', $userId);
            header('Location: ' . $this->withLang('/system/users?msg=reset'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bind_user_dispatch_cc'])) {
            if (!$canUserManage) {
                $this->denyNoPermission();
            }
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $bindCcId = (int)($_POST['dispatch_consigning_client_id'] ?? 0);
            if ($targetUserId <= 0) {
                $error = '无效的用户';
            } elseif (!$this->columnExists($conn, 'users', 'dispatch_consigning_client_id')) {
                $error = '请先执行数据库迁移：database/migrations/023_user_dispatch_consigning_client.sql';
            } elseif (!$this->tableExists($conn, 'dispatch_consigning_clients')) {
                $error = '派送模块未初始化，无法绑定委托客户';
            } elseif ($bindCcId > 0) {
                $chk = $conn->prepare('SELECT id FROM dispatch_consigning_clients WHERE id = ? LIMIT 1');
                if ($chk) {
                    $chk->bind_param('i', $bindCcId);
                    $chk->execute();
                    $okCc = $chk->get_result()->fetch_assoc();
                    $chk->close();
                    if (!$okCc) {
                        $error = '委托客户不存在';
                    } else {
                        $stmt = $conn->prepare('UPDATE users SET dispatch_consigning_client_id = ? WHERE id = ?');
                        if ($stmt) {
                            $stmt->bind_param('ii', $bindCcId, $targetUserId);
                            $stmt->execute();
                            $stmt->close();
                        }
                        $this->writeAuditLog($conn, 'users', 'users.bind_dispatch_client', 'user', $targetUserId, [
                            'dispatch_consigning_client_id' => $bindCcId,
                        ]);
                        header('Location: ' . $this->withLang('/system/users?msg=bound'));
                        exit;
                    }
                } else {
                    $error = '保存失败';
                }
            } else {
                $stmt = $conn->prepare('UPDATE users SET dispatch_consigning_client_id = NULL WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $targetUserId);
                    $stmt->execute();
                    $stmt->close();
                }
                $this->writeAuditLog($conn, 'users', 'users.bind_dispatch_client', 'user', $targetUserId, [
                    'dispatch_consigning_client_id' => null,
                ]);
                header('Location: ' . $this->withLang('/system/users?msg=bound_clear'));
                exit;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
            if (!$canUserCreate) {
                $this->denyNoPermission();
            }
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $wechat = trim($_POST['wechat'] ?? '');
            $lineId = trim($_POST['line_id'] ?? '');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $mustChange = 1;

            if ($username === '' || $password === '' || $roleId <= 0) {
                $error = t('admin.users.err.required');
            } else {
                $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $error = t('admin.users.err.exists');
                } else {
                    $dccNew = (int)($_POST['dispatch_consigning_client_id'] ?? 0);
                    $hasDccCol = $this->columnExists($conn, 'users', 'dispatch_consigning_client_id');
                    $dccErr = '';
                    if ($hasDccCol && $dccNew > 0) {
                        if (!$this->tableExists($conn, 'dispatch_consigning_clients')) {
                            $dccErr = '派送模块未初始化，无法绑定委托客户';
                        } else {
                            $chk = $conn->prepare('SELECT id FROM dispatch_consigning_clients WHERE id = ? AND status = 1 LIMIT 1');
                            if ($chk) {
                                $chk->bind_param('i', $dccNew);
                                $chk->execute();
                                if (!$chk->get_result()->fetch_assoc()) {
                                    $dccErr = '所选委托客户无效';
                                }
                                $chk->close();
                            } else {
                                $dccErr = '校验委托客户失败';
                            }
                        }
                    }
                    if ($dccErr !== '') {
                        $error = $dccErr;
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = null;
                        if ($hasDccCol && $dccNew > 0) {
                            $stmt = $conn->prepare('
                                INSERT INTO users (username, password_hash, full_name, phone, wechat, line_id, role_id, status, must_change_password, dispatch_consigning_client_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                            ');
                            if ($stmt) {
                                $stmt->bind_param('ssssssiii', $username, $hash, $fullName, $phone, $wechat, $lineId, $roleId, $mustChange, $dccNew);
                            }
                        } elseif ($hasDccCol) {
                            $stmt = $conn->prepare('
                                INSERT INTO users (username, password_hash, full_name, phone, wechat, line_id, role_id, status, must_change_password, dispatch_consigning_client_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NULL)
                            ');
                            if ($stmt) {
                                $stmt->bind_param('ssssssii', $username, $hash, $fullName, $phone, $wechat, $lineId, $roleId, $mustChange);
                            }
                        } else {
                            $stmt = $conn->prepare('
                                INSERT INTO users (username, password_hash, full_name, phone, wechat, line_id, role_id, status, must_change_password)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                            ');
                            if ($stmt) {
                                $stmt->bind_param('ssssssii', $username, $hash, $fullName, $phone, $wechat, $lineId, $roleId, $mustChange);
                            }
                        }
                        if (!$stmt) {
                            $error = '创建用户失败';
                        } else {
                            $stmt->execute();
                            $newUserId = (int)$stmt->insert_id;
                            $stmt->close();
                            $this->writeAuditLog($conn, 'users', 'users.create', 'user', $newUserId, [
                                'username' => $username,
                                'role_id' => $roleId,
                                'dispatch_consigning_client_id' => $dccNew > 0 ? $dccNew : null,
                            ]);
                            header('Location: ' . $this->withLang('/system/permissions?scope=action&user_id=' . $newUserId . '&msg=user_created'));
                            exit;
                        }
                    }
                }
            }
        }

        if (isset($_GET['msg'])) {
            if ($_GET['msg'] === 'created') {
                $message = t('admin.users.msg.created');
            } elseif ($_GET['msg'] === 'toggled') {
                $message = t('admin.users.msg.toggled');
            } elseif ($_GET['msg'] === 'reset') {
                $message = t('admin.users.msg.reset');
            } elseif ($_GET['msg'] === 'bound') {
                $message = '已更新该用户的派送数据绑定（委托客户）。';
            } elseif ($_GET['msg'] === 'bound_clear') {
                $message = '已清除该用户的派送数据绑定，账号将按公司内部规则查看全部委托客户订单。';
            }
        }

        $roles = [];
        $roleRes = $conn->query('SELECT id, role_name FROM roles ORDER BY id ASC');
        while ($roleRes && ($row = $roleRes->fetch_assoc())) {
            $roles[] = $row;
        }

        $dispatchClientsForBind = [];
        if ($this->tableExists($conn, 'dispatch_consigning_clients')) {
            $dccRes = $conn->query('SELECT id, client_code, client_name FROM dispatch_consigning_clients WHERE status = 1 ORDER BY client_code ASC');
            while ($dccRes && ($row = $dccRes->fetch_assoc())) {
                $dispatchClientsForBind[] = $row;
            }
            if ($dccRes instanceof mysqli_result) {
                $dccRes->free();
            }
        }
        $hasUserDispatchBindCol = $this->columnExists($conn, 'users', 'dispatch_consigning_client_id');

        $users = [];
        if ($hasUserDispatchBindCol && $this->tableExists($conn, 'dispatch_consigning_clients')) {
            $userRes = $conn->query('
                SELECT u.id, u.username, u.full_name, u.phone, u.wechat, u.line_id, u.status, u.must_change_password, u.created_at,
                       u.dispatch_consigning_client_id,
                       r.role_name,
                       dcc.client_code AS dispatch_client_code, dcc.client_name AS dispatch_client_name
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN dispatch_consigning_clients dcc ON dcc.id = u.dispatch_consigning_client_id
                ORDER BY u.id ASC
            ');
        } else {
            $userRes = $conn->query('
                SELECT u.id, u.username, u.full_name, u.phone, u.wechat, u.line_id, u.status, u.must_change_password, u.created_at, r.role_name
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                ORDER BY u.id ASC
            ');
        }
        while ($userRes && ($row = $userRes->fetch_assoc())) {
            $users[] = $row;
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $total = count($users);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $users = array_slice($users, ($page - 1) * $perPage, $perPage);

        $title = t('admin.title.users');
        $contentView = __DIR__ . '/../Views/system/users.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function roles(): void
    {
        if (!function_exists('hasPermissionKey') || !hasPermissionKey('menu.roles')) {
            $this->denyNoPermission('admin.deny.roles_page');
        }

        $canRoleCreate = $this->hasAnyPermission(['roles.create', 'roles.manage']);
        $canRoleEdit = $this->hasAnyPermission(['roles.edit', 'roles.manage']);

        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
            if (!$canRoleCreate) {
                $this->denyNoPermission();
            }
            $roleName = trim($_POST['role_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($roleName === '') {
                $error = t('admin.roles.err.name_required');
            } else {
                $stmt = $conn->prepare('SELECT id FROM roles WHERE role_name = ? LIMIT 1');
                $stmt->bind_param('s', $roleName);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $error = t('admin.roles.err.name_exists');
                } else {
                    $stmt = $conn->prepare('INSERT INTO roles (role_name, description) VALUES (?, ?)');
                    $stmt->bind_param('ss', $roleName, $description);
                    $stmt->execute();
                    $newRoleId = (int)$stmt->insert_id;
                    $stmt->close();
                    $this->writeAuditLog($conn, 'roles', 'roles.create', 'role', $newRoleId, [
                        'role_name' => $roleName,
                    ]);
                    header('Location: ' . $this->withLang('/system/roles?msg=created&role_id=' . $newRoleId));
                    exit;
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
            if (!$canRoleEdit) {
                $this->denyNoPermission();
            }
            $roleId = (int)($_POST['role_id'] ?? 0);
            $roleName = trim($_POST['role_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($roleId <= 0 || $roleName === '') {
                $error = t('admin.roles.err.params');
            } else {
                $stmt = $conn->prepare('SELECT id FROM roles WHERE role_name = ? AND id <> ? LIMIT 1');
                $stmt->bind_param('si', $roleName, $roleId);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $error = t('admin.roles.err.name_exists');
                } else {
                    $stmt = $conn->prepare('UPDATE roles SET role_name = ?, description = ? WHERE id = ?');
                    $stmt->bind_param('ssi', $roleName, $description, $roleId);
                    $stmt->execute();
                    $stmt->close();
                    $this->writeAuditLog($conn, 'roles', 'roles.edit', 'role', $roleId, [
                        'role_name' => $roleName,
                    ]);
                    header('Location: ' . $this->withLang('/system/roles?msg=updated&role_id=' . $roleId));
                    exit;
                }
            }
        }

        if (isset($_GET['msg'])) {
            if ($_GET['msg'] === 'created') {
                $message = t('admin.roles.msg.created');
            } elseif ($_GET['msg'] === 'updated') {
                $message = t('admin.roles.msg.updated');
            }
        }

        $roles = [];
        $res = $conn->query("
            SELECT r.id, r.role_name, r.description, r.created_at,
                   (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
            FROM roles r
            ORDER BY r.id ASC
        ");
        while ($res && ($row = $res->fetch_assoc())) {
            $roles[] = $row;
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $total = count($roles);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $roles = array_slice($roles, ($page - 1) * $perPage, $perPage);

        $selectedRoleId = (int)($_GET['role_id'] ?? 0);
        if ($selectedRoleId <= 0 && !empty($roles)) {
            $selectedRoleId = (int)$roles[0]['id'];
        }

        $editRole = null;
        foreach ($roles as $role) {
            if ((int)$role['id'] === $selectedRoleId) {
                $editRole = $role;
                break;
            }
        }

        $title = t('admin.title.roles');
        $contentView = __DIR__ . '/../Views/system/roles.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function permissions(): void
    {
        if (!$this->hasAnyPermission(['menu.permissions', 'menu.roles'])) {
            $this->denyNoPermission('admin.deny.permissions_page');
        }
        $canPermissionSave = $this->hasAnyPermission(['permissions.assign', 'roles.manage']);

        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';
        $scope = ($_GET['scope'] ?? 'page') === 'action' ? 'action' : 'page';

        if (isset($_GET['msg']) && $_GET['msg'] === 'user_created') {
            $message = t('admin.permissions.msg.user_created');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canPermissionSave) {
                $this->denyNoPermission('admin.deny.permissions_save');
            }
            $type = $_POST['target_type'] ?? '';
            $targetId = (int)($_POST['target_id'] ?? 0);
            $permissionIds = array_map('intval', $_POST['permission_ids'] ?? []);
            $permissionIds = array_values(array_unique(array_filter($permissionIds, static fn($id) => $id > 0)));

            if (!in_array($type, ['role', 'user'], true) || $targetId <= 0) {
                $error = t('admin.permissions.err.bad_params');
            } else {
                $conn->begin_transaction();
                try {
                    $scopeIsPage = ($scope === 'page');
                    $preserveIds = [];
                    if ($type === 'role') {
                        $stmt = $conn->prepare('
                            SELECT rp.permission_id, p.permission_key
                            FROM role_permissions rp
                            INNER JOIN permissions p ON p.id = rp.permission_id
                            WHERE rp.role_id = ?
                        ');
                    } else {
                        $stmt = $conn->prepare('
                            SELECT up.permission_id, p.permission_key
                            FROM user_permissions up
                            INNER JOIN permissions p ON p.id = up.permission_id
                            WHERE up.user_id = ?
                        ');
                    }
                    $stmt->bind_param('i', $targetId);
                    $stmt->execute();
                    $existingRes = $stmt->get_result();
                    while ($existingRes && ($row = $existingRes->fetch_assoc())) {
                        $pid = (int)($row['permission_id'] ?? 0);
                        $pkey = (string)($row['permission_key'] ?? '');
                        if ($pid <= 0 || $pkey === '') {
                            continue;
                        }
                        $isPagePerm = $this->isPagePermissionKey($pkey);
                        // 本次保存 page -> 保留 action；保存 action -> 保留 page
                        if (($scopeIsPage && !$isPagePerm) || (!$scopeIsPage && $isPagePerm)) {
                            $preserveIds[] = $pid;
                        }
                    }
                    $stmt->close();

                    if ($type === 'role') {
                        $stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
                    } else {
                        $stmt = $conn->prepare('DELETE FROM user_permissions WHERE user_id = ?');
                    }
                    $stmt->bind_param('i', $targetId);
                    $stmt->execute();
                    $stmt->close();

                    $finalPermissionIds = array_values(array_unique(array_merge($permissionIds, $preserveIds)));
                    if (!empty($finalPermissionIds)) {
                        if ($type === 'role') {
                            $stmt = $conn->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                        } else {
                            $stmt = $conn->prepare('INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)');
                        }
                        foreach ($finalPermissionIds as $permissionId) {
                            $stmt->bind_param('ii', $targetId, $permissionId);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    $conn->commit();
                    $this->writeAuditLog($conn, 'permissions', 'permissions.assign', $type, $targetId, [
                        'scope' => $scope,
                        'permission_count' => count($finalPermissionIds),
                    ]);
                    $message = $type === 'role' ? t('admin.permissions.msg.role_saved') : t('admin.permissions.msg.user_saved');
                } catch (Throwable $e) {
                    $conn->rollback();
                    $tpl = t('admin.permissions.err.save_failed');
                    $error = str_contains($tpl, '%s')
                        ? sprintf($tpl, $e->getMessage())
                        : ($tpl . $e->getMessage());
                }
            }
        }

        $roles = [];
        $roleRes = $conn->query('SELECT id, role_name, description FROM roles ORDER BY id ASC');
        while ($roleRes && ($row = $roleRes->fetch_assoc())) {
            $roles[] = $row;
        }

        $users = [];
        $userRes = $conn->query("
            SELECT u.id, u.username, u.full_name, r.role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            ORDER BY u.id ASC
        ");
        while ($userRes && ($row = $userRes->fetch_assoc())) {
            $users[] = $row;
        }

        $selectedRoleId = (int)($_GET['role_id'] ?? 0);
        $selectedUserId = (int)($_GET['user_id'] ?? 0);
        if ($selectedRoleId <= 0 && !empty($roles)) {
            $selectedRoleId = (int)$roles[0]['id'];
        }
        if ($selectedUserId <= 0 && !empty($users)) {
            $selectedUserId = (int)$users[0]['id'];
        }

        $permissions = [];
        $permissionRes = $conn->query('SELECT id, permission_key, module_key, sort_order, permission_name FROM permissions ORDER BY module_key ASC, sort_order ASC, id ASC');
        while ($permissionRes && ($row = $permissionRes->fetch_assoc())) {
            $key = (string)($row['permission_key'] ?? '');
            if ($key !== '') {
                $labelKey = $this->permTranslationKey($key);
                $translated = t($labelKey, '');
                if ($translated !== '' && $translated !== $labelKey) {
                    $row['permission_name'] = $translated;
                }
            }
            $permissions[] = $row;
        }

        $rolePermissionIds = [];
        if ($selectedRoleId > 0) {
            $stmt = $conn->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
            $stmt->bind_param('i', $selectedRoleId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($r = $result->fetch_assoc()) {
                $rolePermissionIds[] = (int)$r['permission_id'];
            }
            $stmt->close();
        }

        $userPermissionIds = [];
        if ($selectedUserId > 0) {
            $stmt = $conn->prepare('SELECT permission_id FROM user_permissions WHERE user_id = ?');
            $stmt->bind_param('i', $selectedUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($r = $result->fetch_assoc()) {
                $userPermissionIds[] = (int)$r['permission_id'];
            }
            $stmt->close();
        }

        $moduleLabels = [
            'dashboard' => t('module.dashboard'),
            'calendar' => t('module.calendar'),
            'users' => t('module.users'),
            'roles' => t('module.roles'),
            'master_data' => t('module.master_data'),
            'express' => t('module.express'),
            'notifications' => t('module.notifications'),
            'system' => t('module.system'),
            'other' => t('module.other'),
        ];

        $groupedPermissions = [];
        foreach ($permissions as $permission) {
            $key = (string)($permission['permission_key'] ?? '');
            $isPage = $this->isPagePermissionKey($key);
            if ($scope === 'page' && !$isPage) {
                continue;
            }
            if ($scope === 'action' && $isPage) {
                continue;
            }
            $moduleKey = (string)($permission['module_key'] ?? 'other');
            if ($moduleKey === '') {
                $moduleKey = 'other';
            }
            $groupedPermissions[$moduleKey][] = $permission;
        }

        $title = t('admin.title.permissions');
        $contentView = __DIR__ . '/../Views/system/permissions.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function notifications(): void
    {
        if (!$this->hasAnyPermission(['menu.notifications', 'menu.roles'])) {
            $this->denyNoPermission('admin.deny.notifications_page');
        }
        $canSaveNotificationRules = $this->hasAnyPermission(['notifications.rules.save', 'notifications.manage', 'roles.manage']);

        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';
        $rulesReady = false;
        $inboxReady = false;
        $rules = [];
        $recentNotifications = [];
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $total = 0;
        $totalPages = 1;

        $rulesReady = $this->tableExists($conn, 'notification_rules');
        $inboxReady = $this->tableExists($conn, 'notifications_inbox');

        $users = [];
        $userRes = $conn->query('SELECT id, username, full_name FROM users WHERE status = 1 ORDER BY username ASC');
        while ($userRes && ($row = $userRes->fetch_assoc())) {
            $users[] = $row;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notification_rules'])) {
            if (!$canSaveNotificationRules) {
                $this->denyNoPermission('admin.deny.notifications_page');
            }
            if (!$rulesReady) {
                $error = '通知规则表未建立，请先执行 migration：011_create_notification_tables.sql';
            } else {
                $postedRules = $_POST['rules'] ?? [];
                if (is_array($postedRules)) {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare('
                            UPDATE notification_rules
                            SET enabled = ?, recipients_mode = ?, custom_user_ids = ?
                            WHERE event_key = ?
                        ');
                        foreach ($postedRules as $eventKey => $cfg) {
                            $enabled = isset($cfg['enabled']) ? 1 : 0;
                            $mode = trim((string)($cfg['recipients_mode'] ?? 'creator_and_assignees'));
                            if (!in_array($mode, ['creator', 'assignees', 'creator_and_assignees', 'custom_users', 'all_active_users'], true)) {
                                $mode = 'creator_and_assignees';
                            }
                            $customIdsRaw = $cfg['custom_user_ids'] ?? [];
                            $customIds = [];
                            if (is_array($customIdsRaw)) {
                                foreach ($customIdsRaw as $rid) {
                                    $iv = (int)$rid;
                                    if ($iv > 0) {
                                        $customIds[] = $iv;
                                    }
                                }
                            }
                            $customIds = array_values(array_unique($customIds));
                            $customCsv = empty($customIds) ? null : implode(',', $customIds);
                            $eventKeyStr = (string)$eventKey;
                            $stmt->bind_param('isss', $enabled, $mode, $customCsv, $eventKeyStr);
                            $stmt->execute();
                        }
                        $stmt->close();
                        $conn->commit();
                        $message = '通知规则已保存';
                        $this->writeAuditLog($conn, 'notifications', 'notifications.rules.save', 'notification_rules', null, [
                            'rule_count' => count($postedRules),
                        ]);
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $error = '保存失败，请稍后重试';
                    }
                }
            }
        }

        if (!$rulesReady && !$inboxReady) {
            $error = $error !== '' ? $error : '通知功能表未建立，请先执行 migration：011_create_notification_tables.sql';
        }

        if ($rulesReady) {
            $res = $conn->query('SELECT id, event_key, rule_name, enabled, recipients_mode, custom_user_ids, updated_at FROM notification_rules ORDER BY id ASC');
            while ($res && ($row = $res->fetch_assoc())) {
                $row['custom_user_ids_arr'] = [];
                $csv = trim((string)($row['custom_user_ids'] ?? ''));
                if ($csv !== '') {
                    $parts = explode(',', $csv);
                    foreach ($parts as $p) {
                        $iv = (int)trim($p);
                        if ($iv > 0) {
                            $row['custom_user_ids_arr'][] = $iv;
                        }
                    }
                }
                $rules[] = $row;
            }
        }

        if ($inboxReady) {
            $perPage = $this->resolvePerPage();
            $page = $this->resolvePage();
            $offset = ($page - 1) * $perPage;
            $total = 0;
            $countRes = $conn->query('SELECT COUNT(*) AS c FROM notifications_inbox');
            if ($countRes) {
                $countRow = $countRes->fetch_assoc();
                $total = (int)($countRow['c'] ?? 0);
            }
            $totalPages = max(1, (int)ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
                $offset = ($page - 1) * $perPage;
            }
            $res = $conn->query('
                SELECT
                    n.id, n.title, n.content, n.biz_type, n.biz_id, n.is_read, n.created_at,
                    COALESCE(NULLIF(u.full_name, \'\'), u.username) AS receiver_name,
                    COALESCE(NULLIF(cu.full_name, \'\'), cu.username) AS creator_name
                FROM notifications_inbox n
                LEFT JOIN users u ON u.id = n.user_id
                LEFT JOIN users cu ON cu.id = n.created_by
                ORDER BY n.id DESC
                LIMIT ' . $offset . ', ' . $perPage . '
            ');
            while ($res && ($row = $res->fetch_assoc())) {
                $recentNotifications[] = $row;
            }
        }

        $title = t('admin.title.notifications');
        $contentView = __DIR__ . '/../Views/system/notifications.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function liveNotifications(): void
    {
        if (!isset($_SESSION['auth_user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => '未登入'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'notifications_inbox')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'unread_total' => 0, 'items' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $userId = (int)$_SESSION['auth_user_id'];
        $items = [];
        $total = 0;
        $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications_inbox WHERE user_id = ? AND is_read = 0');
        if ($countStmt) {
            $countStmt->bind_param('i', $userId);
            $countStmt->execute();
            $row = $countStmt->get_result()->fetch_assoc();
            $total = (int)($row['c'] ?? 0);
            $countStmt->close();
        }
        $stmt = $conn->prepare('
            SELECT id, title, content, biz_type, biz_id, created_at
            FROM notifications_inbox
            WHERE user_id = ? AND is_read = 0
            ORDER BY id DESC
            LIMIT 8
        ');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $items[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'title' => (string)($row['title'] ?? ''),
                    'content' => (string)($row['content'] ?? ''),
                    'biz_type' => (string)($row['biz_type'] ?? ''),
                    'biz_id' => (int)($row['biz_id'] ?? 0),
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
            $stmt->close();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'unread_total' => $total,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function markNotificationsRead(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['auth_user_id'])) {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!$this->hasAnyPermission(['notifications.inbox.mark_read', 'notifications.manage', 'menu.notifications'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => '无权限'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'notifications_inbox')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $rawIds = $_POST['ids'] ?? [];
        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $rawId) {
                $id = (int)$rawId;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $userId = (int)$_SESSION['auth_user_id'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = 'i' . str_repeat('i', count($ids));
        $sql = "UPDATE notifications_inbox SET is_read = 1 WHERE user_id = ? AND id IN ({$placeholders})";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $bindValues = [$userId];
            foreach ($ids as $id) {
                $bindValues[] = $id;
            }
            $bindParams = [];
            $bindParams[] = &$types;
            foreach ($bindValues as $idx => $value) {
                $bindParams[] = &$bindValues[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $affected = (int)$stmt->affected_rows;
            $stmt->close();
            $this->writeAuditLog($conn, 'notifications', 'notifications.inbox.mark_read', 'notifications_inbox_batch', null, [
                'ids' => $ids,
                'affected' => $affected,
            ]);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function logs(): void
    {
        if (!$this->hasAnyPermission(['menu.logs', 'menu.roles'])) {
            $this->denyNoPermission('admin.deny.logs_page');
        }

        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';
        $tableReady = false;
        $logs = [];
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $total = 0;
        $totalPages = 1;

        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));
        $userId = (int)($_GET['user_id'] ?? 0);
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $module = trim((string)($_GET['module'] ?? 'all'));
        if (!in_array($module, ['all', 'calendar', 'system', 'auth'], true)) {
            $module = 'all';
        }

        $users = [];
        $userRes = $conn->query('SELECT id, username, full_name FROM users WHERE status = 1 ORDER BY username ASC');
        while ($userRes && ($row = $userRes->fetch_assoc())) {
            $users[] = $row;
        }

        $tableExistsRes = $conn->query("SHOW TABLES LIKE 'calendar_event_status_logs'");
        $tableReady = $tableExistsRes instanceof mysqli_result && $tableExistsRes->num_rows > 0;
        if ($tableExistsRes instanceof mysqli_result) {
            $tableExistsRes->free();
        }

        $auditTblRes = $conn->query("SHOW TABLES LIKE 'system_audit_logs'");
        $auditReady = $auditTblRes instanceof mysqli_result && $auditTblRes->num_rows > 0;
        if ($auditTblRes instanceof mysqli_result) {
            $auditTblRes->free();
        }

        if (!$tableReady && !$auditReady) {
            $error = '日志表未建立，请先执行 migration：009_create_calendar_event_status_logs.sql 与 010_create_system_audit_logs.sql';
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
                $dateFrom = '';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
                $dateTo = '';
            }

            if (($module === 'all' || $module === 'calendar') && $tableReady) {
                $where = ['1=1'];
                $bindTypes = '';
                $bindValues = [];
                if ($dateFrom !== '') {
                    $where[] = 'DATE(l.created_at) >= ?';
                    $bindTypes .= 's';
                    $bindValues[] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $where[] = 'DATE(l.created_at) <= ?';
                    $bindTypes .= 's';
                    $bindValues[] = $dateTo;
                }
                if ($userId > 0) {
                    $where[] = 'l.changed_by = ?';
                    $bindTypes .= 'i';
                    $bindValues[] = $userId;
                }
                if ($keyword !== '') {
                    $where[] = '(e.title LIKE ? OR e.note LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
                    $kw = '%' . $keyword . '%';
                    $bindTypes .= 'ssss';
                    $bindValues[] = $kw;
                    $bindValues[] = $kw;
                    $bindValues[] = $kw;
                    $bindValues[] = $kw;
                }
                $sql = '
                    SELECT
                        l.id,
                        "calendar" AS module_key,
                        "calendar.status.update" AS action_key,
                        "event" AS target_type,
                        l.event_id AS target_id,
                        l.old_progress_percent,
                        l.new_progress_percent,
                        l.old_is_completed,
                        l.new_is_completed,
                        l.created_at,
                        e.title AS event_title,
                        e.note AS event_note,
                        u.username AS changed_by_username,
                        u.full_name AS changed_by_full_name,
                        NULL AS detail_json
                    FROM calendar_event_status_logs l
                    LEFT JOIN calendar_events e ON e.id = l.event_id
                    LEFT JOIN users u ON u.id = l.changed_by
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY l.id DESC
                    LIMIT 300
                ';
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($bindTypes !== '') {
                        $bindParams = [];
                        $bindParams[] = &$bindTypes;
                        foreach ($bindValues as $idx => $val) {
                            $bindParams[] = &$bindValues[$idx];
                        }
                        call_user_func_array([$stmt, 'bind_param'], $bindParams);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($res && ($row = $res->fetch_assoc())) {
                        $logs[] = $row;
                    }
                    $stmt->close();
                }
            }

            if (($module === 'all' || $module === 'system' || $module === 'auth') && $auditReady) {
                $where = ['1=1'];
                $bindTypes = '';
                $bindValues = [];
                if ($module === 'system') {
                    $where[] = "a.module_key <> 'auth'";
                } elseif ($module === 'auth') {
                    $where[] = "a.module_key = 'auth'";
                }
                if ($dateFrom !== '') {
                    $where[] = 'DATE(a.created_at) >= ?';
                    $bindTypes .= 's';
                    $bindValues[] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $where[] = 'DATE(a.created_at) <= ?';
                    $bindTypes .= 's';
                    $bindValues[] = $dateTo;
                }
                if ($userId > 0) {
                    $where[] = 'a.actor_user_id = ?';
                    $bindTypes .= 'i';
                    $bindValues[] = $userId;
                }
                if ($keyword !== '') {
                    $where[] = '(a.action_key LIKE ? OR a.actor_name LIKE ? OR a.detail_json LIKE ?)';
                    $kw = '%' . $keyword . '%';
                    $bindTypes .= 'sss';
                    $bindValues[] = $kw;
                    $bindValues[] = $kw;
                    $bindValues[] = $kw;
                }

                $sql = '
                    SELECT
                        a.id,
                        a.module_key,
                        a.action_key,
                        a.target_type,
                        a.target_id,
                        NULL AS old_progress_percent,
                        NULL AS new_progress_percent,
                        NULL AS old_is_completed,
                        NULL AS new_is_completed,
                        a.created_at,
                        NULL AS event_title,
                        NULL AS event_note,
                        NULL AS changed_by_username,
                        a.actor_name AS changed_by_full_name,
                        a.detail_json
                    FROM system_audit_logs a
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY a.id DESC
                    LIMIT 300
                ';
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($bindTypes !== '') {
                        $bindParams = [];
                        $bindParams[] = &$bindTypes;
                        foreach ($bindValues as $idx => $val) {
                            $bindParams[] = &$bindValues[$idx];
                        }
                        call_user_func_array([$stmt, 'bind_param'], $bindParams);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($res && ($row = $res->fetch_assoc())) {
                        $logs[] = $row;
                    }
                    $stmt->close();
                }
            }
            usort($logs, static function ($a, $b) {
                return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
            });
            $total = count($logs);
            $totalPages = max(1, (int)ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $logs = array_slice($logs, ($page - 1) * $perPage, $perPage);
        }

        $title = t('admin.title.logs');
        $contentView = __DIR__ . '/../Views/system/logs.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }
}
