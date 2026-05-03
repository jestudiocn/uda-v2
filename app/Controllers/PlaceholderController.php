<?php

class PlaceholderController
{
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

    private function denyNoPermission(string $message = '无权限访问'): void
    {
        http_response_code(403);
        echo $message;
        exit;
    }

    public function page(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        require_once __DIR__ . '/../Config/RouteMenuNavMap.php';
        $keys = array_values(array_unique(array_merge(
            ['menu.dispatch', 'menu.dashboard'],
            RouteMenuNavMap::menuNavKeysForUri($path)
        )));
        if (!$this->hasAnyPermission($keys)) {
            $this->denyNoPermission('无权限访问页面');
        }
        $map = [
            '/dispatch/ops/delivery-list' => '派送业务 / 派送操作 / 派送列表',
            '/dispatch/ops/binding-list' => '派送业务 / 派送操作 / 绑带列表',
            '/dispatch/ops/create-delivery' => '派送业务 / 派送操作 / 分配派送单',
            '/dispatch/ops/delivery-docs' => '派送业务 / 派送操作 / 初步派送单列表',
            '/dispatch/accounting/list' => '派送业务 / 账务处理 / 账务列表',
            '/uda/issues/list' => 'UDA快件 / 问题订单 / 问题订单列表',
            '/uda/issues/create' => 'UDA快件 / 问题订单 / 问题订单录入',
            '/uda/express/query' => 'UDA快件 / 快件收发 / 快件查询',
            '/uda/express/receive' => 'UDA快件 / 快件收发 / 收件录入',
            '/uda/express/forward-packages' => 'UDA快件 / 快件收发 / 转发合包',
            '/uda/warehouse/bundles' => 'UDA快件 / 库内操作 / 集包列表',
            '/uda/warehouse/create-bundle' => 'UDA快件 / 库内操作 / 集包录入',
            '/warehouse' => '仓储管理',
        ];
        $title = $map[$path] ?? '功能占位页';
        $subtitle = '该页面已预留菜单入口，后续可按业务流程补充具体功能。';
        $contentView = __DIR__ . '/../Views/common/placeholder.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }
}
