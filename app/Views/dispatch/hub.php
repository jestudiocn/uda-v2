<?php
/** @var bool $schemaReady */
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务</h2>
    <div class="muted">委托客户、派送客户（收件人）与面单列表。货件经导入或 API 写入后，按「派送客户编号 + 委托客户」匹配主数据并生成订单行（一原始面单一行）。</div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">
        派送资料表尚未建立，请在数据库执行 <code>database/migrations/021_dispatch_core_tables.sql</code>，并执行权限种子 <code>database/seeders/008_dispatch_permissions_seed.sql</code> 后在角色中勾选「派送业务菜单」及相关权限。
    </div>
<?php else: ?>
    <div class="card">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
            <?php if (function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.consigning_clients.view', 'dispatch.manage'])): ?>
                <a class="btn" href="/dispatch/consigning-clients" style="justify-content:center;">委托客户</a>
            <?php endif; ?>
            <?php if (function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.delivery_customers.view', 'dispatch.manage'])): ?>
                <a class="btn" href="/dispatch/delivery-customers" style="justify-content:center;">派送客户</a>
            <?php endif; ?>
            <?php if (function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.waybills.view', 'dispatch.waybills.import', 'dispatch.manage'])): ?>
                <a class="btn" href="/dispatch" style="justify-content:center;">订单查询</a>
            <?php endif; ?>
            <?php if (function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.waybills.import', 'dispatch.manage'])): ?>
                <a class="btn" href="/dispatch/order-import" style="justify-content:center;">订单导入</a>
            <?php endif; ?>
            <?php if (function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.waybills.edit', 'dispatch.manage'])): ?>
                <a class="btn" href="/dispatch/package-ops" style="justify-content:center;">货件操作</a>
            <?php endif; ?>
            <?php if (function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.forwarding.view', 'dispatch.forwarding.package.create', 'dispatch.forwarding.customer.manage', 'dispatch.manage'])): ?>
                <a class="btn" href="/dispatch/forwarding/packages" style="justify-content:center;">转发操作</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
