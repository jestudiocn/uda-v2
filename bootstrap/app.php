<?php

require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$supportedLocales = ['zh-CN', 'th-TH'];
if (!isset($_SESSION['app_locale']) || !in_array((string)$_SESSION['app_locale'], $supportedLocales, true)) {
    $_SESSION['app_locale'] = 'zh-CN';
}
$appLocale = (string)$_SESSION['app_locale'];
$langFile = __DIR__ . '/../lang/' . $appLocale . '.php';
$translations = file_exists($langFile) ? (array)require $langFile : [];
if (!function_exists('t')) {
    function t(string $key, ?string $fallback = null): string
    {
        global $translations;
        if (isset($translations[$key])) {
            return (string)$translations[$key];
        }
        return $fallback ?? $key;
    }
}

if (!function_exists('withLangUrl')) {
    /**
     * 兼容旧调用点：语言仅存在 session / 用户表，不在 URL 携带 lang。
     */
    function withLangUrl(string $pathWithQuery): string
    {
        return $pathWithQuery;
    }
}

if (!function_exists('locale_redirect_current_uri')) {
    /**
     * 供语言切换表单回跳：同一路径与 query，并移除遗留的 lang 参数。
     */
    function locale_redirect_current_uri(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri);
        $path = (string)($parts['path'] ?? '/');
        if ($path === '' || $path[0] !== '/') {
            $path = '/';
        }
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            unset($query['lang']);
        }
        $out = $path;
        if ($query !== []) {
            $out .= '?' . http_build_query($query);
        }
        if (strlen($out) > 2000) {
            return $path;
        }
        return $out;
    }
}

$routes = require __DIR__ . '/../routes/web.php';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '' || $uri === false) {
    $uri = '/';
}

if (!isset($routes[$uri])) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$publicRoutes = [
    '/login',
    '/login.php',
    '/locale',
    '/dispatch/arrival-label-pdf',
    '/dispatch/arrival-label-html',
    '/dispatch/driver/run',
    '/dispatch/driver/pod-upload',
];
$forceRoutes = ['/force-profile', '/force-profile.php', '/logout'];
$isLoggedIn = isset($_SESSION['auth_user_id']);
$mustChangePassword = (int)($_SESSION['must_change_password'] ?? 0) === 1;

if (!$isLoggedIn && !in_array($uri, $publicRoutes, true)) {
    header('Location: /login');
    exit;
}

if ($isLoggedIn && $mustChangePassword && !in_array($uri, $forceRoutes, true)) {
    header('Location: /force-profile');
    exit;
}

if (!function_exists('deny403')) {
    function deny403(string $message = '403 Forbidden'): void
    {
        http_response_code(403);
        echo $message;
        exit;
    }
}

if (!function_exists('hasPermissionKey')) {
    function hasPermissionKey(string $key): bool
    {
        $roleName = (string)($_SESSION['auth_role_name'] ?? '');
        if ($roleName === 'super_admin') {
            return true;
        }
        $keys = $_SESSION['auth_permission_keys'] ?? [];
        if (!is_array($keys)) {
            return false;
        }
        return in_array($key, $keys, true);
    }
}

if (!function_exists('hasAnyPermissionKey')) {
    function hasAnyPermissionKey(array $keys): bool
    {
        foreach ($keys as $key) {
            if (hasPermissionKey((string)$key)) {
                return true;
            }
        }
        return false;
    }
}

