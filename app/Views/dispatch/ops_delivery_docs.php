<?php
/** @var bool $schemaReady */
/** @var string $error */
/** @var string $message */
/** @var list<array<string,mixed>> $rows */
/** @var list<array<string,mixed>> $detailRows */
/** @var string $viewDocNo */
/** @var string $qDocNo */
/** @var string $qLine */
/** @var string $qDate */
/** @var list<array<string,mixed>> $driverRunTokensForView */
/** @var int $stopsFinalState */
$schemaReady = $schemaReady ?? false;
$error = (string)($error ?? '');
$message = (string)($message ?? '');
$rows = $rows ?? [];
$detailRows = $detailRows ?? [];
$viewDocNo = (string)($viewDocNo ?? '');
$qDocNo = (string)($qDocNo ?? '');
$qLine = (string)($qLine ?? '');
$qDate = (string)($qDate ?? '');
$driverRunTokensForView = $driverRunTokensForView ?? [];
$stopsFinalState = (int)($stopsFinalState ?? 0);
?>
<style>
.page-delivery-docs .dd-filter-form {
    display: grid;
    grid-template-columns: repeat(4, minmax(180px, 1fr));
    gap: 10px;
    align-items: end;
}
.page-delivery-docs .dd-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -4px;
}
.page-delivery-docs .dd-table-wrap .data-table { min-width: 560px; }
.page-delivery-docs .delivery-docs-driver {
    margin-top: 14px;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #2563eb;
    background: #f8fafc;
}
.page-delivery-docs .driver-tokens-mobile { display: none; }
@media (max-width: 900px) {
    .page-delivery-docs .dd-filter-form {
        grid-template-columns: 1fr;
    }
    .page-delivery-docs .dd-table-wrap .data-table { min-width: 480px; }
    .page-delivery-docs .driver-tokens-desktop { display: none; }
    .page-delivery-docs .driver-tokens-mobile { display: block; }
    .page-delivery-docs .driver-token-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 10px;
    }
    .page-delivery-docs .driver-token-card .btn-block {
        display: block;
        width: 100%;
        text-align: center;
        margin-top: 8px;
        box-sizing: border-box;
    }
}
</style>

<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>
    <?php return; ?>
<?php endif; ?>

<div class="page-delivery-docs">
<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 派送单列表</h2>
    <div class="muted">查看已生成的派送单（按派送单号聚合），可进入明细查看客户与件数。</div>
</div>

<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="get" class="dd-filter-form">
        <div><label>派送单号</label><input name="q_delivery_doc_no" value="<?php echo htmlspecialchars($qDocNo); ?>" placeholder="模糊匹配"></div>
        <div>
            <label>派送线</label>
            <select name="q_dispatch_line">
                <option value="">全部</option>
                <?php foreach (['A','B','C','D','E'] as $line): ?>
                    <option value="<?php echo $line; ?>"<?php echo $qLine === $line ? ' selected' : ''; ?>><?php echo $line; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>预计派送日期</label><input type="date" name="q_planned_delivery_date" value="<?php echo htmlspecialchars($qDate); ?>"></div>
        <div class="inline-actions"><button type="submit">查询</button><a class="btn" href="/dispatch/ops/delivery-docs">重置</a></div>
    </form>
</div>

