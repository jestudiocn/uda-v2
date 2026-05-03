<?php
/** @var bool $schemaReady */
/** @var string $activeTab */
/** @var string $title */
/** @var string $message */
/** @var string $error */
$dash = t('dispatch.view.common.dash', '—');
$orderStatusLabel = static function (string $s): string {
    $map = [
        '待入库' => ['dispatch.view.order_status.wait_inbound', '待入库'],
        '部分入库' => ['dispatch.view.order_status.partial_inbound', '部分入库'],
        '已入库' => ['dispatch.view.order_status.inbound', '已入库'],
        '待自取' => ['dispatch.view.order_status.wait_pickup', '待自取'],
        '待转发' => ['dispatch.view.order_status.wait_forward', '待转发'],
        '已出库' => ['dispatch.view.order_status.outbound', '已出库'],
        '已自取' => ['dispatch.view.order_status.picked', '已自取'],
        '已转发' => ['dispatch.view.order_status.forwarded', '已转发'],
        '已派送' => ['dispatch.view.order_status.delivered', '已派送'],
        '问题件' => ['dispatch.view.order_status.issue', '问题件'],
    ];
    if (!isset($map[$s])) {
        return $s;
    }
    return t($map[$s][0], $map[$s][1]);
};
$fwdI18n = [
    'dash' => $dash,
    'needTrack' => t('dispatch.view.forwarding.js_need_track', '请至少加入一个原始单号'),
    'confirmRevert' => t('dispatch.view.forwarding.js_confirm_revert', '确认将该订单从待转发移回已入库？'),
    'altVoucher' => t('dispatch.view.forwarding.alt_voucher', '凭证'),
    'confirmDelCust' => t('dispatch.view.forwarding.confirm_del_cust', '确认删除该转发客户？'),
    'confirmDelPkg' => t('dispatch.view.forwarding.confirm_del_pkg', '确认删除该转发合包？删除后内件订单状态将回滚为已入库。'),
];
?>

