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
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 初步派送单列表</h2>
    <div class="muted">此处仅列出尚未转入正式派送单列表的初步派送单。从「分配派送单」生成成功后会跳转到本页。点「调整」：第一步增删客户 → 第二步<strong>拖动</strong>排序 → 确认后转入「正式派送单列表」（停靠与后续分段/拣货均按该顺序）。若无需改顺序，可直接点行内「转入正式派送单列表」按系统主/副路线排序。</div>
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
                    <th style="min-width:320px;">操作</th>
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
                            <td>
                                <div class="inline-actions" style="gap:6px;flex-wrap:wrap;">
                                    <button type="button" class="btn dd-adjust-btn" data-doc="<?php echo htmlspecialchars($docNo, ENT_QUOTES, 'UTF-8'); ?>" style="background:#1d4ed8;color:#fff;border-color:#1e40af;">调整</button>
                                    <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('确认将本单转入「正式派送单列表」？将发布停靠顺序，之后不可再回到本初步列表修改。');">
                                        <input type="hidden" name="action" value="step3_generate_segment_nav">
                                        <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                                        <button type="submit" style="background:#15803d;color:#fff;border-color:#166534;">转入正式派送单列表</button>
                                    </form>
                                    <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('确认删除本初步派送单？单内所有客户将回到「分配派送单」列表。');">
                                        <input type="hidden" name="action" value="delete_preliminary_delivery_doc">
                                        <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                                        <button type="submit" style="background:#b91c1c;color:#fff;">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="muted">下方明细区已移至新页面：<a href="/dispatch/ops/formal-delivery-docs">正式派送单列表</a> 与 <a href="/dispatch/ops/delivery-pick-sheets">派送单拣货表</a>。</div>
</div>

