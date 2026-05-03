<?php

/**
 * 与侧栏一致的一～三级菜单权限树（叶子为 permissions 表中的 menu.nav.*）。
 * key=null 表示仅分组，不落库；勾选由前端联动其下叶子复选框。
 */
class MenuPermissionCatalog
{
    /**
     * @return list<array{key:?string,label:?string,children?:list}>
     */
    public static function tree(): array
    {
        return [
            [
                'key' => 'menu.dashboard',
                'label' => null,
                'children' => [],
            ],
            [
                'key' => 'menu.calendar',
                'label' => null,
                'children' => [
                    ['key' => 'menu.nav.calendar.create', 'label' => null, 'children' => []],
                    ['key' => 'menu.nav.calendar.events', 'label' => null, 'children' => []],
                ],
            ],
            [
                'key' => 'menu.dispatch',
                'label' => null,
                'children' => [
                    [
                        'key' => null,
                        'label' => 'nav.dispatch.root',
                        'children' => [
                            ['key' => 'menu.nav.dispatch.orders', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.dispatch.order_import', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.dispatch.package_ops', 'label' => null, 'children' => []],
                            [
                                'key' => null,
                                'label' => 'nav.dispatch.forwarding',
                                'children' => [
                                    ['key' => 'menu.nav.dispatch.forwarding.packages', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.forwarding.customers', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.forwarding.records', 'label' => null, 'children' => []],
                                ],
                            ],
                            ['key' => 'menu.nav.dispatch.consigning_clients', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.dispatch.delivery_customers', 'label' => null, 'children' => []],
                            [
                                'key' => null,
                                'label' => 'nav.dispatch.ops',
                                'children' => [
                                    ['key' => 'menu.nav.dispatch.ops.delivery_list', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.ops.binding_list', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.ops.create_delivery', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.ops.preliminary_docs', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.ops.formal_docs', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.ops.pick_sheets', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.dispatch.ops.driver', 'label' => null, 'children' => []],
                                ],
                            ],
                            [
                                'key' => null,
                                'label' => 'nav.dispatch.accounting',
                                'children' => [
                                    ['key' => 'menu.nav.dispatch.accounting.list', 'label' => null, 'children' => []],
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => null,
                        'label' => 'nav.uda.root',
                        'children' => [
                            [
                                'key' => null,
                                'label' => 'nav.uda.issues',
                                'children' => [
                                    ['key' => 'menu.nav.uda.issues.list', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.issues.create', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.issues.locations', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.issues.reasons', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.issues.handle_methods', 'label' => null, 'children' => []],
                                ],
                            ],
                            [
                                'key' => null,
                                'label' => 'nav.uda.express',
                                'children' => [
                                    ['key' => 'menu.nav.uda.express.query', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.express.receive', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.express.forward_packages', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.express.forward_query', 'label' => null, 'children' => []],
                                ],
                            ],
                            [
                                'key' => null,
                                'label' => 'nav.uda.warehouse_ops',
                                'children' => [
                                    ['key' => 'menu.nav.uda.batches.list', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.batches.create', 'label' => null, 'children' => []],
                                ],
                            ],
                            [
                                'key' => null,
                                'label' => 'nav.uda.batch_ops',
                                'children' => [
                                    ['key' => 'menu.nav.uda.warehouse.bundles', 'label' => null, 'children' => []],
                                    ['key' => 'menu.nav.uda.warehouse.create_bundle', 'label' => null, 'children' => []],
                                ],
                            ],
                        ],
                    ],
                    ['key' => 'menu.nav.warehouse.root', 'label' => 'nav.warehouse', 'children' => []],
                ],
            ],
            [
                'key' => 'menu.finance',
                'label' => null,
                'children' => [
                    [
                        'key' => null,
                        'label' => 'nav.finance.records',
                        'children' => [
                            ['key' => 'menu.nav.finance.transactions.create', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.transactions.list', 'label' => null, 'children' => []],
                        ],
                    ],
                    [
                        'key' => null,
                        'label' => 'nav.finance.payables',
                        'children' => [
                            ['key' => 'menu.nav.finance.payables.create', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.payables.list', 'label' => null, 'children' => []],
                        ],
                    ],
                    [
                        'key' => null,
                        'label' => 'nav.finance.receivables',
                        'children' => [
                            ['key' => 'menu.nav.finance.receivables.create', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.receivables.list', 'label' => null, 'children' => []],
                        ],
                    ],
                    ['key' => 'menu.nav.finance.reports', 'label' => 'nav.finance.reports', 'children' => []],
                    [
                        'key' => null,
                        'label' => 'nav.finance.ar',
                        'children' => [
                            ['key' => 'menu.nav.finance.ar.customers', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.ar.billing_schemes', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.ar.charges.create', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.ar.charges.list', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.ar.invoices', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.ar.ledger', 'label' => null, 'children' => []],
                        ],
                    ],
                    [
                        'key' => null,
                        'label' => 'nav.finance.maintenance',
                        'children' => [
                            ['key' => 'menu.nav.finance.accounts', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.categories', 'label' => null, 'children' => []],
                            ['key' => 'menu.nav.finance.parties', 'label' => null, 'children' => []],
                        ],
                    ],
                ],
            ],
            [
                'key' => null,
                'label' => 'nav.system',
                'children' => [
                    ['key' => 'menu.users', 'label' => null, 'children' => []],
                    ['key' => 'menu.roles', 'label' => null, 'children' => []],
                    [
                        'key' => null,
                        'label' => 'nav.system.permissions',
                        'children' => [
                            ['key' => 'menu.nav.system.permissions_page', 'label' => 'nav.system.page_permissions', 'children' => []],
                            ['key' => 'menu.nav.system.permissions_action', 'label' => 'nav.system.action_permissions', 'children' => []],
                        ],
                    ],
                    ['key' => 'menu.notifications', 'label' => null, 'children' => []],
                    ['key' => 'menu.logs', 'label' => null, 'children' => []],
                ],
            ],
        ];
    }

    /**
     * @param array<string, array{id:int, permission_name:string}> $keyMeta
     * @return list<array<string,mixed>>
     */
    public static function enrichTreeWithIds(array $tree, array $keyMeta): array
    {
        $out = [];
        foreach ($tree as $node) {
            $key = array_key_exists('key', $node) ? $node['key'] : null;
            $enriched = $node;
            if ($key !== null && $key !== '') {
                $meta = $keyMeta[$key] ?? null;
                $enriched['perm_id'] = is_array($meta) ? (int)($meta['id'] ?? 0) : null;
                if (is_array($meta) && !empty($meta['permission_name'])) {
                    $enriched['display_name'] = (string)$meta['permission_name'];
                } else {
                    $enriched['display_name'] = $key;
                }
            } else {
                $enriched['perm_id'] = null;
                $enriched['display_name'] = null;
            }
            $children = $node['children'] ?? [];
            $enriched['children'] = self::enrichTreeWithIds($children, $keyMeta);
            $out[] = $enriched;
        }
        return $out;
    }

    /**
     * 派送业务一级折叠块内：用于侧栏「是否展开派送子功能」的任一细菜单键。
     *
     * @return list<string>
     */
    public static function dispatchHubMenuNavKeys(): array
    {
        return [
            'menu.nav.dispatch.orders',
            'menu.nav.dispatch.order_import',
            'menu.nav.dispatch.package_ops',
            'menu.nav.dispatch.forwarding.packages',
            'menu.nav.dispatch.forwarding.customers',
            'menu.nav.dispatch.forwarding.records',
            'menu.nav.dispatch.consigning_clients',
            'menu.nav.dispatch.delivery_customers',
            'menu.nav.dispatch.ops.delivery_list',
            'menu.nav.dispatch.ops.binding_list',
            'menu.nav.dispatch.ops.create_delivery',
            'menu.nav.dispatch.ops.preliminary_docs',
            'menu.nav.dispatch.ops.formal_docs',
            'menu.nav.dispatch.ops.pick_sheets',
            'menu.nav.dispatch.ops.driver',
            'menu.nav.dispatch.accounting.list',
        ];
    }

    /**
     * @return list<string>
     */
    public static function udaMenuNavKeys(): array
    {
        return [
            'menu.nav.uda.issues.list',
            'menu.nav.uda.issues.create',
            'menu.nav.uda.issues.locations',
            'menu.nav.uda.issues.reasons',
            'menu.nav.uda.issues.handle_methods',
            'menu.nav.uda.express.query',
            'menu.nav.uda.express.receive',
            'menu.nav.uda.express.forward_packages',
            'menu.nav.uda.express.forward_query',
            'menu.nav.uda.batches.list',
            'menu.nav.uda.batches.create',
            'menu.nav.uda.warehouse.bundles',
            'menu.nav.uda.warehouse.create_bundle',
        ];
    }
}
