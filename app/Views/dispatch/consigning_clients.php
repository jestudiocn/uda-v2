<?php
/** @var array $rows */
/** @var array $parties */
/** @var string $message */
/** @var string $error */
/** @var bool $canEdit */
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 委托客户</h2>
    <div class="muted">委托我司派送的货主；可与财务「收付款对象」可选关联。</div>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($canEdit): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">新增委托客户</h3>
    <form method="post" class="form-grid">
        <label for="client_code">内部编号 <span class="muted">（全局唯一）</span></label>
        <input id="client_code" name="client_code" maxlength="40" required>
        <label for="client_name">名称</label>
        <input id="client_name" name="client_name" maxlength="160" required>
        <label for="party_id">关联财务客户 <span class="muted">（可选）</span></label>
        <select id="party_id" name="party_id">
            <option value="0">不关联</option>
            <?php foreach ($parties as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars((string)$p['party_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="remark">备注</label>
        <input id="remark" name="remark" maxlength="255">
        <div class="form-full">
            <button type="submit" name="add_consigning_client" value="1">保存</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table table-valign-middle">
            <thead>
                <tr>
                    <th>编号</th>
                    <th>名称</th>
                    <th>财务客户</th>
                    <th>状态</th>
                    <th>更新时间</th>
                    <?php if ($canEdit): ?><th style="min-width:120px;text-align:center;">操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?php echo $canEdit ? 6 : 5; ?>" class="muted">暂无委托客户</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $rid = (int)($r['id'] ?? 0);
                    $editPayload = [
                        'id' => $rid,
                        'client_code' => (string)($r['client_code'] ?? ''),
                        'client_name' => (string)($r['client_name'] ?? ''),
                        'party_id' => (int)($r['party_id'] ?? 0),
                        'remark' => (string)($r['remark'] ?? ''),
                        'status' => (int)($r['status'] ?? 0) === 1 ? 1 : 0,
                    ];
                    ?>
                    <tr>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['client_code'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['client_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content(trim((string)($r['party_name'] ?? ''))); ?></td>
                        <td><?php echo (int)($r['status'] ?? 0) === 1 ? '启用' : '停用'; ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($r['updated_at'] ?? '')); ?></td>
                        <?php if ($canEdit): ?>
                        <td style="text-align:center;white-space:nowrap;vertical-align:middle;">
                            <div class="dispatch-row-actions">
                                <button type="button" class="btn btn-dispatch-round btn-dispatch-round--edit cc-edit-open" title="编辑" data-row="<?php echo htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">E</button>
                                <form method="post" style="display:inline;margin:0;" action="/dispatch/consigning-clients" onsubmit="return confirm('确认删除该委托客户？将一并删除其下派送客户与订单，且不可恢复。');">
                                    <input type="hidden" name="delete_consigning_client" value="1">
                                    <input type="hidden" name="consigning_client_id" value="<?php echo $rid; ?>">
                                    <button type="submit" class="btn btn-dispatch-round btn-dispatch-round--delete" title="删除">D</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canEdit): ?>
<div id="ccEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="position:relative;max-width:520px;width:100%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 28px rgba(0,0,0,.2);">
        <button type="button" id="cc_edit_close_x" class="fwd-modal-close-x" style="position:absolute;top:10px;right:12px;">×</button>
        <h3 style="margin:0 0 12px 0;">编辑委托客户</h3>
        <form method="post" action="/dispatch/consigning-clients" class="form-grid">
            <input type="hidden" name="save_consigning_client_edit" value="1">
            <input type="hidden" name="consigning_client_id" id="cc_edit_id" value="">
            <label>内部编号</label>
            <input id="cc_edit_code_display" type="text" disabled style="background:#f1f5f9;">
            <label for="cc_edit_name">名称</label>
            <input id="cc_edit_name" name="client_name" maxlength="160" required>
            <label for="cc_edit_party">关联财务客户</label>
            <select id="cc_edit_party" name="party_id">
                <option value="0">不关联</option>
                <?php foreach ($parties as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars((string)$p['party_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="cc_edit_remark">备注</label>
            <input id="cc_edit_remark" name="remark" maxlength="255">
            <label for="cc_edit_status">状态</label>
            <select id="cc_edit_status" name="status">
                <option value="1">启用</option>
                <option value="0">停用</option>
            </select>
            <div class="form-full" style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn" id="cc_edit_cancel" style="background:#64748b;">取消</button>
                <button type="submit">保存</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('ccEditModal');
    function openCc(payload) {
        if (!modal) return;
        document.getElementById('cc_edit_id').value = String(payload.id || '');
        document.getElementById('cc_edit_code_display').value = payload.client_code || '';
        document.getElementById('cc_edit_name').value = payload.client_name || '';
        document.getElementById('cc_edit_party').value = String(payload.party_id || 0);
        document.getElementById('cc_edit_remark').value = payload.remark || '';
        document.getElementById('cc_edit_status').value = (payload.status === 0) ? '0' : '1';
        modal.style.display = 'flex';
    }
    function closeCc() {
        if (modal) modal.style.display = 'none';
    }
    document.querySelectorAll('.cc-edit-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                var payload = JSON.parse(btn.getAttribute('data-row') || '{}');
                openCc(payload);
            } catch (e) {}
        });
    });
    var cx = document.getElementById('cc_edit_close_x');
    if (cx) cx.addEventListener('click', closeCc);
    var cc = document.getElementById('cc_edit_cancel');
    if (cc) cc.addEventListener('click', closeCc);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeCc();
        });
    }
})();
</script>
<?php endif; ?>