if ($isLoggedIn) {
    $conn = require __DIR__ . '/../config/database.php';
    $userId = (int)$_SESSION['auth_user_id'];

    $roleStmt = $conn->prepare('
        SELECT r.id, r.role_name, u.dispatch_consigning_client_id AS dispatch_bound_cc_id
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $roleRow = null;
    $dispatchBoundCcId = 0;
    if ($roleStmt) {
        $roleStmt->bind_param('i', $userId);
        $roleStmt->execute();
        $roleRow = $roleStmt->get_result()->fetch_assoc();
        $roleStmt->close();
        if (is_array($roleRow)) {
            $dispatchBoundCcId = (int)($roleRow['dispatch_bound_cc_id'] ?? 0);
        }
    }
    if (!is_array($roleRow)) {
        $roleStmt = $conn->prepare('
            SELECT r.id, r.role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
            LIMIT 1
        ');
        if ($roleStmt) {
            $roleStmt->bind_param('i', $userId);
            $roleStmt->execute();
            $roleRow = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();
        }
        $dispatchBoundCcId = 0;
    }
    $_SESSION['auth_dispatch_consigning_client_id'] = $dispatchBoundCcId;

    $_SESSION['auth_role_name'] = (string)($roleRow['role_name'] ?? '');

    $permissionKeys = [];
    if ($_SESSION['auth_role_name'] !== 'super_admin') {
        $permStmt = $conn->prepare('
            SELECT DISTINCT p.permission_key
            FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            INNER JOIN users u ON u.role_id = rp.role_id
            WHERE u.id = ?
            UNION
            SELECT DISTINCT p.permission_key
            FROM permissions p
            INNER JOIN user_permissions up ON up.permission_id = p.id
            WHERE up.user_id = ?
        ');
        $permStmt->bind_param('ii', $userId, $userId);
        $permStmt->execute();
        $permRes = $permStmt->get_result();
        while ($permRes && ($row = $permRes->fetch_assoc())) {
            $permissionKeys[] = (string)$row['permission_key'];
        }
        $permStmt->close();
    }
    $_SESSION['auth_permission_keys'] = $permissionKeys;

    $routeRequiredPermission = [
        '/' => ['menu.dashboard'],
        '/pending-tasks' => ['menu.calendar', 'menu.finance', 'menu.dispatch', 'menu.dashboard'],
        '/finance/transactions/create' => ['menu.finance', 'menu.dashboard'],
        '/finance/transactions/list' => ['menu.finance', 'menu.dashboard'],
        '/finance/transactions/edit' => ['menu.finance', 'menu.dashboard'],
        '/finance/payables/create' => ['menu.finance', 'menu.dashboard'],
        '/finance/payables/list' => ['menu.finance', 'menu.dashboard'],
        '/finance/payables/settle' => ['menu.finance', 'menu.dashboard'],
        '/finance/receivables/create' => ['menu.finance', 'menu.dashboard'],
        '/finance/receivables/list' => ['menu.finance', 'menu.dashboard'],
        '/finance/receivables/settle' => ['menu.finance', 'menu.dashboard'],
        '/finance/voucher/view' => ['menu.finance', 'menu.dashboard'],
        '/finance/accounts' => ['menu.finance', 'menu.dashboard'],
        '/finance/categories' => ['menu.finance', 'menu.dashboard'],
        '/finance/parties' => ['menu.finance', 'menu.dashboard'],
        '/finance/reports/overview' => ['menu.finance', 'menu.dashboard'],
        '/finance/reports/detail' => ['menu.finance', 'menu.dashboard'],
        '/finance/reports/export' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/customers' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/billing-schemes' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/charges/create' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/charges/options' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/charges/list' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/invoices/list' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/invoices/view' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/invoices/export-unpaid' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/invoices/print-unpaid' => ['menu.finance', 'menu.dashboard'],
        '/finance/ar/ledger' => ['menu.finance', 'menu.dashboard'],
        '/dispatch' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/order-import' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/package-ops' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/forwarding/packages' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/forwarding/customers' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/forwarding/records' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/qz/certificate' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/qz/sign' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/consigning-clients' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/delivery-customers' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/waybills' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/ops/delivery-list' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/ops/binding-list' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/ops/create-delivery' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/ops/delivery-docs' => ['menu.dispatch', 'menu.dashboard'],
        '/dispatch/accounting/list' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/issues/list' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/issues/create' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/issues/handle-methods' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/issues/locations' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/issues/reasons' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/express/query' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/express/receive' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/express/forward-packages' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/express/forward-query' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/express/forward-voucher/view' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/batches/list' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/batches/create' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/batches/waybill-check' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/batches/edit' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/warehouse/bundles' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/warehouse/create-bundle' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/warehouse/batch-view' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/warehouse/batch-edit' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/warehouse/batch-export' => ['menu.dispatch', 'menu.dashboard'],
        '/uda/warehouse/import-template' => ['menu.dispatch', 'menu.dashboard'],
        '/warehouse' => ['menu.dispatch', 'menu.dashboard'],
        '/calendar/create' => ['menu.calendar', 'menu.dashboard'],
        '/calendar/events' => ['menu.calendar', 'menu.dashboard'],
        '/calendar/event-status' => ['menu.calendar', 'menu.dashboard'],
        '/calendar/event-status-logs' => ['menu.calendar', 'menu.dashboard'],
        '/system/users' => ['menu.users'],
        '/system/roles' => ['menu.roles'],
        '/system/permissions' => ['menu.permissions', 'menu.roles'], // 兼容旧权限
        '/system/notifications' => ['menu.notifications', 'menu.roles'], // 兼容旧权限
        '/system/logs' => ['menu.logs', 'menu.roles'], // 兼容旧权限
    ];

    if (isset($routeRequiredPermission[$uri])) {
        $required = (array)$routeRequiredPermission[$uri];
        if (!hasAnyPermissionKey($required)) {
            if ($uri === '/') {
                $fallbackRoutePermissions = [
                    '/finance/payables/list' => ['menu.finance', 'menu.dashboard'],
                    '/finance/receivables/list' => ['menu.finance', 'menu.dashboard'],
                    '/finance/transactions/list' => ['menu.finance', 'menu.dashboard'],
                    '/finance/ar/invoices/list' => ['menu.finance', 'menu.dashboard'],
                    '/finance/ar/charges/list' => ['menu.finance', 'menu.dashboard'],
                    '/finance/ar/billing-schemes' => ['menu.finance', 'menu.dashboard'],
                    '/dispatch' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/order-import' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/package-ops' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/forwarding/packages' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/waybills' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/ops/delivery-list' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/ops/binding-list' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/ops/create-delivery' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/ops/delivery-docs' => ['menu.dispatch', 'menu.dashboard'],
                    '/dispatch/accounting/list' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/issues/list' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/issues/create' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/issues/handle-methods' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/issues/locations' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/issues/reasons' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/express/query' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/express/receive' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/express/forward-packages' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/express/forward-query' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/express/forward-voucher/view' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/batches/list' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/batches/create' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/batches/waybill-check' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/batches/edit' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/warehouse/bundles' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/warehouse/create-bundle' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/warehouse/batch-view' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/warehouse/batch-edit' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/warehouse/batch-export' => ['menu.dispatch', 'menu.dashboard'],
                    '/uda/warehouse/import-template' => ['menu.dispatch', 'menu.dashboard'],
                    '/warehouse' => ['menu.dispatch', 'menu.dashboard'],
                    '/pending-tasks' => ['menu.calendar', 'menu.finance', 'menu.dispatch', 'menu.dashboard'],
                    '/calendar/create' => ['menu.calendar', 'menu.dashboard'],
                    '/calendar/events' => ['menu.calendar', 'menu.dashboard'],
                    '/system/users' => ['menu.users'],
                    '/system/roles' => ['menu.roles'],
                    '/system/permissions' => ['menu.permissions', 'menu.roles'],
                    '/system/notifications' => ['menu.notifications', 'menu.roles'],
                    '/system/logs' => ['menu.logs', 'menu.roles'],
                ];
                foreach ($fallbackRoutePermissions as $path => $permKeys) {
                    if (hasAnyPermissionKey($permKeys)) {
                        header('Location: ' . $path);
                        exit;
                    }
                }
            }
            deny403('403 无权限访问该页面');
        }
    }
}

[$controllerClass, $method] = $routes[$uri];
$controller = new $controllerClass();
$controller->$method();