<style>
    .fwd-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
    .fwd-grid .full { grid-column:1 / -1; }
    .fwd-input, .fwd-select, .fwd-textarea { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:14px; }
    .fwd-textarea { min-height:90px; resize:vertical; }
    .fwd-input:focus, .fwd-select:focus, .fwd-textarea:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
    .fwd-selected { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .fwd-chip { background:#dbeafe; color:#1e3a8a; border-radius:999px; font-size:12px; padding:4px 10px; font-weight:700; }
    .sync-flag { display:inline-block; min-width:24px; text-align:center; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:700; }
    .sync-flag-new { background:#dbeafe; color:#1d4ed8; }
    .sync-flag-mod { background:#fee2e2; color:#b91c1c; }
    .fwd-list-tools { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:10px; }
    .fwd-list-tools .fwd-input { min-width:220px; }
    .fwd-customer-table th, .fwd-customer-table td { white-space:nowrap; vertical-align:middle; }
    .fwd-customer-table .col-code { min-width:110px; }
    .fwd-customer-table .col-wxline { min-width:170px; max-width:230px; }
    .fwd-customer-table .col-recipient { min-width:110px; max-width:150px; }
    .fwd-customer-table .col-phone { min-width:110px; max-width:140px; }
    .fwd-customer-table .col-address { min-width:320px; max-width:520px; }
    .fwd-customer-table .col-mark { width:74px; text-align:center; }
    .fwd-customer-table .col-op { min-width:120px; text-align:center; vertical-align:middle; }
    .fwd-modal-close-x {
        position:absolute; top:10px; right:12px; border:none; background:transparent;
        font-size:26px; line-height:1; color:#64748b; cursor:pointer; padding:0 4px;
    }
    .fwd-modal-close-x:hover { color:#0f172a; }
    .fwd-detail-grid { display:grid; grid-template-columns:170px 1fr; gap:10px 14px; }
    .fwd-detail-grid label { color:#475569; font-weight:600; }
    .fwd-detail-val {
        min-height:34px; padding:6px 10px; border-radius:8px;
        background:#f8fafc; border:1px solid #e2e8f0; color:#0f172a;
        display:flex; align-items:center;
    }
    .fwd-fee-em {
        font-weight:800; color:#0f766e; font-size:18px; letter-spacing:.2px;
    }
    .fwd-line-combo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .fwd-line-chip {
        display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px;
        border:1px solid #cbd5e1; background:#fff; color:#0f172a; font-size:13px;
    }
    .fwd-line-chip .k { color:#64748b; font-weight:600; }
    .fwd-line-chip .v { color:#0f172a; }
    .fwd-voucher-modal-img {
        max-width:100%; height:auto; border:1px solid #e5e7eb; border-radius:8px;
        background:#f8fafc; display:block;
    }
    .fwd-voucher-detail-img {
        width:auto; max-width:100%; height:200px; border:1px solid #e5e7eb; border-radius:8px;
        background:#f8fafc; object-fit:contain; display:block;
    }
    @media (max-width: 900px) { .fwd-grid { grid-template-columns:1fr; } }
</style>

<script>
window.__dispatchFwdI18n = <?php echo json_encode($fwdI18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars((string)($title ?? t('dispatch.view.forwarding.title_default', '派送业务 / 转发操作'))); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('dispatch.view.forwarding.subtitle', '先按 V1 的转发合包 / 固定客户 / 查询记录模式搭建，后续再按你的业务差异微调。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">
        <?php echo t('dispatch.view.forwarding.schema', '转发操作相关数据表尚未建立，请先执行 <code>database/migrations/027_dispatch_forwarding_tables.sql</code>，并执行权限种子 <code>database/seeders/011_dispatch_forwarding_permissions_seed.sql</code>。'); ?>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php if (($activeTab ?? '') === 'packages' && isset($packageFeeColumnReady) && !$packageFeeColumnReady): ?>
    <div class="card" style="border-left:4px solid #ca8a04;">
        <?php echo t('dispatch.view.forwarding.need_fee_col', '转发合包需「转发费用」字段，请先执行 <code>database/migrations/029_dispatch_forward_package_forward_fee.sql</code> 后再提交表单。'); ?>
    </div>
<?php endif; ?>
<?php if (($activeTab ?? '') === 'packages' && isset($packageVoucherColumnReady) && !$packageVoucherColumnReady): ?>
    <div class="card" style="border-left:4px solid #ca8a04;">
        <?php echo t('dispatch.view.forwarding.need_voucher_col', '转发合包需「凭证上传」字段，请先执行 <code>database/migrations/030_dispatch_forward_package_voucher_path.sql</code> 后再提交表单。'); ?>
    </div>
<?php endif; ?>

<?php if ($message !== ''): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($activeTab === 'packages'): ?>
    <div class="card">
        <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.pkg_new_title', '新增转发合包')); ?></h3>
        <form method="post" id="forwardPkgForm" class="fwd-grid" enctype="multipart/form-data">
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_pkg_no', '转发单号（必填）')); ?></label>
                <input class="fwd-input" type="text" name="package_no" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_send_at', '发出时间（必填）')); ?></label>
                <input class="fwd-input" type="datetime-local" name="send_at" id="fwd_send_at" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_fee', '转发费用（必填）')); ?></label>
                <input class="fwd-input" type="number" name="forward_fee" step="0.01" min="0" inputmode="decimal" placeholder="0.00" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_customer', '派送客户（委托客户 · 客户编号 · 微信/Line）')); ?></label>
                <select class="fwd-select" name="forward_delivery_customer_id" id="fwd_customer_select">
                    <option value=""><?php echo htmlspecialchars(t('dispatch.view.forwarding.opt_no_customer', '不选（无客户代码订单：手填下方收件信息）')); ?></option>
                    <?php foreach (($customerOptions ?? []) as $c): ?>
                        <?php
                        $optRec = trim((string)($c['opt_recipient'] ?? ''));
                        $did = (int)($c['delivery_customer_id'] ?? 0);
                        $cname = trim((string)($c['consigning_client_name'] ?? ''));
                        $code = trim((string)($c['customer_code'] ?? ''));
                        $wxl = trim((string)($c['wechat_line'] ?? ''));
                        $optLabel = ($cname !== '' ? $cname . ' ｜ ' : '') . $code . ($wxl !== '' ? ' ｜ ' . $wxl : '');
                        ?>
                        <option
                            value="<?php echo $did > 0 ? (string)$did : ''; ?>"
                            data-recipient="<?php echo htmlspecialchars($optRec, ENT_QUOTES); ?>"
                            data-phone="<?php echo htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES); ?>"
                            data-addr-th-full="<?php echo htmlspecialchars((string)($c['addr_th_full'] ?? ''), ENT_QUOTES); ?>"
                        >
                            <?php echo htmlspecialchars($optLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_receiver', '收件人（必填）')); ?></label>
                <input class="fwd-input" type="text" name="receiver_name" id="fwd_receiver_name" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_phone', '收件电话（必填）')); ?></label>
                <input class="fwd-input" type="text" name="receiver_phone" id="fwd_receiver_phone" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_voucher', '凭证上传（必填）')); ?></label>
                <input class="fwd-input" type="file" name="voucher_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
            </div>
            <div class="full">
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_address', '收件地址（必填）')); ?></label>
                <textarea class="fwd-textarea" name="receiver_address" id="fwd_receiver_address" required></textarea>
            </div>
            <div class="full">
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_scan', '扫码/输入原始单号（回车加入，支持自动去掉 @ 后缀）')); ?></label>
                <div class="muted" style="margin:0 0 6px 0;font-size:13px;">
                    <?php echo htmlspecialchars(t('dispatch.view.forwarding.scan_help', '用于绑定订单库中的面单：可多件扫码或输入后回车逐条加入。可与上方「派送客户」留空搭配，作无客户编码的问题件等转发；确认后订单状态将更新为「已转发」。')); ?>
                </div>
                <input class="fwd-input" type="text" id="fwd_scan_input" autocomplete="off" placeholder="<?php echo htmlspecialchars(t('dispatch.view.forwarding.ph_scan_example', '例如 TH123456@88')); ?>">
                <input type="hidden" name="source_tracking_nos" id="fwd_source_tracking_nos">
                <div class="muted" style="margin-top:6px;font-size:13px;"><?php echo t('dispatch.view.forwarding.must_one_track', '提交前<strong>必须</strong>至少绑定一条原始单号（上方扫码/回车加入，或勾选下方候选订单）；未添加时无法提交。'); ?></div>
                <div id="fwd_selected_list" class="fwd-selected"></div>
            </div>
            <div class="full">
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.candidates_title', '可转发订单（已匹配派送客户 · 主路线 OT · 待转发/已入库 · 未入合包）')); ?></label>
                <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:6px 0 8px 0;">
                    <div>
                        <label style="font-size:12px;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.filter_code', '客户编码筛选')); ?></label>
                        <input class="fwd-input" type="text" id="fwd_q_customer_code" value="<?php echo htmlspecialchars((string)($_GET['q_customer_code'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars(t('dispatch.view.forwarding.ph_filter_optional', '可选，模糊匹配')); ?>">
                    </div>
                    <div class="inline-actions">
                        <button type="button" id="fwd_filter_btn"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_filter', '筛选')); ?></button>
                        <a class="btn" href="/dispatch/forwarding/packages"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_reset', '重置')); ?></a>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <tr>
                            <th style="width:44px;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_pick', '选')); ?></th>
                            <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_track', '原始单号')); ?></th>
                            <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_cust_code', '客户编码')); ?></th>
                            <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_wxline_pkg', '微信 / Line')); ?></th>
                            <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_order_status', '订单状态')); ?></th>
                            <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_scan', '扫描时间')); ?></th>
                            <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_op', '操作')); ?></th>
                        </tr>
                        <?php foreach (($candidateRows ?? []) as $r): ?>
                            <?php
                            $wx = trim((string)($r['wechat_id'] ?? ''));
                            $ln = trim((string)($r['line_id'] ?? ''));
                            $wxLine = $wx === '' ? ($ln !== '' ? $ln : '') : ($ln === '' ? $wx : ($wx . ' / ' . $ln));
                            ?>
                            <tr>
                                <td><input type="checkbox" class="fwd-candidate-check" value="<?php echo htmlspecialchars((string)($r['original_tracking_no'] ?? ''), ENT_QUOTES); ?>"></td>
                                <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['original_tracking_no'] ?? '')); ?></td>
                                <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['matched_customer_code'] ?? $r['delivery_customer_code'] ?? '')); ?></td>
                                <td class="cell-tip"><?php echo html_cell_tip_content($wxLine); ?></td>
                                <td class="cell-tip"><?php echo html_cell_tip_content($orderStatusLabel((string)($r['order_status'] ?? ''))); ?></td>
                                <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['scanned_at'] ?? '')); ?></td>
                                <td>
                                    <?php if ((string)($r['order_status'] ?? '') === '待转发'): ?>
                                        <button
                                            type="button"
                                            class="btn fwd-revert-btn"
                                            data-waybill-id="<?php echo (int)($r['waybill_id'] ?? 0); ?>"
                                            style="padding:2px 8px;min-height:auto;font-size:12px;background:#64748b;color:#fff;"
                                        ><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_revert_inbound', '移回已入库')); ?></button>
                                    <?php else: ?>
                                        <span class="muted"><?php echo htmlspecialchars($dash); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="full">
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_remark', '备注')); ?></label>
                <textarea class="fwd-textarea" name="remark"></textarea>
            </div>
            <div class="full">
                <button type="submit" name="forward_create_package" <?php echo (empty($packageFeeColumnReady) || empty($packageVoucherColumnReady)) ? 'disabled' : ''; ?>><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_confirm_forward', '转发确认')); ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'customers'): ?>
    <div class="card">
        <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.cust_push_title', '手动推送添加')); ?></h3>
        <form method="post" class="fwd-grid">
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_cust_code_push', '客户代码')); ?></label>
                <input class="fwd-input" type="text" name="customer_code" placeholder="<?php echo htmlspecialchars(t('dispatch.view.forwarding.ph_push_code', '输入客户编码后推送添加')); ?>" required>
            </div>
            <div class="full">
                <button type="submit" name="push_forward_customer_by_code"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_push', '确认推送')); ?></button>
            </div>
        </form>
    </div>

    <?php if (!empty($editRow)): ?>
        <div class="card">
            <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.edit_title', '编辑转发客户（保存后清除“新/改”标记）')); ?></h3>
            <form method="post" class="fwd-grid">
                <input type="hidden" name="customer_id" value="<?php echo (int)($editRow['id'] ?? 0); ?>">
                <div>
                    <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_code_ro', '客户代码（不可改）')); ?></label>
                    <input class="fwd-input" type="text" value="<?php echo htmlspecialchars((string)($editRow['customer_code'] ?? '')); ?>" disabled>
                </div>
                <div>
                    <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_wxline_ro', '微信/Line号（不可改）')); ?></label>
                    <input class="fwd-input" type="text" value="<?php echo htmlspecialchars((string)($editRow['wechat_line'] ?? '')); ?>" disabled>
                </div>
                <div>
                    <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_recipient', '收件人')); ?></label>
                    <input class="fwd-input" type="text" name="recipient_name" value="<?php echo htmlspecialchars((string)($editRow['recipient_name'] ?? '')); ?>">
                </div>
                <div>
                    <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_phone_edit', '电话')); ?></label>
                    <input class="fwd-input" type="text" name="phone" value="<?php echo htmlspecialchars((string)($editRow['phone'] ?? '')); ?>">
                </div>
                <div class="full">
                    <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_addr_th', '完整泰文地址')); ?></label>
                    <textarea class="fwd-textarea" name="addr_th_full"><?php echo htmlspecialchars((string)($editRow['addr_th_full'] ?? ($editRow['address'] ?? ''))); ?></textarea>
                </div>
                <div>
                    <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_status', '状态')); ?></label>
                    <select class="fwd-select" name="status">
                        <option value="1" <?php echo (int)($editRow['status'] ?? 1) === 1 ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('dispatch.view.forwarding.status_on', '启用')); ?></option>
                        <option value="0" <?php echo (int)($editRow['status'] ?? 1) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('dispatch.view.forwarding.status_off', '停用')); ?></option>
                    </select>
                </div>
                <div class="full">
                    <button type="submit" name="save_forward_customer_edit"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_save', '保存修改')); ?></button>
                    <a class="btn" href="/dispatch/forwarding/customers"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_cancel', '取消')); ?></a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.list_title', '客户列表')); ?></h3>
        <form method="get" class="fwd-list-tools">
            <input class="fwd-input" type="text" name="q" value="<?php echo htmlspecialchars((string)($_GET['q'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars(t('dispatch.view.forwarding.ph_search_cust', '按客户代码/微信Line搜索')); ?>">
            <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_search', '搜索')); ?></button>
            <a class="btn" href="/dispatch/forwarding/customers"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_reset', '重置')); ?></a>
        </form>
        <div class="table-wrap">
            <table class="data-table fwd-customer-table table-valign-middle">
                <tr>
                    <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_cust_code', '客户编码')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_wxline_customer', '微信/Line号')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_recipient', '收件人')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_phone', '电话')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_addr', '完整泰文地址')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_state', '状态')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.th_op_customer', '操作')); ?></th>
                </tr>
                <?php foreach (($rows ?? []) as $r): ?>
                    <tr>
                        <td class="col-code"><?php echo htmlspecialchars((string)$r['customer_code']); ?></td>
                        <td class="col-wxline"><?php echo html_cell_tip_content((string)($r['wechat_line'] ?? '')); ?></td>
                        <td class="col-recipient"><?php echo html_cell_tip_content((string)($r['recipient_name'] ?? '')); ?></td>
                        <td class="col-phone"><?php echo html_cell_tip_content((string)($r['phone'] ?? '')); ?></td>
                        <td class="col-address"><?php echo html_cell_tip_content((string)($r['addr_th_full'] ?? ($r['address'] ?? ''))); ?></td>
                        <td class="col-mark">
                            <?php
                            $mark = trim((string)($r['sync_mark'] ?? ''));
                            if ($mark === 'new') {
                                echo '<span class="sync-flag sync-flag-new">' . htmlspecialchars(t('dispatch.view.forwarding.mark_new', '新')) . '</span>';
                            } elseif ($mark === 'modified') {
                                echo '<span class="sync-flag sync-flag-mod">' . htmlspecialchars(t('dispatch.view.forwarding.mark_mod', '改')) . '</span>';
                            } else {
                                echo '';
                            }
                            ?>
                        </td>
                        <td class="col-op">
                            <?php if (!empty($canManage)): ?>
                            <div class="dispatch-row-actions">
                                <a class="btn btn-dispatch-round btn-dispatch-round--edit" href="/dispatch/forwarding/customers?edit_id=<?php echo (int)$r['id']; ?>" title="<?php echo htmlspecialchars(t('dispatch.view.forwarding.title_edit', '编辑')); ?>">E</a>
                                <form method="post" action="/dispatch/forwarding/customers" style="display:inline;margin:0;" onsubmit="return confirm(window.__dispatchFwdI18n.confirmDelCust);">
                                    <input type="hidden" name="delete_forward_customer" value="1">
                                    <input type="hidden" name="customer_id" value="<?php echo (int)$r['id']; ?>">
                                    <button type="submit" class="btn btn-dispatch-round btn-dispatch-round--delete" title="<?php echo htmlspecialchars(t('dispatch.view.forwarding.title_delete', '删除')); ?>">D</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="muted"><?php echo htmlspecialchars($dash); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'records'): ?>
    <div class="card">
        <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.records_title', '查询记录')); ?></h3>
        <form method="get" class="fwd-grid" id="fwd_records_filter_form" style="margin-bottom:12px;">
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_q_pkg', '转发单号')); ?></label>
                <input class="fwd-input" type="text" name="q_package_no" value="<?php echo htmlspecialchars((string)($_GET['q_package_no'] ?? '')); ?>">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_q_cust', '客户代码')); ?></label>
                <input class="fwd-input" type="text" name="q_customer_code" value="<?php echo htmlspecialchars((string)($_GET['q_customer_code'] ?? '')); ?>">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_q_track', '原始单号')); ?></label>
                <input class="fwd-input" type="text" name="q_source_no" value="<?php echo htmlspecialchars((string)($_GET['q_source_no'] ?? '')); ?>">
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.label_q_batch', '入库批次')); ?></label>
                <input class="fwd-input" type="text" name="q_inbound_batch" value="<?php echo htmlspecialchars((string)($_GET['q_inbound_batch'] ?? '')); ?>">
            </div>
            <div style="display:flex;align-items:flex-end;gap:10px;">
                <button type="submit"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_query', '查询')); ?></button>
                <a class="btn" href="/dispatch/forwarding/records"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_reset', '重置')); ?></a>
            </div>
        </form>
        <script>
        (function () {
            var f = document.getElementById('fwd_records_filter_form');
            if (!f) return;
            var inp = f.querySelector('input[name="q_source_no"]');
            if (!inp) return;
            function stripScanSuffix(s) { return String(s || '').trim().replace(/@\d+$/, '').trim(); }
            f.addEventListener('submit', function () { inp.value = stripScanSuffix(inp.value); });
        })();
        </script>
        <div class="table-wrap">
            <table class="data-table">
                <tr>
                    <th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_pkg', '转发单号')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_send', '发出时间')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_fee', '转发费用')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_cust', '客户代码')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_recv', '收件人')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_phone', '电话')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_batch', '入库批次')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_items', '内件数')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_tracks', '原始单号')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_voucher', '凭证')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_detail', '详情')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_op', '操作')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_by', '录入人')); ?></th><th><?php echo htmlspecialchars(t('dispatch.view.forwarding.rec_th_at', '录入时间')); ?></th>
                </tr>
                <?php foreach (($rows ?? []) as $r): ?>
                    <?php
                    $voucherPath = trim((string)($r['voucher_path'] ?? ''));
                    $voucherUrl = $voucherPath !== '' ? '/dispatch/forwarding/voucher/view?id=' . (int)($r['id'] ?? 0) : '';
                    $detailPayload = [
                        'package_no' => (string)($r['package_no'] ?? ''),
                        'send_at' => (string)($r['send_at'] ?? ''),
                        'forward_fee' => number_format((float)($r['forward_fee'] ?? 0), 2, '.', ''),
                        'forward_customer_code' => (string)($r['forward_customer_code'] ?? ''),
                        'receiver_name' => (string)($r['receiver_name'] ?? ''),
                        'receiver_phone' => (string)($r['receiver_phone'] ?? ''),
                        'receiver_address' => (string)($r['receiver_address'] ?? ''),
                        'inbound_batches' => (string)($r['inbound_batches'] ?? ''),
                        'source_tracking_nos' => (string)($r['source_tracking_nos'] ?? ''),
                        'remark' => (string)($r['remark'] ?? ''),
                        'voucher_url' => $voucherUrl,
                    ];
                    ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['package_no'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['send_at'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(number_format((float)($r['forward_fee'] ?? 0), 2, '.', '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['forward_customer_code'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['receiver_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['receiver_phone'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['inbound_batches'] ?? '')); ?></td>
                        <td><?php echo (int)($r['item_count'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['source_tracking_nos'] ?? '')); ?></td>
                        <td>
                            <?php if ($voucherUrl !== ''): ?>
                                <button type="button" class="btn fwd-voucher-btn" data-voucher-url="<?php echo htmlspecialchars($voucherUrl, ENT_QUOTES); ?>" style="padding:4px 8px;min-height:auto;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_view', '查看')); ?></button>
                            <?php else: ?>
                                <?php echo htmlspecialchars($dash); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn fwd-detail-btn" data-detail="<?php echo htmlspecialchars(json_encode($detailPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" style="padding:4px 8px;min-height:auto;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_view', '查看')); ?></button>
                        </td>
                        <td>
                            <form method="post" action="/dispatch/forwarding/records" style="display:inline;" onsubmit="return confirm(window.__dispatchFwdI18n.confirmDelPkg);">
                                <input type="hidden" name="delete_forward_package" value="1">
                                <input type="hidden" name="package_id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                <button type="submit" class="btn" style="padding:4px 8px;min-height:auto;background:#b91c1c;color:#fff;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.btn_del_short', '删')); ?></button>
                            </form>
                        </td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['created_by_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['created_at'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
<?php endif; ?>

<div id="fwdVoucherModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:980px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" class="fwd-modal-close-x" id="fwdVoucherCloseX" aria-label="<?php echo htmlspecialchars(t('dispatch.view.forwarding.aria_close', '关闭')); ?>">×</button>
        <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.voucher_title', '凭证查看')); ?></h3>
        <div id="fwdVoucherBody"></div>
    </div>
</div>

<div id="fwdDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:900px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" class="fwd-modal-close-x" id="fwdDetailCloseX" aria-label="<?php echo htmlspecialchars(t('dispatch.view.forwarding.aria_close', '关闭')); ?>">×</button>
        <h3 style="margin:0 0 10px 0;"><?php echo htmlspecialchars(t('dispatch.view.forwarding.detail_title', '转发详情')); ?></h3>
        <div class="fwd-detail-grid">
            <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_label_info', '转发信息')); ?></label>
            <div id="fwd_d_topline" class="fwd-detail-val fwd-line-combo">
                <span class="fwd-line-chip"><span class="k"><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_k_pkg_no', '转发单号')); ?></span><span class="v" id="fwd_d_package_no"><?php echo htmlspecialchars($dash); ?></span></span>
                <span class="fwd-line-chip"><span class="k"><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_k_fee', '转发费用')); ?></span><span class="v fwd-fee-em"><span id="fwd_d_fee"><?php echo htmlspecialchars($dash); ?></span> THB</span></span>
            </div>
            <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_label_send', '发出时间')); ?></label><div id="fwd_d_send_at" class="fwd-detail-val"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_label_recv_block', '收件资料')); ?></label>
            <div id="fwd_d_line3" class="fwd-detail-val fwd-line-combo">
                <span class="fwd-line-chip"><span class="k"><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_k_cust', '客户代码')); ?></span><span class="v" id="fwd_d_customer_code"><?php echo htmlspecialchars($dash); ?></span></span>
                <span class="fwd-line-chip"><span class="k"><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_k_recv', '收件人')); ?></span><span class="v" id="fwd_d_receiver"><?php echo htmlspecialchars($dash); ?></span></span>
                <span class="fwd-line-chip"><span class="k"><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_k_phone', '收件电话')); ?></span><span class="v" id="fwd_d_phone"><?php echo htmlspecialchars($dash); ?></span></span>
            </div>
            <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_label_addr', '收件地址')); ?></label><div id="fwd_d_address" class="fwd-detail-val"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_label_tracks', '原始单号')); ?></label><div id="fwd_d_source_nos" class="fwd-detail-val"><?php echo htmlspecialchars($dash); ?></div>
            <label><?php echo htmlspecialchars(t('dispatch.view.forwarding.d_label_voucher', '凭证')); ?></label><div id="fwd_d_voucher_wrap" class="fwd-detail-val" style="align-items:flex-start;"><?php echo htmlspecialchars($dash); ?></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var sendAtInput = document.getElementById('fwd_send_at');
    if (sendAtInput && !sendAtInput.value) {
        var key = 'dispatch_forward_packages_opened_at';
        var v = sessionStorage.getItem(key);
        if (!v) {
            var d = new Date();
            var pad = function (n) { return String(n).padStart(2, '0'); };
            v = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
            sessionStorage.setItem(key, v);
        }
        sendAtInput.value = v;
    }
    var customerSelect = document.getElementById('fwd_customer_select');
    var nameInput = document.getElementById('fwd_receiver_name');
    var phoneInput = document.getElementById('fwd_receiver_phone');
    var addrInput = document.getElementById('fwd_receiver_address');
    if (customerSelect) {
        customerSelect.addEventListener('change', function () {
            var op = this.options[this.selectedIndex];
            if (!op) return;
            if (!op.value) return;
            var rec = op.getAttribute('data-recipient') || '';
            if (nameInput) nameInput.value = rec;
            if (phoneInput) phoneInput.value = op.getAttribute('data-phone') || '';
            if (addrInput) addrInput.value = op.getAttribute('data-addr-th-full') || '';
        });
    }

    var scanInput = document.getElementById('fwd_scan_input');
    var hiddenInput = document.getElementById('fwd_source_tracking_nos');
    var selectedList = document.getElementById('fwd_selected_list');
    var checks = document.querySelectorAll('.fwd-candidate-check');
    var form = document.getElementById('forwardPkgForm');
    var selectedMap = {};
    var filterInput = document.getElementById('fwd_q_customer_code');
    var filterBtn = document.getElementById('fwd_filter_btn');

    function submitPost(path, fields) {
        var f = document.createElement('form');
        f.method = 'post';
        f.action = path;
        Object.keys(fields || {}).forEach(function (k) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = String(fields[k] == null ? '' : fields[k]);
            f.appendChild(input);
        });
        document.body.appendChild(f);
        f.submit();
    }

    function sync() {
        if (hiddenInput) hiddenInput.value = Object.keys(selectedMap).join(',');
        if (!selectedList) return;
        selectedList.innerHTML = '';
        Object.keys(selectedMap).forEach(function (no) {
            var span = document.createElement('span');
            span.className = 'fwd-chip';
            span.textContent = no;
            selectedList.appendChild(span);
        });
    }

    function addNo(v) {
        var val = String(v || '').toUpperCase().trim().replace(/@.*$/, '');
        if (!val) return;
        selectedMap[val] = true;
        checks.forEach(function (el) {
            if (String(el.value || '').toUpperCase() === val) el.checked = true;
        });
        sync();
    }

    if (scanInput) {
        scanInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addNo(scanInput.value);
                scanInput.value = '';
            }
        });
    }

    checks.forEach(function (el) {
        el.addEventListener('change', function () {
            var val = String(el.value || '').toUpperCase();
            if (el.checked) selectedMap[val] = true;
            else delete selectedMap[val];
            sync();
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            sync();
            if (!hiddenInput || hiddenInput.value.trim() === '') {
                e.preventDefault();
                alert(window.__dispatchFwdI18n.needTrack);
            }
        });
    }

    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            var q = String((filterInput && filterInput.value) || '').trim();
            var url = '/dispatch/forwarding/packages';
            if (q !== '') {
                url += '?q_customer_code=' + encodeURIComponent(q);
            }
            window.location.href = url;
        });
    }

    document.querySelectorAll('.fwd-revert-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var waybillId = parseInt(String(btn.getAttribute('data-waybill-id') || '0'), 10) || 0;
            if (waybillId <= 0) return;
            if (!window.confirm(window.__dispatchFwdI18n.confirmRevert)) return;
            submitPost('/dispatch/forwarding/packages', {
                forward_revert_to_inbound: '1',
                waybill_id: String(waybillId)
            });
        });
    });

    function txt(v) {
        var I2 = window.__dispatchFwdI18n || {};
        var d = (I2.dash !== undefined && I2.dash !== null && String(I2.dash) !== '') ? String(I2.dash) : '\u2014';
        var s = String(v || '').trim();
        return s === '' ? d : s;
    }
    var voucherModal = document.getElementById('fwdVoucherModal');
    var voucherBody = document.getElementById('fwdVoucherBody');
    var voucherClose = document.getElementById('fwdVoucherClose');
    function closeVoucherModal() {
        if (!voucherModal) return;
        voucherModal.style.display = 'none';
        if (voucherBody) voucherBody.innerHTML = '';
    }
    document.querySelectorAll('.fwd-voucher-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = String(btn.getAttribute('data-voucher-url') || '').trim();
            if (!url || !voucherModal || !voucherBody) return;
            voucherBody.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="' + String((window.__dispatchFwdI18n && window.__dispatchFwdI18n.altVoucher) || '').replace(/"/g, '&quot;') + '" class="fwd-voucher-modal-img">';
            voucherModal.style.display = 'flex';
        });
    });
    if (voucherClose) voucherClose.addEventListener('click', closeVoucherModal);
    var voucherCloseX = document.getElementById('fwdVoucherCloseX');
    if (voucherCloseX) voucherCloseX.addEventListener('click', closeVoucherModal);
    if (voucherModal) voucherModal.addEventListener('click', function (e) { if (e.target === voucherModal) closeVoucherModal(); });

    var detailModal = document.getElementById('fwdDetailModal');
    var detailClose = document.getElementById('fwdDetailClose');
    function closeDetailModal() {
        if (detailModal) detailModal.style.display = 'none';
    }
    document.querySelectorAll('.fwd-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!detailModal) return;
            var payload = {};
            try { payload = JSON.parse(btn.getAttribute('data-detail') || '{}'); } catch (e) {}
            document.getElementById('fwd_d_package_no').textContent = txt(payload.package_no);
            document.getElementById('fwd_d_send_at').textContent = txt(payload.send_at);
            document.getElementById('fwd_d_fee').textContent = txt(payload.forward_fee);
            document.getElementById('fwd_d_customer_code').textContent = txt(payload.forward_customer_code);
            document.getElementById('fwd_d_receiver').textContent = txt(payload.receiver_name);
            document.getElementById('fwd_d_phone').textContent = txt(payload.receiver_phone);
            document.getElementById('fwd_d_address').textContent = txt(payload.receiver_address);
            document.getElementById('fwd_d_source_nos').textContent = txt(payload.source_tracking_nos);
            var voucherWrap = document.getElementById('fwd_d_voucher_wrap');
            if (voucherWrap) {
                var voucherUrl = String(payload.voucher_url || '').trim();
                var altV = (window.__dispatchFwdI18n && window.__dispatchFwdI18n.altVoucher) ? String(window.__dispatchFwdI18n.altVoucher).replace(/"/g, '&quot;') : '';
                var dsh = (window.__dispatchFwdI18n && window.__dispatchFwdI18n.dash) ? String(window.__dispatchFwdI18n.dash) : '\u2014';
                voucherWrap.innerHTML = voucherUrl === '' ? dsh : ('<img src="' + voucherUrl.replace(/"/g, '&quot;') + '" alt="' + altV + '" class="fwd-voucher-detail-img">');
            }
            detailModal.style.display = 'flex';
        });
    });
    if (detailClose) detailClose.addEventListener('click', closeDetailModal);
    var detailCloseX = document.getElementById('fwdDetailCloseX');
    if (detailCloseX) detailCloseX.addEventListener('click', closeDetailModal);
    if (detailModal) detailModal.addEventListener('click', function (e) { if (e.target === detailModal) closeDetailModal(); });
});
</script>
