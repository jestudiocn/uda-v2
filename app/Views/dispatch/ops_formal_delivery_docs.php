<?php
/** @var bool $schemaReady */
/** @var string $error */
/** @var string $message */
/** @var list<array<string,mixed>> $rows */
/** @var list<array<string,mixed>> $drivers */
/** @var list<array<string,mixed>> $assignedDriverFilterOptions */
/** @var string $qFormalDate */
/** @var int $qFormalDriverId */
$schemaReady = $schemaReady ?? false;
$error = (string)($error ?? '');
$message = (string)($message ?? '');
$rows = $rows ?? [];
$drivers = $drivers ?? [];
$assignedDriverFilterOptions = $assignedDriverFilterOptions ?? [];
$qFormalDate = (string)($qFormalDate ?? '');
$qFormalDriverId = (int)($qFormalDriverId ?? 0);
?>
<?php if (!$schemaReady): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error !== '' ? $error : '数据表未就绪'); ?></div>
    <?php return; ?>
<?php endif; ?>

<div class="card">
    <h2 style="margin:0 0 6px 0;">派送业务 / 派送操作 / 正式派送单列表</h2>
    <div class="muted">已从初步派送单转入的正式派送单。流程：先点「生成路线分段」→ 本单进入「派送单拣货表」；生成分段后即可回本页「指派」司机（可与拣货并行）；司机在「司机派送」执行；指派后本页可点「已完成」「部份完成」。在<strong>未指派司机且未完成司机派送</strong>前，可点右侧「退回」将本单退回「初步派送单列表」继续调整。主/副路线排序与列表一致。</div>
</div>
<?php if ($message !== ''): ?><div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <form method="get" class="inline-actions" style="flex-wrap:wrap;gap:12px;align-items:end;">
        <div>
            <label for="q_formal_date" style="display:block;margin-bottom:4px;font-size:13px;">预计派送日期</label>
            <input id="q_formal_date" type="date" name="q_planned_delivery_date" value="<?php echo htmlspecialchars($qFormalDate); ?>">
        </div>
        <div>
            <label for="q_formal_driver" style="display:block;margin-bottom:4px;font-size:13px;">司机（曾指派）</label>
            <select id="q_formal_driver" name="q_driver_user_id" style="min-width:200px;">
                <option value="0">全部</option>
                <?php foreach ($assignedDriverFilterOptions as $od): ?>
                    <?php
                    $oid = (int)($od['id'] ?? 0);
                    $olab = trim((string)($od['full_name'] ?? ''));
                    if ($olab === '') {
                        $olab = (string)($od['username'] ?? '');
                    }
                    ?>
                    <option value="<?php echo $oid; ?>"<?php echo $qFormalDriverId === $oid ? ' selected' : ''; ?>><?php echo htmlspecialchars($olab); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">查询</button>
        <a class="btn" href="/dispatch/ops/formal-delivery-docs">重置</a>
    </form>
</div>

