<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
/** @var array $queueRows */
/** @var array $recipientRows */
/** @var array $savedRecipientOptions */
?>
<style>
    .fwd-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
    .fwd-grid .full { grid-column:1 / -1; }
    .fwd-input, .fwd-select, .fwd-textarea { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:14px; }
    .fwd-textarea { min-height:90px; resize:vertical; }
    .fwd-input:focus, .fwd-select:focus, .fwd-textarea:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
    .fwd-modal-close-x {
        position:absolute; top:10px; right:12px; border:none; background:transparent;
        font-size:26px; line-height:1; color:#64748b; cursor:pointer; padding:0 4px;
    }
    .fwd-modal-close-x:hover { color:#0f172a; }
    @media (max-width: 900px) { .fwd-grid { grid-template-columns:1fr; } }
</style>

<div class="card">
    <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.title', 'UDA快件 / 快件收发 / 转发合包')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.subtitle', '版面与操作比照「派送业务 / 转发操作 / 转发合包」；待合包数据由「快件查询」手动推送；派送客户下拉取自常用收件人。下方列表可勾选要纳入本单的快件；也可不勾选任何项直接提交（仅保存合包，不含快件明细）。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;">
        <?php echo t('uda.page.express_forward_packages.schema_block', 'UDA 转发合包相关表未就绪，请先执行：<code>database/migrations/034_uda_express_forward_packages.sql</code>（若尚未执行）、<code>database/migrations/035_uda_saved_recipients_and_forward_packages.sql</code>'); ?>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php if (($message ?? '') !== ''): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars((string)$message); ?></div>
<?php endif; ?>
<?php if (($error ?? '') !== ''): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars((string)$error); ?></div>
<?php endif; ?>

<form method="post" id="udaForwardPkgForm" enctype="multipart/form-data">
<div class="card">
    <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.section_new', '新增转发合包')); ?></h3>
    <div class="fwd-grid">
        <input type="hidden" name="post_action" value="uda_forward_create_package">
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_pkg_no', '转发单号（必填）')); ?></label>
            <input class="fwd-input" type="text" name="package_no" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_send_at', '发出时间（必填）')); ?></label>
            <input class="fwd-input" type="datetime-local" name="send_at" id="uda_fwd_send_at" step="1" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_fee', '转发费用（必填）')); ?></label>
            <input class="fwd-input" type="number" name="forward_fee" step="0.01" min="0" inputmode="decimal" placeholder="0.00" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_saved_recipient', '派送客户（常用收件人）')); ?></label>
            <select class="fwd-select" name="saved_recipient_id" id="uda_fwd_recipient_select">
                <option value=""><?php echo htmlspecialchars(t('uda.page.express_forward_packages.opt_no_recipient', '不选（手填下方收件信息）')); ?></option>
                <?php foreach (($savedRecipientOptions ?? []) as $c): ?>
                    <?php $rid = (int)($c['id'] ?? 0); ?>
                    <option
                        value="<?php echo $rid > 0 ? (string)$rid : ''; ?>"
                        data-recipient="<?php echo htmlspecialchars((string)($c['recipient_name'] ?? ''), ENT_QUOTES); ?>"
                        data-phone="<?php echo htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES); ?>"
                        data-address="<?php echo htmlspecialchars((string)($c['address'] ?? ''), ENT_QUOTES); ?>"
                    ><?php echo htmlspecialchars((string)($c['label'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_receiver', '收件人（必填）')); ?></label>
            <input class="fwd-input" type="text" name="receiver_name" id="uda_fwd_receiver_name" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_receiver_phone', '收件电话（必填）')); ?></label>
            <input class="fwd-input" type="text" name="receiver_phone" id="uda_fwd_receiver_phone" required>
        </div>
        <div class="full" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div style="flex:1;min-width:220px;">
                <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_voucher', '凭证上传（必填）')); ?></label>
                <input class="fwd-input" type="file" name="voucher_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
            </div>
            <div>
                <button type="button" class="btn" id="uda_recipient_modal_open"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.btn_save_recipient', '录入常用收件人')); ?></button>
            </div>
        </div>
        <div class="full">
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_address', '收件地址（必填）')); ?></label>
            <textarea class="fwd-textarea" name="receiver_address" id="uda_fwd_receiver_address" required></textarea>
        </div>
        <div class="full">
            <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.label_remark', '备注')); ?></label>
            <textarea class="fwd-textarea" name="remark"></textarea>
        </div>
    </div>
</div>
</form>

<div class="card">
    <h3 style="margin-top:0;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.section_queue', '待合包快件（由快件查询「转」推送）')); ?></h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:44px;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_pick', '选')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_tracking', '面单号')); ?></th>
                    <th><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_receiver', '收件人')); ?></th>
                    <th style="width:100px;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_op', '操作')); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($queueRows)): ?>
                <tr><td colspan="4" class="muted"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.empty_queue', '暂无待合包数据')); ?></td></tr>
            <?php else: ?>
                <?php foreach ($queueRows as $qr): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="queue_pick[]" value="<?php echo (int)($qr['queue_id'] ?? 0); ?>" form="udaForwardPkgForm">
                        </td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($qr['tracking_no'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($qr['receiver_display'] ?? '')); ?></td>
                        <td>
                            <form method="post" style="margin:0;" onsubmit="return confirm((window.__udaFwdPkgI18n || {}).confirmReturn || '');">
                                <input type="hidden" name="post_action" value="uda_return_queue">
                                <input type="hidden" name="queue_id" value="<?php echo (int)($qr['queue_id'] ?? 0); ?>">
                                <button type="submit" style="padding:4px 10px;min-height:auto;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.btn_return_queue', '返')); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="inline-actions" style="margin-top:12px;">
        <button type="submit" form="udaForwardPkgForm"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.btn_submit', '转发确认')); ?></button>
    </div>
</div>

<div id="udaRecipientModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10050;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:720px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" class="fwd-modal-close-x" id="udaRecipientModalClose" aria-label="<?php echo htmlspecialchars(t('uda.page.express_forward_packages.aria_close_modal', '关闭')); ?>">×</button>
        <h3 style="margin:0 0 12px 0;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.modal_title', '录入常用收件人')); ?></h3>
        <form method="post" class="fwd-grid" style="grid-template-columns:1fr 1fr; margin-bottom:16px;">
            <input type="hidden" name="post_action" value="uda_recipient_save">
            <input type="hidden" name="recipient_edit_id" id="uda_recipient_edit_id" value="">
            <div class="full">
                <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.modal_recipient', '收件人')); ?></label>
                <input class="fwd-input" type="text" name="recipient_name" id="uda_modal_recipient_name" required>
            </div>
            <div>
                <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.modal_phone', '电话')); ?></label>
                <input class="fwd-input" type="text" name="recipient_phone" id="uda_modal_recipient_phone" required>
            </div>
            <div class="full">
                <label><?php echo htmlspecialchars(t('uda.page.express_forward_packages.modal_address', '地址')); ?></label>
                <textarea class="fwd-textarea" name="recipient_address" id="uda_modal_recipient_address" rows="3" required></textarea>
            </div>
            <div class="full inline-actions">
                <button type="submit"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.modal_save', '保存')); ?></button>
                <button type="button" class="btn" id="uda_recipient_form_reset"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.modal_reset', '清空新增')); ?></button>
            </div>
        </form>
        <h4 style="margin:0 0 8px 0;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.saved_list', '常用列表')); ?></h4>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_saved_name', '收件人')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_saved_phone', '电话')); ?></th><th><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_saved_addr', '地址')); ?></th><th style="width:120px;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.th_saved_op', '操作')); ?></th></tr>
                </thead>
                <tbody>
                <?php if (empty($recipientRows)): ?>
                    <tr><td colspan="4" class="muted"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.empty_saved', '暂无常用收件人')); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($recipientRows as $rr): ?>
                        <tr>
                            <td class="cell-tip"><?php echo html_cell_tip_content((string)($rr['recipient_name'] ?? '')); ?></td>
                            <td class="cell-tip"><?php echo html_cell_tip_content((string)($rr['phone'] ?? '')); ?></td>
                            <td class="cell-tip"><?php echo html_cell_tip_content((string)($rr['address'] ?? '')); ?></td>
                            <td>
                                <button type="button" class="btn uda-recipient-edit-btn" style="padding:2px 8px;min-height:auto;"
                                    data-id="<?php echo (int)($rr['id'] ?? 0); ?>"
                                    data-name="<?php echo htmlspecialchars((string)($rr['recipient_name'] ?? ''), ENT_QUOTES); ?>"
                                    data-phone="<?php echo htmlspecialchars((string)($rr['phone'] ?? ''), ENT_QUOTES); ?>"
                                    data-address="<?php echo htmlspecialchars((string)($rr['address'] ?? ''), ENT_QUOTES); ?>"
                                ><?php echo htmlspecialchars(t('uda.page.express_forward_packages.btn_edit_short', '改')); ?></button>
                                <form method="post" style="display:inline;margin:0;" onsubmit="return confirm((window.__udaFwdPkgI18n || {}).confirmDelSaved || '');">
                                    <input type="hidden" name="post_action" value="uda_recipient_delete">
                                    <input type="hidden" name="recipient_id" value="<?php echo (int)($rr['id'] ?? 0); ?>">
                                    <button type="submit" style="padding:2px 8px;min-height:auto;"><?php echo htmlspecialchars(t('uda.page.express_forward_packages.btn_del_short', '删')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.__udaFwdPkgI18n=<?php echo json_encode([
    'confirmReturn' => t('uda.page.express_forward_packages.confirm_return', '确定从列表移除并取消再发出？'),
    'confirmDelSaved' => t('uda.page.express_forward_packages.confirm_del_saved', '确定删除？'),
], JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sendAtInput = document.getElementById('uda_fwd_send_at');
    if (sendAtInput) {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        sendAtInput.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T'
            + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
    var sel = document.getElementById('uda_fwd_recipient_select');
    var nameInput = document.getElementById('uda_fwd_receiver_name');
    var phoneInput = document.getElementById('uda_fwd_receiver_phone');
    var addrInput = document.getElementById('uda_fwd_receiver_address');
    if (sel) {
        sel.addEventListener('change', function () {
            var op = this.options[this.selectedIndex];
            if (!op || !op.value) return;
            if (nameInput) nameInput.value = op.getAttribute('data-recipient') || '';
            if (phoneInput) phoneInput.value = op.getAttribute('data-phone') || '';
            if (addrInput) addrInput.value = op.getAttribute('data-address') || '';
        });
    }

    var modal = document.getElementById('udaRecipientModal');
    var openBtn = document.getElementById('uda_recipient_modal_open');
    var closeBtn = document.getElementById('udaRecipientModalClose');
    var editId = document.getElementById('uda_recipient_edit_id');
    var mName = document.getElementById('uda_modal_recipient_name');
    var mPhone = document.getElementById('uda_modal_recipient_phone');
    var mAddr = document.getElementById('uda_modal_recipient_address');
    function openModal() { if (modal) modal.style.display = 'flex'; }
    function closeModal() { if (modal) modal.style.display = 'none'; }
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    var resetBtn = document.getElementById('uda_recipient_form_reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (editId) editId.value = '';
            if (mName) mName.value = '';
            if (mPhone) mPhone.value = '';
            if (mAddr) mAddr.value = '';
        });
    }
    document.querySelectorAll('.uda-recipient-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (editId) editId.value = String(btn.getAttribute('data-id') || '');
            if (mName) mName.value = String(btn.getAttribute('data-name') || '');
            if (mPhone) mPhone.value = String(btn.getAttribute('data-phone') || '');
            if (mAddr) mAddr.value = String(btn.getAttribute('data-address') || '');
            openModal();
        });
    });
});
</script>
