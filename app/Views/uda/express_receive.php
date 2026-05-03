<?php
/** @var bool $schemaReady */
/** @var string $message */
/** @var string $error */
?>
<div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo htmlspecialchars(t('uda.page.express_receive.title', 'UDA快件 / 快件录入')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars(t('uda.page.express_receive.subtitle', '对应 V1「收件登记」。')); ?></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars(t('uda.page.express_receive.schema', '表 `express_uda` 不存在，无法使用该功能。')); ?></div>
    <?php return; ?>
<?php endif; ?>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="post" class="form-grid" style="grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px;">
        <input type="hidden" name="uda_receive_submit" value="1">
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_receive.receive_time', '收到时间')); ?></label>
            <input type="datetime-local" id="uda_receive_time" name="receive_time" step="1" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_receive.tracking_no', '快递单号')); ?></label>
            <input type="text" name="tracking_no" id="uda_tracking_no" placeholder="<?php echo htmlspecialchars(t('uda.page.express_receive.tracking_placeholder', '支持扫码，自动去掉@后缀')); ?>" required autocomplete="off">
        </div>
        <div>
            <label><?php echo htmlspecialchars(t('uda.page.express_receive.receiver', '收件人')); ?></label>
            <input type="text" name="receiver_name" placeholder="<?php echo htmlspecialchars(t('uda.common.optional', '选填')); ?>">
        </div>
        <div class="form-full">
            <label><?php echo htmlspecialchars(t('uda.page.express_receive.remark', '备注')); ?></label>
            <textarea name="remark" rows="4" placeholder="<?php echo htmlspecialchars(t('uda.common.optional', '选填')); ?>"></textarea>
        </div>
        <div class="form-full inline-actions">
            <button type="submit"><?php echo htmlspecialchars(t('uda.common.save_record', '保存记录')); ?></button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('uda_tracking_no');
    var receiveTime = document.getElementById('uda_receive_time');
    if (receiveTime && !receiveTime.value) {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        receiveTime.value = d.getFullYear() + '-'
            + pad(d.getMonth() + 1) + '-'
            + pad(d.getDate()) + 'T'
            + pad(d.getHours()) + ':'
            + pad(d.getMinutes()) + ':'
            + pad(d.getSeconds());
    }
    if (!input) return;
    input.focus();
    input.addEventListener('input', function () {
        this.value = String(this.value || '').toUpperCase().trim().replace(/@.*$/, '');
    });
});
</script>