<div class="card">
    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>派送单号</th>
                    <th>预计派送日期</th>
                    <th>总客户数</th>
                    <th>派送区域</th>
                    <th>司机</th>
                    <th>指派</th>
                    <th style="min-width:140px;">路线分段</th>
                    <th>已完成</th>
                    <th style="min-width:88px;">退回</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="muted">暂无数据</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $docNo = (string)($r['delivery_doc_no'] ?? '');
                    $planned = (string)($r['planned_delivery_date'] ?? '');
                    $area = (string)($r['delivery_area'] ?? '-');
                    $driverName = trim((string)($r['assigned_driver_name'] ?? ''));
                    $driverIdRow = (int)($r['assigned_driver_user_id'] ?? 0);
                    $doneAt = trim((string)($r['driver_run_completed_at'] ?? ''));
                    $isDone = $doneAt !== '';
                    $custCount = (int)($r['customer_count'] ?? 0);
                    $tokensAt = trim((string)($r['tokens_generated_at'] ?? ''));
                    $pickingAt = trim((string)($r['picking_completed_at'] ?? ''));
                    $pickDone = $pickingAt !== '';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($docNo); ?></td>
                        <td><?php echo htmlspecialchars($planned); ?></td>
                        <td><?php echo (string)max(0, $custCount); ?></td>
                        <td><?php echo htmlspecialchars($area); ?></td>
                        <td><?php echo $driverIdRow > 0 && $driverName !== '' ? htmlspecialchars($driverName) : '<span class="muted">—</span>'; ?></td>
                        <td>
                            <?php if ($isDone): ?>
                                <span class="muted">—</span>
                            <?php elseif ($tokensAt === ''): ?>
                                <span class="muted" title="请先生成本单路线分段">须先分段</span>
                            <?php elseif ($driverIdRow > 0): ?>
                                <button type="button" disabled style="background:#94a3b8;color:#fff;cursor:not-allowed;border:0;border-radius:6px;padding:6px 10px;font-size:13px;">已指派</button>
                            <?php else: ?>
                                <form method="post" class="inline-actions" style="gap:8px;flex-wrap:wrap;">
                                    <input type="hidden" name="action" value="assign_formal_doc_driver">
                                    <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                                    <select name="driver_user_id" required style="min-width:160px;">
                                        <option value="">选择司机</option>
                                        <?php foreach ($drivers as $d): ?>
                                            <?php
                                            $did = (int)($d['id'] ?? 0);
                                            $dlabel = trim((string)($d['full_name'] ?? ''));
                                            if ($dlabel === '') {
                                                $dlabel = (string)($d['username'] ?? '');
                                            }
                                            ?>
                                            <option value="<?php echo $did; ?>"><?php echo htmlspecialchars($dlabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">指派</button>
                                </form>
                                <?php if (!$pickDone): ?>
                                    <span class="muted" style="display:block;margin-top:4px;font-size:12px;" title="拣货表可并行操作">拣货未勾选完成</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isDone): ?>
                                <span class="muted">—</span>
                            <?php elseif ($tokensAt !== ''): ?>
                                <button type="button" disabled style="background:#94a3b8;color:#fff;cursor:not-allowed;border:0;border-radius:6px;padding:8px 12px;font-weight:600;">已生成</button>
                            <?php else: ?>
                                <form method="post" style="display:inline;margin:0;" onsubmit="return confirm(<?php echo json_encode('确认生成路线分段？生成后本单将进入「派送单拣货表」，并生成司机端分段链接；回本页即可指派司机。派送单号：' . $docNo, JSON_UNESCAPED_UNICODE); ?>);">
                                    <input type="hidden" name="action" value="formal_generate_driver_segments">
                                    <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                                    <button type="submit" style="background:#7c3aed;color:#fff;">生成路线分段</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-start;">
                                <button type="button" class="btn formal-doc-detail-btn" data-doc="<?php echo htmlspecialchars($docNo, ENT_QUOTES, 'UTF-8'); ?>">明细</button>
                                <?php if ($driverIdRow > 0 && !$isDone): ?>
                                    <form method="post" style="display:inline;margin:0;" onsubmit="return confirm(<?php echo json_encode('确认本单司机派送已全部完成？派送单号：' . $docNo, JSON_UNESCAPED_UNICODE); ?>);">
                                        <input type="hidden" name="action" value="formal_driver_run_complete">
                                        <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                                        <button type="submit" style="background:#0f766e;">已完成</button>
                                    </form>
                                    <button type="button" class="btn formal-partial-btn" data-doc="<?php echo htmlspecialchars($docNo, ENT_QUOTES, 'UTF-8'); ?>" style="border-color:#ca8a04;color:#a16207;">部份完成</button>
                                <?php elseif ($isDone): ?>
                                    <button type="button" disabled style="background:#94a3b8;color:#fff;cursor:not-allowed;border:0;border-radius:6px;padding:6px 10px;">已完成</button>
                                <?php else: ?>
                                    <span class="muted" title="须先指派司机">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($isDone): ?>
                                <span class="muted" title="司机派送已完成">—</span>
                            <?php elseif ($driverIdRow > 0): ?>
                                <span class="muted" title="已指派司机，无法退回">—</span>
                            <?php else: ?>
                                <form method="post" style="display:inline;margin:0;" onsubmit="return confirm(<?php echo json_encode('确认将本单退回「初步派送单列表」？将清除路线分段、拣货完成记录与工作流状态（运单仍绑定本派送单号）；已生成的司机分段链接将失效。派送单号：' . $docNo, JSON_UNESCAPED_UNICODE); ?>);">
                                    <input type="hidden" name="action" value="formal_revert_to_preliminary">
                                    <input type="hidden" name="delivery_doc_no" value="<?php echo htmlspecialchars($docNo); ?>">
                                    <button type="submit" style="background:#9a3412;color:#fff;border:0;border-radius:6px;padding:6px 10px;font-size:13px;">退回</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="formal-doc-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10050;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;" role="dialog" aria-modal="true" aria-labelledby="formal-doc-detail-title">
    <div class="formal-doc-detail-inner" style="position:relative;width:100%;max-width:960px;background:#fff;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.22);display:flex;flex-direction:column;max-height:min(92vh, 720px);overflow:hidden;min-height:0;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
            <h3 id="formal-doc-detail-title" style="margin:0;font-size:16px;">派送单明细</h3>
            <button type="button" class="btn formal-doc-detail-close" aria-label="关闭">关闭</button>
        </div>
        <div id="formal-doc-detail-scroll" style="flex:1 1 auto;min-height:0;max-height:600px;padding:12px 14px 14px;overflow-y:auto;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-gutter:stable;">
            <p id="formal-doc-detail-loading" class="muted" style="margin:0;">加载中…</p>
            <table id="formal-doc-detail-table" class="data-table" style="display:none;margin:0;min-width:520px;">
                <thead>
                    <tr>
                        <th>客户编码</th>
                        <th>微信/Line</th>
                        <th>英文/泰文小区</th>
                        <th>门牌, 巷</th>
                        <th>主/副线路</th>
                    </tr>
                </thead>
                <tbody id="formal-doc-detail-tbody"></tbody>
            </table>
            <p id="formal-doc-detail-error" class="muted" style="display:none;margin:0;color:#b91c1c;"></p>
        </div>
    </div>