<style>
    .dd-sort-ul { list-style:none;margin:0;padding:0; }
    .dd-sort-li { display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:8px;background:#f8fafc;cursor:grab; }
    .dd-sort-li:active { cursor:grabbing; }
    .dd-sort-handle { color:#64748b;user-select:none;font-size:14px;letter-spacing:-2px; }
    #dd-adjust-step2 { display:none; }
</style>
<div id="dd-adjust-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10050;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;" role="dialog" aria-modal="true" aria-labelledby="dd-adjust-title">
    <div class="dd-modal-inner" style="position:relative;width:100%;max-width:960px;background:#fff;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.22);display:flex;flex-direction:column;max-height:min(92vh, 820px);overflow:hidden;min-height:0;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
            <h3 id="dd-adjust-title" style="margin:0;font-size:16px;">调整初步派送单</h3>
            <button type="button" class="btn dd-adjust-modal-close" aria-label="关闭">关闭</button>
        </div>
        <div style="flex:1 1 auto;min-height:0;padding:12px 14px;overflow:auto;">
            <p id="dd-adjust-loading" class="muted" style="margin:0 0 8px 0;">加载中…</p>
            <p id="dd-adjust-error" class="muted" style="display:none;margin:0 0 8px 0;color:#b91c1c;"></p>
            <form method="post" id="dd-adjust-wizard-form" style="display:none;">
                <input type="hidden" name="action" value="preliminary_adjust_order_and_formal">
                <input type="hidden" name="delivery_doc_no" id="dd-adjust-doc-no" value="">
                <div id="dd-adjust-order-inputs"></div>
                <div id="dd-adjust-step1">
                    <p class="muted" style="margin:0 0 10px;font-size:13px;"><strong>第 1 步</strong>：勾选要从本单<strong>移除</strong>的客户；在分配池勾选要<strong>追加</strong>的客户。点「下一步」进入拖动排序。</p>
                    <h4 style="margin:12px 0 6px;font-size:14px;">本单客户（移除）</h4>
                    <div style="overflow:auto;max-height:32vh;">
                        <table class="data-table" style="min-width:560px;margin:0;">
                            <thead>
                                <tr>
                                    <th>客户编码</th>
                                    <th>微信/Line</th>
                                    <th>件数</th>
                                    <th>主/副线路</th>
                                    <th style="width:72px;text-align:center;">移除</th>
                                </tr>
                            </thead>
                            <tbody id="dd-adjust-remove-tbody"></tbody>
                        </table>
                    </div>
                    <h4 style="margin:16px 0 6px;font-size:14px;">可追加客户（分配池）</h4>
                    <div style="overflow:auto;max-height:34vh;">
                        <table class="data-table" style="min-width:640px;margin:0;">
                            <thead>
                                <tr>
                                    <th>客户编码</th>
                                    <th>微信/Line</th>
                                    <th>派送件数</th>
                                    <th>泰文小区</th>
                                    <th>主/副线路</th>
                                    <th style="width:72px;text-align:center;"><input type="checkbox" id="dd-adjust-add-check-all" title="全选"></th>
                                </tr>
                            </thead>
                            <tbody id="dd-adjust-add-tbody"></tbody>
                        </table>
                    </div>
                    <div class="inline-actions" style="margin-top:14px;gap:8px;flex-wrap:wrap;">
                        <button type="button" id="dd-adjust-btn-next" style="background:#1d4ed8;color:#fff;">下一步：拖动排序</button>
                        <button type="button" class="btn dd-adjust-modal-close">取消</button>
                    </div>
                </div>
                <div id="dd-adjust-step2">
                    <p class="muted" style="margin:0 0 10px;font-size:13px;"><strong>第 2 步</strong>：拖动整行调整<strong>派送先后顺序</strong>（首户在上）。确认后将按此顺序写入停靠并转入「正式派送单列表」。</p>
                    <ul id="dd-adjust-sort-ul" class="dd-sort-ul" aria-label="客户顺序"></ul>
                    <div class="inline-actions" style="margin-top:14px;gap:8px;flex-wrap:wrap;">
                        <button type="button" id="dd-adjust-btn-back" class="btn">上一步</button>
                        <button type="submit" id="dd-adjust-btn-submit" style="background:#15803d;color:#fff;">确认顺序并转入正式派送单列表</button>
                        <button type="button" class="btn dd-adjust-modal-close">取消</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function wxLine(r) {
        var wx = String(r.wechat_id == null ? '' : r.wechat_id).trim();
        var ln = String(r.line_id == null ? '' : r.line_id).trim();
        if (wx === '' && ln === '') return '—';
        if (wx === '') return esc(ln);
        if (ln === '') return esc(wx);
        return esc(wx) + ' / ' + esc(ln);
    }
    function routeText(rp, rs) {
        var a = String(rp == null ? '' : rp).trim();
        var b = String(rs == null ? '' : rs).trim();
        if (a === '' && b === '') return '—';
        if (a === '') return esc(b);
        if (b === '') return esc(a);
        return esc(a) + '/' + esc(b);
    }
    function getRemoveIdSet() {
        var s = new Set();
        document.querySelectorAll('#dd-adjust-remove-tbody input[type="checkbox"]:checked').forEach(function (c) {
            var v = parseInt(String(c.value), 10) || 0;
            if (v > 0) { s.add(v); }
        });
        return s;
    }
    function getAddIdSet() {
        var s = new Set();
        document.querySelectorAll('.dd-adjust-add-row:checked').forEach(function (c) {
            var v = parseInt(String(c.value), 10) || 0;
            if (v > 0) { s.add(v); }
        });
        return s;
    }
    function buildMergedSortRows(bindRows, poolRows, removeIdSet, addIdSet) {
        var out = [];
        bindRows.forEach(function (row) {
            var id = parseInt(String(row.delivery_customer_id), 10) || 0;
            if (id <= 0) { return; }
            if (removeIdSet.has(id)) { return; }
            var code = String(row.customer_code || '').trim();
            if (!code) { return; }
            out.push({
                code: code,
                route_primary: String(row.route_primary || '').trim(),
                route_secondary: String(row.route_secondary || '').trim(),
                label: esc(code) + ' · ' + wxLine(row)
            });
        });
        var poolById = {};
        poolRows.forEach(function (pr) {
            var pid = parseInt(String(pr.id), 10) || 0;
            if (pid > 0) { poolById[pid] = pr; }
        });
        addIdSet.forEach(function (addId) {
            var pr = poolById[addId];
            if (!pr) { return; }
            var code = String(pr.customer_code || '').trim();
            if (!code) { return; }
            out.push({
                code: code,
                route_primary: String(pr.route_primary || '').trim(),
                route_secondary: String(pr.route_secondary || '').trim(),
                label: esc(code) + ' · ' + wxLine(pr)
            });
        });
        out.sort(function (a, b) {
            if (a.route_primary !== b.route_primary) { return a.route_primary.localeCompare(b.route_primary); }
            if (a.route_secondary !== b.route_secondary) { return a.route_secondary.localeCompare(b.route_secondary); }
            return a.code.localeCompare(b.code);
        });
        return out;
    }
    function wireDragSort(ul) {
        var dragged = null;
        ul.addEventListener('dragstart', function (e) {
            var li = e.target && e.target.closest ? e.target.closest('li') : null;
            if (!li || li.parentNode !== ul) { return; }
            dragged = li;
            li.style.opacity = '0.45';
            try { e.dataTransfer.setData('text/plain', li.getAttribute('data-code') || ''); } catch (err) { /* ignore */ }
            e.dataTransfer.effectAllowed = 'move';
        });
        ul.addEventListener('dragend', function () {
            if (dragged) { dragged.style.opacity = '1'; }
            dragged = null;
        });
        ul.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragged) { return; }
            var li = e.target && e.target.closest ? e.target.closest('li') : null;
            if (!li || li.parentNode !== ul || li === dragged) { return; }
            var rect = li.getBoundingClientRect();
            var before = (e.clientY - rect.top) < (rect.height / 2);
            if (before) {
                ul.insertBefore(dragged, li);
            } else {
                ul.insertBefore(dragged, li.nextSibling);
            }
        });
    }
    function renderSortUl(rows) {
        var ul = document.getElementById('dd-adjust-sort-ul');
        if (!ul) { return; }
        ul.innerHTML = '';
        rows.forEach(function (r) {
            var li = document.createElement('li');
            li.className = 'dd-sort-li';
            li.setAttribute('draggable', 'true');
            li.setAttribute('data-code', r.code);
            li.innerHTML = '<span class="dd-sort-handle" aria-hidden="true">⋮⋮</span><span style="flex:1;">' + r.label + '</span><span class="muted" style="font-size:12px;">' + routeText(r.route_primary, r.route_secondary) + '</span>';
            ul.appendChild(li);
        });
        wireDragSort(ul);
    }
    function showStep1() {
        var s1 = document.getElementById('dd-adjust-step1');
        var s2 = document.getElementById('dd-adjust-step2');
        if (s1) { s1.style.display = 'block'; }
        if (s2) { s2.style.display = 'none'; }
    }
    function showStep2() {
        var s1 = document.getElementById('dd-adjust-step1');
        var s2 = document.getElementById('dd-adjust-step2');
        if (s1) { s1.style.display = 'none'; }
        if (s2) { s2.style.display = 'block'; }
    }

    var adjModal = document.getElementById('dd-adjust-modal');
    var adjLoading = document.getElementById('dd-adjust-loading');
    var adjErr = document.getElementById('dd-adjust-error');
    var adjForm = document.getElementById('dd-adjust-wizard-form');
    var adjDocNo = document.getElementById('dd-adjust-doc-no');
    var adjTitle = document.getElementById('dd-adjust-title');
    var adjRmTbody = document.getElementById('dd-adjust-remove-tbody');
    var adjAddTbody = document.getElementById('dd-adjust-add-tbody');
    var adjAddCheckAll = document.getElementById('dd-adjust-add-check-all');

    function closeAdj() {
        if (adjModal) { adjModal.style.display = 'none'; }
        showStep1();
    }

    if (adjModal) {
        adjModal.querySelectorAll('.dd-adjust-modal-close').forEach(function (b) {
            b.addEventListener('click', closeAdj);
        });
        adjModal.addEventListener('click', function (e) {
            if (e.target === adjModal) { closeAdj(); }
        });
        var inner = adjModal.querySelector('.dd-modal-inner');
        if (inner) { inner.addEventListener('click', function (e) { e.stopPropagation(); }); }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        if (adjModal && adjModal.style.display === 'flex') { closeAdj(); }
    });

    var btnNext = document.getElementById('dd-adjust-btn-next');
    if (btnNext) {
        btnNext.addEventListener('click', function () {
            var bindRows = window.__ddAdjustBindRows || [];
            var poolRows = window.__ddAdjustPoolRows || [];
            var merged = buildMergedSortRows(bindRows, poolRows, getRemoveIdSet(), getAddIdSet());
            if (merged.length === 0) {
                window.alert('请至少保留或追加一位有客户编码的客户，再进入排序。');
                return;
            }
            var codes = merged.map(function (m) { return m.code; });
            var uniq = {};
            var dup = false;
            codes.forEach(function (c) {
                if (uniq[c]) { dup = true; }
                uniq[c] = true;
            });
            if (dup) {
                window.alert('当前选择下存在重复客户编码，请调整移除/追加后再试。');
                return;
            }
            renderSortUl(merged);
            if (adjTitle) { adjTitle.textContent = '拖动排序 · ' + (adjDocNo && adjDocNo.value ? adjDocNo.value : ''); }
            showStep2();
        });
    }
    var btnBack = document.getElementById('dd-adjust-btn-back');
    if (btnBack) {
        btnBack.addEventListener('click', function () {
            if (adjTitle) {
                var d = adjDocNo && adjDocNo.value ? adjDocNo.value : '';
                adjTitle.textContent = d ? '调整初步派送单 · ' + d : '调整初步派送单';
            }
            showStep1();
        });
    }
    if (adjForm) {
        adjForm.addEventListener('submit', function () {
            var holder = document.getElementById('dd-adjust-order-inputs');
            if (holder) { holder.innerHTML = ''; }
            var ul = document.getElementById('dd-adjust-sort-ul');
            if (!ul || !holder) { return; }
            ul.querySelectorAll('li').forEach(function (li) {
                var code = li.getAttribute('data-code') || '';
                if (!code) { return; }
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'stop_order_customer_codes[]';
                inp.value = code;
                holder.appendChild(inp);
            });
            return window.confirm('确认按当前顺序转入「正式派送单列表」？将先应用移除/追加，再发布停靠顺序，之后不可再回到本初步列表。');
        });
    }

    document.querySelectorAll('.dd-adjust-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var doc = btn.getAttribute('data-doc') || '';
            if (!doc || !adjModal) { return; }
            window.__ddAdjustBindRows = [];
            window.__ddAdjustPoolRows = [];
            adjModal.style.display = 'flex';
            showStep1();
            if (adjTitle) { adjTitle.textContent = '调整初步派送单 · ' + doc; }
            if (adjDocNo) { adjDocNo.value = doc; }
            if (adjLoading) { adjLoading.style.display = 'block'; adjLoading.textContent = '加载中…'; }
            if (adjErr) { adjErr.style.display = 'none'; adjErr.textContent = ''; adjErr.style.color = '#b91c1c'; }
            if (adjForm) { adjForm.style.display = 'none'; }
            if (adjRmTbody) { adjRmTbody.innerHTML = ''; }
            if (adjAddTbody) { adjAddTbody.innerHTML = ''; }
            if (adjAddCheckAll) { adjAddCheckAll.checked = false; }
            var urlDoc = '/dispatch/ops/delivery-docs?doc_customers_json=1&delivery_doc_no=' + encodeURIComponent(doc);
            var p1 = fetch(urlDoc, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: t || '响应无效' }; } }); });
            var p2 = fetch('/dispatch/ops/delivery-docs?assign_pool_json=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: t || '响应无效' }; } }); });
            Promise.all([p1, p2]).then(function (results) {
                if (adjLoading) { adjLoading.style.display = 'none'; }
                var jDoc = results[0];
                var jPool = results[1];
                if (!jDoc || !jDoc.ok) {
                    if (adjErr) {
                        adjErr.style.display = 'block';
                        adjErr.textContent = (jDoc && jDoc.error) ? jDoc.error : '加载本单客户失败';
                    }
                    return;
                }
                if (!jPool || !jPool.ok) {
                    if (adjErr) {
                        adjErr.style.display = 'block';
                        adjErr.textContent = (jPool && jPool.error) ? jPool.error : '加载分配池失败';
                    }
                    return;
                }
                var bindRows = jDoc.bind_rows || [];
                window.__ddAdjustBindRows = bindRows;
                window.__ddAdjustPoolRows = jPool.rows || [];
                if (adjRmTbody) {
                    if (bindRows.length === 0) {
                        adjRmTbody.innerHTML = '<tr><td colspan="5" class="muted">本单暂无可按客户 id 移除的绑定（若仅有编码匹配运单，请整单删除后重新生成）；仍可仅追加客户后排序。</td></tr>';
                    } else {
                        adjRmTbody.innerHTML = bindRows.map(function (row) {
                            var id = parseInt(String(row.delivery_customer_id), 10) || 0;
                            if (id <= 0) { return ''; }
                            return '<tr>'
                                + '<td>' + esc(row.customer_code) + '</td>'
                                + '<td>' + wxLine(row) + '</td>'
                                + '<td>' + esc(String(row.piece_count != null ? row.piece_count : '')) + '</td>'
                                + '<td>' + routeText(row.route_primary, row.route_secondary) + '</td>'
                                + '<td style="text-align:center;"><input type="checkbox" name="remove_delivery_customer_ids[]" value="' + id + '"></td>'
                                + '</tr>';
                        }).join('');
                    }
                }
                var pool = jPool.rows || [];
                if (adjAddTbody) {
                    if (pool.length === 0) {
                        adjAddTbody.innerHTML = '<tr><td colspan="6" class="muted">当前分配池暂无可追加客户</td></tr>';
                    } else {
                        adjAddTbody.innerHTML = pool.map(function (row) {
                            var id = parseInt(String(row.id), 10) || 0;
                            if (id <= 0) { return ''; }
                            return '<tr>'
                                + '<td>' + esc(row.customer_code) + '</td>'
                                + '<td>' + wxLine(row) + '</td>'
                                + '<td>' + esc(String(row.inbound_count != null ? row.inbound_count : '')) + '</td>'
                                + '<td>' + esc(row.community_name_th || '—') + '</td>'
                                + '<td>' + routeText(row.route_primary, row.route_secondary) + '</td>'
                                + '<td style="text-align:center;"><input type="checkbox" name="add_delivery_customer_ids[]" value="' + id + '" class="dd-adjust-add-row"></td>'
                                + '</tr>';
                        }).join('');
                    }
                }
                if (adjAddCheckAll) {
                    adjAddCheckAll.onchange = function () {
                        var on = !!adjAddCheckAll.checked;
                        document.querySelectorAll('.dd-adjust-add-row').forEach(function (c) { c.checked = on; });
                    };
                }
                if (adjForm) { adjForm.style.display = 'block'; }
            }).catch(function () {
                if (adjLoading) { adjLoading.style.display = 'none'; }
                if (adjErr) {
                    adjErr.style.display = 'block';
                    adjErr.textContent = '网络错误';
                }
            });
        });
    });
})();
</script>
</div>