<div class="card">
    <div class="dd-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>派送单号</th>
                    <th>派送线</th>
                    <th>预计派送日期</th>
                    <th>客户数</th>
                    <th>总件数</th>
                    <th>生成时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="muted">暂无派送单数据</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $docNo = (string)($r['delivery_doc_no'] ?? ''); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($docNo); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['dispatch_line'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['planned_delivery_date'] ?? '')); ?></td>
                            <td><?php echo (int)($r['customer_count'] ?? 0); ?></td>
                            <td><?php echo (int)($r['piece_count'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
                            <td><a class="btn" style="padding:4px 10px;min-height:auto;" href="/dispatch/ops/delivery-docs?delivery_doc_no=<?php echo urlencode($docNo); ?>">查看明细</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($viewDocNo !== ''): ?>
<div class="card">
    <h3 style="margin:0 0 10px 0;">派送单明细：<?php echo htmlspecialchars($viewDocNo); ?></h3>
    <div class="dd-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>顺序</th>
                    <th>段</th>
                    <th>客户编码</th>
                    <th>微信/Line号</th>
                    <th>主/副线路</th>
                    <th>件数</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detailRows as $d): ?>
                    <tr>
                        <td><?php echo max(1, (int)($d['stop_order'] ?? 0)); ?></td>
                        <td><?php echo (int)($d['segment_index'] ?? 0) + 1; ?></td>
                        <td><?php echo htmlspecialchars((string)($d['customer_code'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)($d['wx_or_line'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(((string)($d['route_primary'] ?? '')) . '/' . ((string)($d['route_secondary'] ?? ''))); ?></td>
                        <td><?php echo (int)($d['piece_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $baseDriver = $host !== '' ? ($scheme . '://' . $host . '/dispatch/driver/run?t=') : '/dispatch/driver/run?t=';
    ?>
    <section class="delivery-docs-driver">
        <h3 style="margin:0 0 8px 0;">司机端（免登录 · 当前为中文界面）</h3>
        <p class="muted" style="margin:0 0 8px 0;">
            当前状态：<?php echo $stopsFinalState === 1 ? '已发布最终派送单（司机使用此顺序）' : '草稿（可先优化，再发布）'; ?>
        </p>
        <p class="muted" style="margin:0 0 10px 0;">流程建议：先“优化排序（草稿）” -> 确认后“发布为最终派送单” -> 最后“生成司机端链接”。每段最多 6 位客户。</p>
        <div class="inline-actions" style="margin-bottom:12px;">
            <form method="post">
                <input type="hidden" name="action" value="optimize_delivery_doc_stops">
                <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($viewDocNo); ?>">
                <button type="submit">优化排序（草稿）</button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="finalize_delivery_doc_stops">
                <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($viewDocNo); ?>">
                <button type="submit" style="background:#0f766e;">发布为最终派送单</button>
            </form>
        </div>
        <form method="post" style="margin-bottom:12px;">
            <input type="hidden" name="action" value="create_driver_run_tokens">
            <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($viewDocNo); ?>">
            <button type="submit">生成 / 刷新本单全部段链接</button>
        </form>
        <?php if ($driverRunTokensForView === []): ?>
            <p class="muted">尚未生成链接，请点击上方按钮。</p>
        <?php else: ?>
            <div class="driver-tokens-desktop dd-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>段序（从 1 起）</th>
                            <th>过期时间</th>
                            <th>司机页链接（可复制做二维码）</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($driverRunTokensForView as $tr): ?>
                            <?php
                            $tok = (string)($tr['token'] ?? '');
                            $seg = (int)($tr['segment_index'] ?? 0);
                            $exp = (string)($tr['expires_at'] ?? '');
                            $url = $baseDriver . rawurlencode($tok);
                            ?>
                            <tr>
                                <td><?php echo (int)$seg + 1; ?></td>
                                <td><?php echo htmlspecialchars($exp); ?></td>
                                <td style="word-break:break-all;font-size:12px;"><a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($url); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="driver-tokens-mobile" aria-hidden="false">
                <?php foreach ($driverRunTokensForView as $tr): ?>
                    <?php
                    $tok = (string)($tr['token'] ?? '');
                    $seg = (int)($tr['segment_index'] ?? 0);
                    $exp = (string)($tr['expires_at'] ?? '');
                    $url = $baseDriver . rawurlencode($tok);
                    ?>
                    <div class="driver-token-card">
                        <strong>第 <?php echo (int)$seg + 1; ?> 段</strong>
                        <span class="muted" style="display:block;margin-top:4px;">过期：<?php echo htmlspecialchars($exp); ?></span>
                        <a class="btn btn-block" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer">打开司机页</a>
                        <button type="button" class="btn btn-block copy-driver-url" style="background:#64748b;" data-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">复制本段链接</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('.page-delivery-docs .copy-driver-url').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var u = btn.getAttribute('data-url') || '';
            if (!u) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(u).then(function () {
                    btn.textContent = '已复制';
                    window.setTimeout(function () { btn.textContent = '复制本段链接'; }, 2000);
                }).catch(function () {
                    window.prompt('请长按复制链接', u);
                });
            } else {
                window.prompt('请长按复制链接', u);
            }
        });
    });
})();
</script>
</div>

