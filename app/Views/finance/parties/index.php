<?php
/** @var array $rows */
/** @var string $message */
/** @var string $error */
/** @var int $perPage */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="card">
    <h2>财务管理 / 付款收款对象</h2>
    <div class="muted">维护统一对象清单，供财务记录/待付款/待收款表单选择。</div>
</div>
<?php if (!empty($message)): ?>
    <div class="card" style="border-left:4px solid #16a34a;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #dc2626;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <label for="party_name">对象名称</label>
        <input id="party_name" type="text" name="party_name" required>

        <label for="party_kind">对象类型</label>
        <select id="party_kind" name="party_kind">
            <option value="both">付款+收款</option>
            <option value="pay">仅付款</option>
            <option value="receive">仅收款</option>
        </select>

        <div class="form-full">
            <button type="submit" name="create_party" value="1">新增对象</button>
        </div>
    </form>
</div>

<div class="card">
    <form method="get" class="toolbar" style="margin-bottom:10px;">
        <label for="per_page">每页</label>
        <select id="per_page" name="per_page">
            <?php foreach ([20, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($perPage ?? 20) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">应用</button>
    </form>
    <div style="overflow:auto;">
        <table class="data-table table-valign-middle">
            <thead>
            <tr>
                <th>ID</th>
                <th>对象名称</th>
                <th>对象类型</th>
                <th>状态</th>
                <th>建立时间</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="muted">暂无对象资料</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $kind = (string)($row['party_kind'] ?? 'both');
                    $kindLabel = $kind === 'pay' ? '仅付款' : ($kind === 'receive' ? '仅收款' : '付款+收款');
                    ?>
                    <tr>
                        <td><?php echo (int)($row['id'] ?? 0); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['party_name'] ?? '')); ?></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content($kindLabel); ?></td>
                        <td><span class="chip"><?php echo ((int)($row['status'] ?? 0) === 1) ? '启用' : '停用'; ?></span></td>
                        <td class="cell-tip"><?php echo html_cell_tip_content((string)($row['created_at'] ?? '')); ?></td>
                        <td>
                            <form method="post" action="/finance/parties" style="display:inline;">
                                <input type="hidden" name="toggle_party" value="1">
                                <input type="hidden" name="party_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                <button class="btn" style="padding:6px 10px;min-height:auto;" type="submit">切换状态</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (($totalPages ?? 1) > 1): ?>
    <div class="card">
        <div class="toolbar" style="justify-content:space-between;">
            <span class="muted">共 <?php echo (int)($total ?? 0); ?> 条，第 <?php echo (int)($page ?? 1); ?> / <?php echo (int)($totalPages ?? 1); ?> 页</span>
            <div class="inline-actions">
                <?php for ($p = 1; $p <= (int)$totalPages; $p++): ?>
                    <a class="btn" style="<?php echo $p === (int)$page ? '' : 'background:#64748b;'; ?>padding:6px 10px;min-height:auto;" href="/finance/parties?per_page=<?php echo (int)$perPage; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