</div>

<div id="formal-partial-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10060;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;" role="dialog" aria-modal="true" aria-labelledby="formal-partial-title">
    <div class="formal-partial-inner" style="position:relative;width:100%;max-width:720px;background:#fff;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.22);display:flex;flex-direction:column;max-height:min(90vh, 640px);overflow:hidden;min-height:0;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
            <h3 id="formal-partial-title" style="margin:0;font-size:16px;">部份完成（回滚未全派送客户）</h3>
            <button type="button" class="btn formal-partial-close" aria-label="关闭">关闭</button>
        </div>
        <div style="flex:1 1 auto;min-height:0;padding:12px 14px;overflow:auto;">
            <p class="muted" style="margin:0 0 8px 0;font-size:13px;">下列客户在本正式派送单中仍有非「已派送」运单。勾选并确认后，将从本单解绑并回到「分配派送单」列表。</p>
            <p id="formal-partial-loading" class="muted" style="margin:0 0 8px 0;">加载中…</p>
            <p id="formal-partial-error" class="muted" style="display:none;margin:0 0 8px 0;color:#b91c1c;"></p>
            <form method="post" id="formal-partial-form" style="display:none;" onsubmit="return window.__formalPartialValidate();">
                <input type="hidden" name="action" value="formal_partial_unbind_customers">
                <input type="hidden" name="delivery_doc_no" id="formal-partial-doc-no" value="">
                <div style="overflow:auto;max-height:42vh;">
                    <table class="data-table" style="min-width:520px;margin:0;">
                        <thead>
                            <tr>
                                <th>客户编码</th>
                                <th>已派送运单</th>
                                <th>总行数</th>
                                <th style="width:72px;text-align:center;">解绑</th>
                            </tr>
                        </thead>
                        <tbody id="formal-partial-tbody"></tbody>
                    </table>
                </div>
                <div class="inline-actions" style="margin-top:12px;gap:8px;">
                    <button type="submit" style="background:#b45309;color:#fff;">确认删除（回滚）</button>
                    <button type="button" class="btn formal-partial-close">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('formal-doc-detail-modal');
    if (!modal) return;
    var inner = modal.querySelector('.formal-doc-detail-inner');
    var titleEl = document.getElementById('formal-doc-detail-title');
    var loadingEl = document.getElementById('formal-doc-detail-loading');
    var tableEl = document.getElementById('formal-doc-detail-table');
    var tbodyEl = document.getElementById('formal-doc-detail-tbody');
    var errEl = document.getElementById('formal-doc-detail-error');

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function mergeHouseLane(h, l) {
        var a = String(h == null ? '' : h).trim();
        var b = String(l == null ? '' : l).trim();
        if (a === '' && b === '') return '—';
        if (a === '') return esc(b);
        if (b === '') return esc(a);
        return esc(a) + ', ' + esc(b);
    }

    function mergeRoutes(rp, rs) {
        var a = String(rp == null ? '' : rp).trim();
        var b = String(rs == null ? '' : rs).trim();
        if (a === '' && b === '') return '—';
        if (a === '') return esc(b);
        if (b === '') return esc(a);
        return esc(a) + '/' + esc(b);
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function openModal(docNo) {
        modal.style.display = 'flex';
        if (titleEl) titleEl.textContent = '派送单明细 · ' + docNo;
        if (loadingEl) { loadingEl.style.display = 'block'; loadingEl.textContent = '加载中…'; }
        if (tableEl) tableEl.style.display = 'none';
        if (tbodyEl) tbodyEl.innerHTML = '';
        if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }

        var url = '/dispatch/ops/formal-delivery-docs?formal_detail_json=1&delivery_doc_no=' + encodeURIComponent(docNo);
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: t || '响应无效' }; } }); })
            .then(function (j) {
                if (loadingEl) loadingEl.style.display = 'none';
                if (!j || !j.ok) {
                    if (errEl) {
                        errEl.style.display = 'block';
                        errEl.textContent = (j && j.error) ? j.error : '加载失败';
                    }
                    return;
                }
                var rows = j.rows || [];
                if (tbodyEl) {
                    tbodyEl.innerHTML = rows.map(function (row) {
                        return '<tr>'
                            + '<td>' + esc(row.customer_code) + '</td>'
                            + '<td>' + (String(row.wx_or_line || '').trim() !== '' ? esc(row.wx_or_line) : '—') + '</td>'
                            + '<td>' + esc(row.community_en_th || '—') + '</td>'
                            + '<td>' + mergeHouseLane(row.addr_house_no, row.addr_road_soi) + '</td>'
                            + '<td>' + mergeRoutes(row.route_primary, row.route_secondary) + '</td>'
                            + '</tr>';
                    }).join('');
                }
                if (tableEl) tableEl.style.display = rows.length ? 'table' : 'none';
                if (!rows.length && errEl) {
                    errEl.style.display = 'block';
                    errEl.style.color = '#64748b';
                    errEl.textContent = '暂无客户明细';
                }
            })
            .catch(function () {
                if (loadingEl) loadingEl.style.display = 'none';
                if (errEl) {
                    errEl.style.display = 'block';
                    errEl.textContent = '网络错误';
                }
            });
    }

    document.querySelectorAll('.formal-doc-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var doc = btn.getAttribute('data-doc') || '';
            if (doc) openModal(doc);
        });
    });

    modal.querySelectorAll('.formal-doc-detail-close').forEach(function (b) {
        b.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    if (inner) {
        inner.addEventListener('click', function (e) { e.stopPropagation(); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
    });
})();

(function () {
    var pModal = document.getElementById('formal-partial-modal');
    if (!pModal) return;
    var pInner = pModal.querySelector('.formal-partial-inner');
    var pTitle = document.getElementById('formal-partial-title');
    var pLoad = document.getElementById('formal-partial-loading');
    var pErr = document.getElementById('formal-partial-error');
    var pForm = document.getElementById('formal-partial-form');
    var pTbody = document.getElementById('formal-partial-tbody');
    var pDocNo = document.getElementById('formal-partial-doc-no');

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function closeP() {
        pModal.style.display = 'none';
    }

    window.__formalPartialValidate = function () {
        var n = document.querySelectorAll('#formal-partial-tbody input[name="delivery_customer_ids[]"]:checked').length;
        if (n === 0) {
            window.alert('请至少勾选一位客户');
            return false;
        }
        return confirm('确认将所选客户从本正式派送单解绑？其已入库货件将回到「分配派送单」列表。');
    };

    pModal.querySelectorAll('.formal-partial-close').forEach(function (b) {
        b.addEventListener('click', closeP);
    });
    pModal.addEventListener('click', function (e) {
        if (e.target === pModal) closeP();
    });
    if (pInner) {
        pInner.addEventListener('click', function (e) { e.stopPropagation(); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && pModal.style.display === 'flex') closeP();
    });

    document.querySelectorAll('.formal-partial-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var doc = btn.getAttribute('data-doc') || '';
            if (!doc) return;
            pModal.style.display = 'flex';
            if (pTitle) pTitle.textContent = '部份完成 · ' + doc;
            if (pDocNo) pDocNo.value = doc;
            if (pLoad) { pLoad.style.display = 'block'; pLoad.textContent = '加载中…'; }
            if (pErr) { pErr.style.display = 'none'; pErr.textContent = ''; pErr.style.color = '#b91c1c'; }
            if (pForm) pForm.style.display = 'none';
            if (pTbody) pTbody.innerHTML = '';

            var url = '/dispatch/ops/formal-delivery-docs?formal_undelivered_json=1&delivery_doc_no=' + encodeURIComponent(doc);
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: t || '响应无效' }; } }); })
                .then(function (j) {
                    if (pLoad) pLoad.style.display = 'none';
                    if (!j || !j.ok) {
                        if (pErr) {
                            pErr.style.display = 'block';
                            pErr.textContent = (j && j.error) ? j.error : '加载失败';
                        }
                        return;
                    }
                    var rows = j.rows || [];
                    if (!pTbody) return;
                    if (rows.length === 0) {
                        if (pErr) {
                            pErr.style.display = 'block';
                            pErr.style.color = '#64748b';
                            pErr.textContent = '本单没有「仍有非已派送运单」且可识别的派送客户，或已全部派送。';
                        }
                        return;
                    }
                    pTbody.innerHTML = rows.map(function (row) {
                        var id = parseInt(String(row.delivery_customer_id), 10) || 0;
                        if (id <= 0) return '';
                        return '<tr>'
                            + '<td>' + esc(row.customer_code) + '</td>'
                            + '<td>' + esc(String(row.delivered_rows != null ? row.delivered_rows : '')) + '</td>'
                            + '<td>' + esc(String(row.total_rows != null ? row.total_rows : '')) + '</td>'
                            + '<td style="text-align:center;"><input type="checkbox" name="delivery_customer_ids[]" value="' + id + '"></td>'
                            + '</tr>';
                    }).join('');
                    if (pForm) pForm.style.display = 'block';
                })
                .catch(function () {
                    if (pLoad) pLoad.style.display = 'none';
                    if (pErr) {
                        pErr.style.display = 'block';
                        pErr.textContent = '网络错误';
                    }
                });
        });
    });
})();
</script>
