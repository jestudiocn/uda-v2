<?php
/** @var string $title */
/** @var string $contentView */
$__htmlLang = (string)($_SESSION['app_locale'] ?? 'zh-CN');
if (!in_array($__htmlLang, ['zh-CN', 'th-TH'], true)) {
    $__htmlLang = 'zh-CN';
}
$pendingTodoCount = 0;
$overdueTodoCount = 0;
if (isset($_SESSION['auth_user_id'])) {
    try {
        $conn = require __DIR__ . '/../../../config/database.php';
        $userId = (int)($_SESSION['auth_user_id'] ?? 0);
        $canPending = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.calendar', 'menu.finance', 'menu.dispatch', 'menu.dashboard']);
        if ($userId > 0 && $canPending) {
            $hasCompletedCol = false;
            $resCol = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'is_completed'");
            if ($resCol instanceof mysqli_result && $resCol->num_rows > 0) {
                $hasCompletedCol = true;
            }
            if ($resCol instanceof mysqli_result) {
                $resCol->free();
            }
            if ($hasCompletedCol) {
                $hasAssigneeTable = false;
                $resTbl = $conn->query("SHOW TABLES LIKE 'calendar_event_assignees'");
                if ($resTbl instanceof mysqli_result && $resTbl->num_rows > 0) {
                    $hasAssigneeTable = true;
                }
                if ($resTbl instanceof mysqli_result) {
                    $resTbl->free();
                }
                $sql = $hasAssigneeTable
                    ? 'SELECT COUNT(*) AS c FROM calendar_events ce WHERE COALESCE(ce.is_completed, 0) = 0 AND (ce.created_by = ? OR EXISTS (SELECT 1 FROM calendar_event_assignees cea WHERE cea.event_id = ce.id AND cea.user_id = ?))'
                    : 'SELECT COUNT(*) AS c FROM calendar_events ce WHERE COALESCE(ce.is_completed, 0) = 0 AND ce.created_by = ?';
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($hasAssigneeTable) {
                        $stmt->bind_param('ii', $userId, $userId);
                    } else {
                        $stmt->bind_param('i', $userId);
                    }
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $pendingTodoCount += (int)($row['c'] ?? 0);
                    $stmt->close();
                }
            }
            $hasPayables = false;
            $resPayablesTbl = $conn->query("SHOW TABLES LIKE 'payables'");
            if ($resPayablesTbl instanceof mysqli_result && $resPayablesTbl->num_rows > 0) {
                $hasPayables = true;
            }
            if ($resPayablesTbl instanceof mysqli_result) {
                $resPayablesTbl->free();
            }
            if ($hasPayables) {
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM payables WHERE status = 'pending' AND created_by = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $pendingTodoCount += (int)($row['c'] ?? 0);
                    $stmt->close();
                }
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM payables WHERE status = 'pending' AND created_by = ? AND expected_pay_date < CURDATE()");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $overdueTodoCount += (int)($row['c'] ?? 0);
                    $stmt->close();
                }
            }
            $hasReceivables = false;
            $resReceivablesTbl = $conn->query("SHOW TABLES LIKE 'receivables'");
            if ($resReceivablesTbl instanceof mysqli_result && $resReceivablesTbl->num_rows > 0) {
                $hasReceivables = true;
            }
            if ($resReceivablesTbl instanceof mysqli_result) {
                $resReceivablesTbl->free();
            }
            if ($hasReceivables) {
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM receivables WHERE status = 'pending' AND created_by = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $pendingTodoCount += (int)($row['c'] ?? 0);
                    $stmt->close();
                }
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM receivables WHERE status = 'pending' AND created_by = ? AND expected_receive_date < CURDATE()");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $overdueTodoCount += (int)($row['c'] ?? 0);
                    $stmt->close();
                }
            }
        }
    } catch (Throwable $e) {
        $pendingTodoCount = 0;
        $overdueTodoCount = 0;
    }
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($__htmlLang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UDA-V2 内部管理系统</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --bg: #f4f6fb;
            --text: #111827;
            --border: #e5e7eb;
            --primary: #2563eb;
            --sidebar-w: 176px;
            --topbar-h: 54px;
            --shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
            --sidebar-yellow: #ffc300;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, "Microsoft YaHei", "SimHei", sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
        }
        body { overflow-x: hidden; }
        a { color: inherit; text-decoration: none; }

        body, button, input, select, textarea, a, div, span, p, li, h1, h2, h3, h4, h5, h6 {
            font-family: Arial, "Microsoft YaHei", "SimHei", sans-serif;
        }

        /* 顶栏：与旧版 global-topbar 一致，固定全宽 */
        .global-topbar {
            height: var(--topbar-h);
            background: var(--sidebar-yellow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 14px;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .global-topbar-left {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        /* 窄屏：侧栏收进抽屉，用顶栏按钮打开 */
        .nav-burger {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 36px;
            padding: 0;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.12);
            background: #fff;
            color: #111827;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
        }
        .nav-burger:hover { background: #fff8db; }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            top: var(--topbar-h);
            background: rgba(15, 23, 42, 0.45);
            z-index: 880;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
        }
        body.layout-nav-open .sidebar-backdrop {
            display: block;
            opacity: 1;
            pointer-events: auto;
        }
        @media (max-width: 900px) {
            .nav-burger { display: inline-flex; }
            .sidebar {
                width: min(288px, 88vw);
                transform: translateX(-102%);
                transition: transform 0.22s ease;
                box-shadow: none;
            }
            body.layout-nav-open .sidebar {
                transform: translateX(0);
                box-shadow: 6px 0 24px rgba(0,0,0,0.12);
            }
            .main {
                margin-left: 0;
                padding: 12px;
            }
            .global-topbar-right {
                flex-wrap: wrap;
                justify-content: flex-end;
                row-gap: 6px;
                max-width: calc(100vw - 120px);
            }
        }
        .global-topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #111827;
            font-weight: 600;
        }
        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            background: #fff;
            color: #111827;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(0,0,0,0.08);
        }
        .logout-link:hover { background: #fff8db; }
        .profile-link { background: #fff; }
        .profile-link:hover { background: #fff8db; }
        .topbar-notify-btn {
            height: 32px;
            min-height: auto;
            padding: 0 10px;
            border-radius: 8px;
            background: #fff;
            color: #111827;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .topbar-pending-btn {
            height: 32px;
            min-height: auto;
            padding: 0 10px;
            border-radius: 8px;
            background: #fff;
            color: #111827;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .topbar-pending-btn.has-pending {
            animation: pendingPulse 1.2s ease-in-out infinite;
            border-color: #dc2626;
            color: #991b1b;
        }
        @keyframes pendingPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.0); }
            50% { box-shadow: 0 0 0 5px rgba(220, 38, 38, 0.22); }
        }
        .topbar-pending-dot {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            display: inline-block;
        }
        .topbar-overdue-dot {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #7f1d1d;
            color: #fff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            display: inline-block;
        }
        .topbar-notify-dot {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            display: none;
        }
        .notify-toast-wrap {
            position: fixed;
            top: calc(var(--topbar-h) + 10px);
            right: 12px;
            z-index: 1200;
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: min(360px, calc(100vw - 24px));
        }
        .notify-toast {
            background: #111827;
            color: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.24);
            padding: 10px 12px;
        }
        .notify-toast-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
            line-height: 1.25;
        }
        .notify-toast-content {
            font-size: 12px;
            color: #d1d5db;
            line-height: 1.35;
            margin-bottom: 6px;
        }
        .notify-toast-time {
            font-size: 11px;
            color: #9ca3af;
        }
        form.inline-locale { display: inline; margin: 0; padding: 0; }
        .global-topbar button.locale-switch {
            height: 32px;
            padding: 0 10px;
            min-height: auto;
            border-radius: 8px;
            background: #fff;
            color: #111827;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(0,0,0,0.08);
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .global-topbar button.locale-switch:hover { background: #fff8db; }

        .layout { display: block; min-height: 100vh; }

        /* 侧栏：与旧版固定于顶栏下方 */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--sidebar-yellow) 0%, var(--sidebar-yellow) 100%);
            color: #111827;
            padding: 14px 0;
            box-shadow: 2px 0 12px rgba(0,0,0,0.05);
            position: fixed;
            top: var(--topbar-h);
            left: 0;
            bottom: 0;
            z-index: 900;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar .menu { padding: 0 10px; }

        .menu-toggle {
            width: 100%;
            border: none;
            background: transparent;
            text-align: left;
            padding: 12px 14px;
            margin: 4px 0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #222;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-toggle:hover { background: rgba(0,0,0,0.06); }
        .menu-toggle.open,
        .menu-toggle.menu-parent-active {
            background: #0f172a;
            color: #fff;
        }
        .menu-toggle .arrow { font-size: 12px; transition: transform 0.2s ease; color: inherit; }
        .menu-toggle.open .arrow,
        .menu-toggle.menu-parent-active .arrow {
            color: #fff;
            transform: rotate(180deg);
        }

        .menu-link {
            display: block;
            padding: 10px 14px;
            margin: 4px 0;
            border-radius: 8px;
            color: #222;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .menu-link:hover { background: rgba(0,0,0,0.05); color: #222; }
        .menu-link.active {
            background: #111827;
            color: #fff;
            font-weight: 600;
        }

        .submenu {
            display: none;
            padding-left: 10px;
            margin-top: 4px;
        }
        .submenu.open { display: block; }

        .sub-toggle {
            width: 100%;
            border: none;
            background: transparent;
            text-align: left;
            padding: 10px 14px;
            margin: 4px 0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #222;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sub-toggle:hover { background: rgba(0,0,0,0.06); }
        .sub-toggle.open {
            background: #0f172a;
            color: #fff;
        }
        .sub-toggle .arrow { font-size: 12px; transition: transform 0.2s ease; }
        .sub-toggle.open .arrow { transform: rotate(180deg); color: #fff; }

        .submenu-l3 { margin-left: 8px; display: none; padding-left: 6px; }
        .submenu-l3.open { display: block; }
        .menu-link.level-3 { font-size: 14px; padding: 9px 12px; }

        /* 主区：与旧版 .main + .main-inner */
        .main {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 18px;
            min-width: 0;
        }
        .main-inner { max-width: 1400px; }
        .wrap { width: 100%; }

        .card {
            background: #fff;
            border-radius: 16px;
            border: none;
            padding: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
        }
        .muted { color: #6b7280; font-size: 12px; }
        /* 表单字段说明：与侧栏菜单同级字号、粗体；不含内含勾选框的 label */
        .card label:not(:has(input)) {
            font-size: 13px;
            font-weight: 700;
            color: #111;
            display: block;
            margin-bottom: 2px;
        }
        /* 与 label+br+input 写法并存时，去掉多余换行占高 */
        .card label:not(:has(input)) + br {
            display: none;
        }
        /* 工具栏内「标签 + 控件」横排：不强制换行 */
        .card .toolbar label:not(:has(input)) {
            display: inline;
            margin-bottom: 0;
            margin-right: 6px;
        }
        input, select, textarea {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 8px 10px;
            background: #fff;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            line-height: 1.25;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }
        /* 主按钮：对称 padding + flex 居中（避免固定 height 与中文字体基线导致视觉上偏上） */
        button, .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 14px;
            border: none;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
            cursor: pointer;
            text-decoration: none;
            box-sizing: border-box;
        }
        button:hover, .btn:hover { background: #1d4ed8; }

        /* 表格内多按钮横排对齐 */
        .cell-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        /* 表单内「主按钮 + 次按钮」横排 */
        .inline-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        /* 与用户管理页一致：主标题 h2 ≈ 1.5×正文字号，区块标题 h3 */
        .main .card h2 {
            margin: 0 0 8px 0;
            font-size: 21px;
            font-weight: 700;
            color: #111827;
            line-height: 1.25;
        }
        .main .card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            line-height: 1.25;
        }
        .page-title { margin: 0 0 4px 0; }
        /* 全站：删除页面标题下方说明小字 */
        .card h2 + .muted,
        .card .page-title + .muted {
            display: none !important;
        }
        .toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .form-grid { display:grid; grid-template-columns:180px 1fr; gap:10px; }
        .form-grid input,
        .form-grid select,
        .form-grid textarea { width: 100%; }
        .form-grid input[type="checkbox"],
        .form-grid input[type="radio"] {
            width: auto;
            min-width: 0;
            margin: 0;
            padding: 0;
        }
        .form-full { grid-column: 1 / -1; }
        .data-table { width:100%; border-collapse:collapse; font-size:12px; }
        .data-table th { text-align:left; padding:8px; border-bottom:1px solid #e5e7eb; background:#f8fafc; }
        .data-table td { padding:8px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
        /* 系统管理列表等：整表 th/td 垂直居中，行高一致时更整齐 */
        .table-valign-middle th,
        .table-valign-middle td { vertical-align: middle; }
        .data-table tbody tr:hover td { background:#f8fbff; }
        .chip { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; background:#eef2ff; color:#1e3a8a; }
        /* 列表格长文本：单行省略，点击见全站 JS 深色气泡 */
        td.cell-tip {
            max-width: 14rem;
            vertical-align: middle;
        }
        .cell-tip-trigger {
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            line-height: 1.45;
            color: inherit;
        }
        .cell-tip-trigger:hover { text-decoration: underline; }
        .ud-cell-tip {
            position: fixed;
            z-index: 10050;
            display: none;
            max-width: min(420px, calc(100vw - 24px));
            padding: 10px 32px 10px 12px;
            background: #303133;
            color: #f9fafb;
            border-radius: 4px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.28);
            font-size: 13px;
            line-height: 1.5;
            word-break: break-word;
            white-space: normal;
        }
        .ud-cell-tip-close {
            position: absolute;
            top: 4px;
            right: 6px;
            width: 22px;
            height: 22px;
            padding: 0;
            border: none;
            border-radius: 4px;
            background: transparent;
            color: #e5e7eb;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
        }
        .ud-cell-tip-close:hover { background: rgba(255, 255, 255, 0.12); color: #fff; }
        .ud-cell-tip-arrow {
            position: absolute;
            left: 50%;
            bottom: -6px;
            margin-left: -6px;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #303133;
        }
        .ud-cell-tip--below .ud-cell-tip-arrow {
            bottom: auto;
            top: -6px;
            border-top: none;
            border-bottom: 6px solid #303133;
        }
        .chip-mark-modified { background: #fee2e2; color: #b91c1c; }
        .chip-mark-new { background: #d1fae5; color: #047857; }
        .stat-grid { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)); gap:8px; }
        .stat-item { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:8px; }
        .stat-item .label { font-size:11px; color:#6b7280; margin-bottom:4px; }
        .stat-item .value { font-size:18px; font-weight:800; color:#111827; }
        /* 弹层内标题与主内容区 h3 一致 */
        #userDetailModal .modal-inner h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            line-height: 1.25;
        }
        /* 全站操作按钮统一 */
        .btn-action-edit { background: #0b3a82 !important; color: #fff !important; }
        .btn-action-delete { background: #b91c1c !important; color: #fff !important; }
        .btn-action-info { background: #374151 !important; color: #fff !important; }
        .btn-action-forward { background: #6b7f2a !important; color: #fff !important; }
        /* 派送模块表格行：圆形 E / D / i（派送客户操作列为基准，各页共用） */
        .dispatch-row-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
            vertical-align: middle;
        }
        .btn.btn-dispatch-round {
            width: 28px;
            height: 28px;
            min-width: 28px !important;
            min-height: 28px !important;
            padding: 0 !important;
            border-radius: 999px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            box-sizing: border-box;
        }
        .btn.btn-dispatch-round.btn-dispatch-round--edit,
        .btn.btn-dispatch-round.btn-dispatch-round--info {
            background: #0b3a82 !important;
            color: #fff !important;
        }
        .btn.btn-dispatch-round.btn-dispatch-round--info {
            font-size: 13px !important;
            font-weight: 600 !important;
        }
        .btn.btn-dispatch-round.btn-dispatch-round--delete {
            background: #b91c1c !important;
            color: #fff !important;
        }
        .btn.btn-dispatch-round.btn-dispatch-round--edit:hover,
        .btn.btn-dispatch-round.btn-dispatch-round--info:hover {
            background: #082a66 !important;
            color: #fff !important;
        }
        .btn.btn-dispatch-round.btn-dispatch-round--delete:hover {
            background: #991b1b !important;
            color: #fff !important;
        }
    </style>
</head>
<body>
<div class="global-topbar">
    <div class="global-topbar-left">
        <button type="button" class="nav-burger" id="navBurger" aria-label="打开菜单">≡</button>
        <span class="global-topbar-title"><?php echo htmlspecialchars(t('app.name', 'UDA-V2')); ?></span>
    </div>
    <div class="global-topbar-right">
        <form class="inline-locale" method="post" action="/locale">
            <input type="hidden" name="app_locale" value="zh-CN">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(function_exists('locale_redirect_current_uri') ? locale_redirect_current_uri() : '/', ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="locale-switch"><?php echo htmlspecialchars(t('lang.switch.zh', '中文')); ?></button>
        </form>
        <form class="inline-locale" method="post" action="/locale">
            <input type="hidden" name="app_locale" value="th-TH">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(function_exists('locale_redirect_current_uri') ? locale_redirect_current_uri() : '/', ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="locale-switch"><?php echo htmlspecialchars(t('lang.switch.th', 'ไทย')); ?></button>
        </form>
        <span><?php echo htmlspecialchars((string)(($_SESSION['auth_full_name'] ?? '') !== '' ? $_SESSION['auth_full_name'] : ($_SESSION['auth_username'] ?? ''))); ?></span>
        <button
            type="button"
            class="topbar-pending-btn <?php echo $pendingTodoCount > 0 ? 'has-pending' : ''; ?>"
            id="topbarPendingBtn"
        >
            <span>待处理</span>
            <?php if ($overdueTodoCount > 0): ?>
                <span class="topbar-overdue-dot" title="逾期事项"><?php echo htmlspecialchars((string)($overdueTodoCount > 99 ? '99+' : $overdueTodoCount)); ?></span>
            <?php endif; ?>
            <span class="topbar-pending-dot"><?php echo htmlspecialchars((string)($pendingTodoCount > 99 ? '99+' : $pendingTodoCount)); ?></span>
        </button>
        <button type="button" class="topbar-notify-btn" id="topbarNotifyBtn">
            <span>通知</span>
            <span class="topbar-notify-dot" id="topbarNotifyDot">0</span>
        </button>
        <a class="logout-link profile-link" href="/profile"><?php echo htmlspecialchars(t('nav.profile', '个人设置')); ?></a>
        <a class="logout-link" href="/logout"><?php echo htmlspecialchars(t('nav.logout', '退出登录')); ?></a>
    </div>
</div>
<div id="notifyToastWrap" class="notify-toast-wrap"></div>
<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$permissionScope = $_GET['scope'] ?? 'page';
?>
<div class="layout">
    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
    <aside class="sidebar">
        <div class="menu">
        <?php $canDashboard = function_exists('hasPermissionKey') && hasPermissionKey('menu.dashboard'); ?>
        <?php $canCalendarMenu = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.calendar', 'menu.dashboard']); ?>
        <?php $canCalendarCreate = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['calendar.events.create', 'dashboard.calendar.manage']); ?>
        <?php $canCalendarEvents = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['calendar.events.view', 'dashboard.calendar.manage']); ?>
        <?php $canDispatchMenu = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.dispatch', 'menu.dashboard']); ?>
        <?php $canDispatchHub = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.manage', 'dispatch.consigning_clients.view', 'dispatch.delivery_customers.view', 'dispatch.waybills.view', 'dispatch.waybills.import', 'dispatch.waybills.edit']); ?>
        <?php $canDispatchConsigning = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.consigning_clients.view', 'dispatch.manage']); ?>
        <?php $canDispatchDelivery = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.delivery_customers.view', 'dispatch.manage']); ?>
        <?php $canDispatchWaybills = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.waybills.view', 'dispatch.waybills.import', 'dispatch.manage']); ?>
        <?php $canDispatchOrderImport = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.waybills.import', 'dispatch.manage']); ?>
        <?php $canDispatchPackageOps = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.waybills.edit', 'dispatch.manage']); ?>
        <?php $canDispatchForwarding = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['dispatch.forwarding.view', 'dispatch.forwarding.package.create', 'dispatch.forwarding.customer.manage', 'dispatch.manage']); ?>
        <?php $canFinanceMenu = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.finance', 'menu.dashboard']); ?>
        <?php $canTransactionsCreate = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.transactions.create', 'finance.manage']); ?>
        <?php $canTransactionsList = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.transactions.view', 'finance.manage']); ?>
        <?php $canPayablesCreate = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.payables.create', 'finance.manage']); ?>
        <?php $canPayablesList = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.payables.view', 'finance.manage']); ?>
        <?php $canReceivablesCreate = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.receivables.create', 'finance.manage']); ?>
        <?php $canReceivablesList = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.receivables.view', 'finance.manage']); ?>
        <?php $canFinanceAccounts = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.accounts.view', 'finance.manage']); ?>
        <?php $canFinanceCategories = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.categories.view', 'finance.manage']); ?>
        <?php $canFinanceParties = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.parties.view', 'finance.manage']); ?>
        <?php $canFinanceReports = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.reports.view', 'finance.manage']); ?>
        <?php $canArCustomers = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.ar.customers', 'finance.manage']); ?>
        <?php $canArChargesCreate = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.ar.charges.create', 'finance.manage']); ?>
        <?php $canArChargesList = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.ar.charges.view', 'finance.manage']); ?>
        <?php $canArInvoices = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.ar.invoices.view', 'finance.ar.invoices.create', 'finance.manage']); ?>
        <?php $canArLedger = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['finance.ar.ledger.view', 'finance.manage']); ?>
        <?php $canUsers = function_exists('hasPermissionKey') && hasPermissionKey('menu.users'); ?>
        <?php $canRoles = function_exists('hasPermissionKey') && hasPermissionKey('menu.roles'); ?>
        <?php $canPermissions = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.permissions', 'menu.roles']); ?>
        <?php $canNotifications = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.notifications', 'menu.roles']); ?>
        <?php $canLogs = function_exists('hasAnyPermissionKey') && hasAnyPermissionKey(['menu.logs', 'menu.roles']); ?>

        <?php if ($canDashboard): ?>
            <a class="menu-link <?php echo $currentPath === '/' ? 'active' : ''; ?>" href="/"><?php echo htmlspecialchars(t('nav.dashboard.home', '首页')); ?></a>
        <?php endif; ?>

        <?php if ($canCalendarMenu && ($canCalendarCreate || $canCalendarEvents)): ?>
            <button class="menu-toggle" type="button" data-target="menu-calendar">
                <span><?php echo htmlspecialchars(t('nav.calendar', '行事历管理')); ?></span><span class="arrow">▾</span>
            </button>
            <div id="menu-calendar" class="submenu">
                <?php if ($canCalendarCreate): ?>
                    <a class="menu-link <?php echo $currentPath === '/calendar/create' ? 'active' : ''; ?>" href="/calendar/create"><?php echo htmlspecialchars(t('nav.calendar.create', '新增事件')); ?></a>
                <?php endif; ?>
                <?php if ($canCalendarEvents): ?>
                    <a class="menu-link <?php echo $currentPath === '/calendar/events' ? 'active' : ''; ?>" href="/calendar/events"><?php echo htmlspecialchars(t('nav.calendar.events', '事件列表')); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($canDispatchMenu && $canDispatchHub): ?>
            <button class="menu-toggle" type="button" data-target="menu-dispatch">
                <span>派送业务</span><span class="arrow">▾</span>
            </button>
            <div id="menu-dispatch" class="submenu">
                <?php if ($canDispatchWaybills): ?>
                    <a class="menu-link <?php echo $currentPath === '/dispatch' ? 'active' : ''; ?>" href="/dispatch">订单查询</a>
                <?php endif; ?>
                <?php if ($canDispatchOrderImport): ?>
                    <a class="menu-link <?php echo $currentPath === '/dispatch/order-import' ? 'active' : ''; ?>" href="/dispatch/order-import">订单导入</a>
                <?php endif; ?>
                <?php if ($canDispatchPackageOps): ?>
                    <a class="menu-link <?php echo $currentPath === '/dispatch/package-ops' ? 'active' : ''; ?>" href="/dispatch/package-ops">货件操作</a>
                <?php endif; ?>
                <?php if ($canDispatchForwarding): ?>
                    <?php $dispatchForwardingOpen = in_array($currentPath, ['/dispatch/forwarding/packages', '/dispatch/forwarding/customers', '/dispatch/forwarding/records'], true); ?>
                    <button class="sub-toggle <?php echo $dispatchForwardingOpen ? 'open' : ''; ?>" type="button" data-target="menu-dispatch-forwarding">
                        <span>转发操作</span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-dispatch-forwarding" class="submenu-l3 <?php echo $dispatchForwardingOpen ? 'open' : ''; ?>">
                        <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/forwarding/packages' ? 'active' : ''; ?>" href="/dispatch/forwarding/packages">转发合包</a>
                        <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/forwarding/customers' ? 'active' : ''; ?>" href="/dispatch/forwarding/customers">客户维护</a>
                        <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/forwarding/records' ? 'active' : ''; ?>" href="/dispatch/forwarding/records">查询记录</a>
                    </div>
                <?php endif; ?>
                <?php if ($canDispatchConsigning): ?>
                    <a class="menu-link <?php echo $currentPath === '/dispatch/consigning-clients' ? 'active' : ''; ?>" href="/dispatch/consigning-clients">委托客户</a>
                <?php endif; ?>
                <?php if ($canDispatchDelivery): ?>
                    <a class="menu-link <?php echo $currentPath === '/dispatch/delivery-customers' ? 'active' : ''; ?>" href="/dispatch/delivery-customers">派送客户</a>
                <?php endif; ?>
                <?php $dispatchOpsOpen = in_array($currentPath, ['/dispatch/ops/delivery-list', '/dispatch/ops/binding-list', '/dispatch/ops/create-delivery', '/dispatch/ops/delivery-docs'], true); ?>
                <button class="sub-toggle <?php echo $dispatchOpsOpen ? 'open' : ''; ?>" type="button" data-target="menu-dispatch-ops">
                    <span>派送操作</span><span class="arrow">▾</span>
                </button>
                <div id="menu-dispatch-ops" class="submenu-l3 <?php echo $dispatchOpsOpen ? 'open' : ''; ?>">
                    <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/ops/delivery-list' ? 'active' : ''; ?>" href="/dispatch/ops/delivery-list">派送列表</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/ops/binding-list' ? 'active' : ''; ?>" href="/dispatch/ops/binding-list">绑带列表</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/ops/create-delivery' ? 'active' : ''; ?>" href="/dispatch/ops/create-delivery">生成派送单</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/ops/delivery-docs' ? 'active' : ''; ?>" href="/dispatch/ops/delivery-docs">派送单列表</a>
                </div>

                <?php $dispatchAccountingOpen = ($currentPath === '/dispatch/accounting/list'); ?>
                <button class="sub-toggle <?php echo $dispatchAccountingOpen ? 'open' : ''; ?>" type="button" data-target="menu-dispatch-accounting">
                    <span>账务处理</span><span class="arrow">▾</span>
                </button>
                <div id="menu-dispatch-accounting" class="submenu-l3 <?php echo $dispatchAccountingOpen ? 'open' : ''; ?>">
                    <a class="menu-link level-3 <?php echo $currentPath === '/dispatch/accounting/list' ? 'active' : ''; ?>" href="/dispatch/accounting/list">账务列表</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canDispatchMenu): ?>
            <button class="menu-toggle" type="button" data-target="menu-uda-express">
                <span>UDA快件</span><span class="arrow">▾</span>
            </button>
            <div id="menu-uda-express" class="submenu">
                <?php $udaIssueOpen = in_array($currentPath, ['/uda/issues/list', '/uda/issues/create', '/uda/issues/handle-methods', '/uda/issues/locations', '/uda/issues/reasons'], true); ?>
                <button class="sub-toggle <?php echo $udaIssueOpen ? 'open' : ''; ?>" type="button" data-target="menu-uda-issue">
                    <span>问题订单</span><span class="arrow">▾</span>
                </button>
                <div id="menu-uda-issue" class="submenu-l3 <?php echo $udaIssueOpen ? 'open' : ''; ?>">
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/issues/list' ? 'active' : ''; ?>" href="/uda/issues/list">问题订单列表</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/issues/create' ? 'active' : ''; ?>" href="/uda/issues/create">问题订单录入</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/issues/locations' ? 'active' : ''; ?>" href="/uda/issues/locations">地点管理</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/issues/reasons' ? 'active' : ''; ?>" href="/uda/issues/reasons">问题原因管理</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/issues/handle-methods' ? 'active' : ''; ?>" href="/uda/issues/handle-methods">处理方式管理</a>
                </div>

                <?php $udaExpressOpen = in_array($currentPath, ['/uda/express/query', '/uda/express/receive', '/uda/express/forward-packages', '/uda/express/forward-query'], true); ?>
                <button class="sub-toggle <?php echo $udaExpressOpen ? 'open' : ''; ?>" type="button" data-target="menu-uda-express-sub">
                    <span>快件收发</span><span class="arrow">▾</span>
                </button>
                <div id="menu-uda-express-sub" class="submenu-l3 <?php echo $udaExpressOpen ? 'open' : ''; ?>">
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/express/query' ? 'active' : ''; ?>" href="/uda/express/query">快件查询</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/express/receive' ? 'active' : ''; ?>" href="/uda/express/receive">收件录入</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/express/forward-packages' ? 'active' : ''; ?>" href="/uda/express/forward-packages">转发合包</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/express/forward-query' ? 'active' : ''; ?>" href="/uda/express/forward-query">转发查询</a>
                </div>

                <?php $udaBatchOpen = in_array($currentPath, ['/uda/batches/list', '/uda/batches/create', '/uda/batches/edit'], true); ?>
                <button class="sub-toggle <?php echo $udaBatchOpen ? 'open' : ''; ?>" type="button" data-target="menu-uda-batches">
                    <span>仓内操作</span><span class="arrow">▾</span>
                </button>
                <div id="menu-uda-batches" class="submenu-l3 <?php echo $udaBatchOpen ? 'open' : ''; ?>">
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/batches/list' ? 'active' : ''; ?>" href="/uda/batches/list">集包列表</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/batches/create' ? 'active' : ''; ?>" href="/uda/batches/create">集包录入</a>
                </div>

                <?php $udaWarehouseOpen = in_array($currentPath, ['/uda/warehouse/bundles', '/uda/warehouse/create-bundle', '/uda/warehouse/batch-view', '/uda/warehouse/batch-edit'], true); ?>
                <button class="sub-toggle <?php echo $udaWarehouseOpen ? 'open' : ''; ?>" type="button" data-target="menu-uda-warehouse">
                    <span>批次操作</span><span class="arrow">▾</span>
                </button>
                <div id="menu-uda-warehouse" class="submenu-l3 <?php echo $udaWarehouseOpen ? 'open' : ''; ?>">
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/warehouse/bundles' ? 'active' : ''; ?>" href="/uda/warehouse/bundles">批次列表</a>
                    <a class="menu-link level-3 <?php echo $currentPath === '/uda/warehouse/create-bundle' ? 'active' : ''; ?>" href="/uda/warehouse/create-bundle">批次录入</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canDispatchMenu): ?>
            <a class="menu-link <?php echo $currentPath === '/warehouse' ? 'active' : ''; ?>" href="/warehouse">仓储管理</a>
        <?php endif; ?>

        <?php
        $financeRecordsOpen = in_array($currentPath, ['/finance/transactions/create', '/finance/transactions/list', '/finance/transactions/edit'], true);
        $financePayablesOpen = in_array($currentPath, ['/finance/payables/create', '/finance/payables/list', '/finance/payables/settle'], true);
        $financeReceivablesOpen = in_array($currentPath, ['/finance/receivables/create', '/finance/receivables/list', '/finance/receivables/settle'], true);
        $financeArOpen = in_array($currentPath, ['/finance/ar/customers', '/finance/ar/billing-schemes', '/finance/ar/charges/create', '/finance/ar/charges/list', '/finance/ar/charges/options', '/finance/ar/invoices/list', '/finance/ar/invoices/view', '/finance/ar/invoices/print-unpaid', '/finance/ar/ledger'], true);
        $financeMaintenanceOpen = in_array($currentPath, ['/finance/accounts', '/finance/categories', '/finance/parties'], true);
        ?>
        <?php if ($canFinanceMenu && ($canTransactionsCreate || $canTransactionsList || $canPayablesCreate || $canPayablesList || $canReceivablesCreate || $canReceivablesList || $canFinanceAccounts || $canFinanceCategories || $canFinanceParties || $canFinanceReports || $canArCustomers || $canArChargesCreate || $canArChargesList || $canArInvoices || $canArLedger)): ?>
            <button class="menu-toggle" type="button" data-target="menu-finance">
                <span>财务管理</span><span class="arrow">▾</span>
            </button>
            <div id="menu-finance" class="submenu">
                <?php if ($canTransactionsCreate || $canTransactionsList): ?>
                    <button class="sub-toggle <?php echo $financeRecordsOpen ? 'open' : ''; ?>" type="button" data-target="menu-finance-records">
                        <span>财务记录</span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-finance-records" class="submenu-l3 <?php echo $financeRecordsOpen ? 'open' : ''; ?>">
                        <?php if ($canTransactionsCreate): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/transactions/create' ? 'active' : ''; ?>" href="/finance/transactions/create">新增财务记录</a>
                        <?php endif; ?>
                        <?php if ($canTransactionsList): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/transactions/list' || $currentPath === '/finance/transactions/edit' ? 'active' : ''; ?>" href="/finance/transactions/list">财务记录列表</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canPayablesCreate || $canPayablesList): ?>
                    <button class="sub-toggle <?php echo $financePayablesOpen ? 'open' : ''; ?>" type="button" data-target="menu-finance-payables">
                        <span>待付款</span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-finance-payables" class="submenu-l3 <?php echo $financePayablesOpen ? 'open' : ''; ?>">
                        <?php if ($canPayablesCreate): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/payables/create' ? 'active' : ''; ?>" href="/finance/payables/create">新增待付款</a>
                        <?php endif; ?>
                        <?php if ($canPayablesList): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/payables/list' || $currentPath === '/finance/payables/settle' ? 'active' : ''; ?>" href="/finance/payables/list">待付款列表</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canReceivablesCreate || $canReceivablesList): ?>
                    <button class="sub-toggle <?php echo $financeReceivablesOpen ? 'open' : ''; ?>" type="button" data-target="menu-finance-receivables">
                        <span>待收款</span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-finance-receivables" class="submenu-l3 <?php echo $financeReceivablesOpen ? 'open' : ''; ?>">
                        <?php if ($canReceivablesCreate): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/receivables/create' ? 'active' : ''; ?>" href="/finance/receivables/create">新增待收款</a>
                        <?php endif; ?>
                        <?php if ($canReceivablesList): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/receivables/list' || $currentPath === '/finance/receivables/settle' ? 'active' : ''; ?>" href="/finance/receivables/list">待收款列表</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canFinanceReports): ?>
                    <a class="menu-link <?php echo $currentPath === '/finance/reports/overview' || $currentPath === '/finance/reports/detail' ? 'active' : ''; ?>" href="/finance/reports/overview">财务报表</a>
                <?php endif; ?>

                <?php if ($canArCustomers || $canArChargesCreate || $canArChargesList || $canArInvoices || $canArLedger): ?>
                    <button class="sub-toggle <?php echo $financeArOpen ? 'open' : ''; ?>" type="button" data-target="menu-finance-ar">
                        <span>应收账单</span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-finance-ar" class="submenu-l3 <?php echo $financeArOpen ? 'open' : ''; ?>">
                        <?php if ($canArCustomers): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/ar/customers' ? 'active' : ''; ?>" href="/finance/ar/customers">客户计费档案</a>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/ar/billing-schemes' ? 'active' : ''; ?>" href="/finance/ar/billing-schemes">计费方式维护</a>
                        <?php endif; ?>
                        <?php if ($canArChargesCreate): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/ar/charges/create' ? 'active' : ''; ?>" href="/finance/ar/charges/create">新增费用记录</a>
                        <?php endif; ?>
                        <?php if ($canArChargesList): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/ar/charges/list' ? 'active' : ''; ?>" href="/finance/ar/charges/list">费用记录列表</a>
                        <?php endif; ?>
                        <?php if ($canArInvoices): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/ar/invoices/list' || $currentPath === '/finance/ar/invoices/view' ? 'active' : ''; ?>" href="/finance/ar/invoices/list">账单列表</a>
                        <?php endif; ?>
                        <?php if ($canArLedger): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/ar/ledger' ? 'active' : ''; ?>" href="/finance/ar/ledger">应收台账</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canFinanceAccounts || $canFinanceCategories || $canFinanceParties): ?>
                    <button class="sub-toggle <?php echo $financeMaintenanceOpen ? 'open' : ''; ?>" type="button" data-target="menu-finance-maintenance">
                        <span>财务管理维护</span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-finance-maintenance" class="submenu-l3 <?php echo $financeMaintenanceOpen ? 'open' : ''; ?>">
                        <?php if ($canFinanceAccounts): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/accounts' ? 'active' : ''; ?>" href="/finance/accounts">账户管理</a>
                        <?php endif; ?>
                        <?php if ($canFinanceCategories): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/categories' ? 'active' : ''; ?>" href="/finance/categories">类目管理</a>
                        <?php endif; ?>
                        <?php if ($canFinanceParties): ?>
                            <a class="menu-link level-3 <?php echo $currentPath === '/finance/parties' ? 'active' : ''; ?>" href="/finance/parties">收付款对象管理</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($canUsers || $canRoles || $canPermissions || $canNotifications || $canLogs): ?>
            <button class="menu-toggle" type="button" data-target="menu-system">
                <span><?php echo htmlspecialchars(t('nav.system', '系统管理')); ?></span><span class="arrow">▾</span>
            </button>
            <div id="menu-system" class="submenu">
                <?php if ($canUsers): ?>
                    <a class="menu-link <?php echo $currentPath === '/system/users' ? 'active' : ''; ?>" href="/system/users"><?php echo htmlspecialchars(t('nav.system.users', '用户管理')); ?></a>
                <?php endif; ?>
                <?php if ($canRoles): ?>
                    <a class="menu-link <?php echo $currentPath === '/system/roles' ? 'active' : ''; ?>" href="/system/roles"><?php echo htmlspecialchars(t('nav.system.roles', '角色管理')); ?></a>
                <?php endif; ?>
                <?php if ($canPermissions): ?>
                    <?php $permissionMenuOpen = ($currentPath === '/system/permissions'); ?>
                    <button class="sub-toggle <?php echo $permissionMenuOpen ? 'open' : ''; ?>" type="button" data-target="menu-system-permissions">
                        <span><?php echo htmlspecialchars(t('nav.system.permissions', '权限管理')); ?></span><span class="arrow">▾</span>
                    </button>
                    <div id="menu-system-permissions" class="submenu-l3 <?php echo $permissionMenuOpen ? 'open' : ''; ?>">
                        <a class="menu-link level-3 <?php echo ($currentPath === '/system/permissions' && $permissionScope === 'page') ? 'active' : ''; ?>" href="/system/permissions?scope=page"><?php echo htmlspecialchars(t('nav.system.page_permissions', '页面权限')); ?></a>
                        <a class="menu-link level-3 <?php echo ($currentPath === '/system/permissions' && $permissionScope === 'action') ? 'active' : ''; ?>" href="/system/permissions?scope=action"><?php echo htmlspecialchars(t('nav.system.action_permissions', '操作权限')); ?></a>
                    </div>
                <?php endif; ?>
                <?php if ($canNotifications): ?>
                    <a class="menu-link <?php echo $currentPath === '/system/notifications' ? 'active' : ''; ?>" href="/system/notifications"><?php echo htmlspecialchars(t('nav.system.notifications', '通知管理')); ?></a>
                <?php endif; ?>
                <?php if ($canLogs): ?>
                    <a class="menu-link <?php echo $currentPath === '/system/logs' ? 'active' : ''; ?>" href="/system/logs"><?php echo htmlspecialchars(t('nav.system.logs', '日志管理')); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </aside>
    <main class="main">
        <div class="main-inner">
            <div class="wrap">
                <?php
                require_once __DIR__ . '/../../inc/view_cell_tip.php';
                require $contentView;
                ?>
            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('.menu-toggle');
    const keyPrefix = 'uda_v2_sidebar_';
    const singleOpenKey = keyPrefix + 'single_open_target';

    function closeAll() {
        toggles.forEach(function (btn) {
            const id = btn.getAttribute('data-target');
            const panel = document.getElementById(id);
            if (!panel) return;
            panel.classList.remove('open');
            btn.classList.remove('open');
            btn.classList.remove('menu-parent-active');
            localStorage.setItem(keyPrefix + id, 'closed');
        });
    }

    toggles.forEach(function (toggle) {
        const targetId = toggle.getAttribute('data-target');
        const submenu = document.getElementById(targetId);
        if (!submenu) return;

        const hasActive = submenu.querySelector('.menu-link.active') !== null;
        const saved = localStorage.getItem(keyPrefix + targetId);
        const shouldOpen = saved === null ? hasActive : saved === 'open';

        if (shouldOpen) {
            submenu.classList.add('open');
            toggle.classList.add('open');
        }
        if (submenu.querySelector('.menu-link.active')) {
            toggle.classList.add('menu-parent-active');
        } else {
            toggle.classList.remove('menu-parent-active');
        }

        toggle.addEventListener('click', function () {
            const willOpen = !submenu.classList.contains('open');
            closeAll();
            if (willOpen) {
                submenu.classList.add('open');
                toggle.classList.add('open');
                if (submenu.querySelector('.menu-link.active')) {
                    toggle.classList.add('menu-parent-active');
                }
                localStorage.setItem(keyPrefix + targetId, 'open');
                localStorage.setItem(singleOpenKey, targetId);
            } else {
                toggle.classList.remove('menu-parent-active');
                localStorage.removeItem(singleOpenKey);
            }
        });
    });

    // 首次加载后：严格单开，仅保留一个展开项（优先当前页，其次上次打开）
    const activeToggle = Array.from(toggles).find(function (btn) {
        const id = btn.getAttribute('data-target');
        const panel = document.getElementById(id);
        return panel && panel.querySelector('.menu-link.active');
    });
    const savedTarget = localStorage.getItem(singleOpenKey);
    const targetToKeep = activeToggle ? activeToggle.getAttribute('data-target') : savedTarget;
    if (targetToKeep) {
        closeAll();
        const keepPanel = document.getElementById(targetToKeep);
        const keepBtn = Array.from(toggles).find(function (btn) {
            return btn.getAttribute('data-target') === targetToKeep;
        });
        if (keepPanel && keepBtn) {
            keepPanel.classList.add('open');
            keepBtn.classList.add('open');
            localStorage.setItem(keyPrefix + targetToKeep, 'open');
            localStorage.setItem(singleOpenKey, targetToKeep);
        }
    }

    document.querySelectorAll('.sub-toggle').forEach(function (toggle) {
        const targetId = toggle.getAttribute('data-target');
        const panel = document.getElementById(targetId);
        if (!panel) return;
        toggle.addEventListener('click', function () {
            panel.classList.toggle('open');
            toggle.classList.toggle('open');
        });
    });

    const notifyBtn = document.getElementById('topbarNotifyBtn');
    const pendingBtn = document.getElementById('topbarPendingBtn');
    const notifyDot = document.getElementById('topbarNotifyDot');
    const toastWrap = document.getElementById('notifyToastWrap');
    const shownIds = new Set();

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setUnreadDot(total) {
        const num = Number(total || 0);
        if (!notifyDot) return;
        if (num > 0) {
            notifyDot.style.display = 'inline-block';
            notifyDot.textContent = String(num > 99 ? '99+' : num);
        } else {
            notifyDot.style.display = 'none';
            notifyDot.textContent = '0';
        }
    }

    function markRead(ids) {
        if (!ids || ids.length === 0) return;
        const body = new URLSearchParams();
        ids.forEach(function (id) {
            body.append('ids[]', String(id));
        });
        fetch('/notifications/mark-read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        }).catch(function () {});
    }

    function showToast(item) {
        if (!toastWrap || !item || shownIds.has(item.id)) return;
        shownIds.add(item.id);
        const toast = document.createElement('div');
        toast.className = 'notify-toast';
        toast.innerHTML = ''
            + '<div class="notify-toast-title">' + escapeHtml(item.title) + '</div>'
            + '<div class="notify-toast-content">' + escapeHtml(item.content || '') + '</div>'
            + '<div class="notify-toast-time">' + escapeHtml(item.created_at || '') + '</div>';
        toastWrap.prepend(toast);
        if (toastWrap.children.length > 4) {
            toastWrap.removeChild(toastWrap.lastElementChild);
        }
        window.setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 6500);
    }

    function pullLiveNotifications() {
        fetch('/notifications/live', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || data.ok !== true) return;
                setUnreadDot(data.unread_total || 0);
                const items = Array.isArray(data.items) ? data.items : [];
                if (items.length > 0) {
                    items.slice().reverse().forEach(function (item) {
                        showToast(item);
                    });
                    markRead(items.map(function (x) { return x.id; }));
                    setUnreadDot(0);
                }
            })
            .catch(function () {});
    }

    if (notifyBtn) {
        notifyBtn.addEventListener('click', function () {
            window.location.href = '/system/notifications';
        });
    }
    if (pendingBtn) {
        pendingBtn.addEventListener('click', function () {
            window.location.href = '/pending-tasks';
        });
    }
    pullLiveNotifications();
    window.setInterval(pullLiveNotifications, 10000);
});
</script>
<script>
(function () {
    'use strict';
    if (window.__udaCellTipBound) return;
    window.__udaCellTipBound = true;

    var tipEl = null;
    var currentTrigger = null;
    var docClose = null;
    var escClose = null;
    var scrollClose = null;

    function ensureTip() {
        if (tipEl) return tipEl;
        tipEl = document.createElement('div');
        tipEl.id = 'udCellTipLayer';
        tipEl.className = 'ud-cell-tip';
        tipEl.setAttribute('role', 'dialog');
        tipEl.innerHTML = '<button type="button" class="ud-cell-tip-close" aria-label="关闭">×</button><div class="ud-cell-tip-body"></div><div class="ud-cell-tip-arrow" aria-hidden="true"></div>';
        document.body.appendChild(tipEl);
        tipEl.querySelector('.ud-cell-tip-close').addEventListener('click', function (e) {
            e.stopPropagation();
            hideTip();
        });
        return tipEl;
    }

    function hideTip() {
        if (tipEl) {
            tipEl.style.display = 'none';
            tipEl.classList.remove('ud-cell-tip--below');
        }
        if (docClose) {
            document.removeEventListener('click', docClose, true);
            docClose = null;
        }
        if (escClose) {
            document.removeEventListener('keydown', escClose, true);
            escClose = null;
        }
        if (scrollClose) {
            window.removeEventListener('scroll', scrollClose, true);
            scrollClose = null;
        }
        currentTrigger = null;
    }

    function showTip(trigger) {
        var tip = ensureTip();
        var body = tip.querySelector('.ud-cell-tip-body');
        body.textContent = (trigger.textContent || '').trim();
        tip.style.display = 'block';
        tip.style.visibility = 'hidden';
        tip.style.left = '0';
        tip.style.top = '0';
        tip.classList.remove('ud-cell-tip--below');
        var rect = trigger.getBoundingClientRect();
        var tw = tip.offsetWidth;
        var th = tip.offsetHeight;
        var pad = 8;
        var left = rect.left + rect.width / 2 - tw / 2;
        if (left < pad) left = pad;
        if (left + tw > window.innerWidth - pad) left = window.innerWidth - pad - tw;
        var gap = 8;
        var top = rect.top - th - gap;
        if (top < pad) {
            top = rect.bottom + gap;
            tip.classList.add('ud-cell-tip--below');
        }
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
        tip.style.visibility = 'visible';
        currentTrigger = trigger;
        docClose = function (e) {
            if (tip.contains(e.target)) return;
            var onSameTrigger = e.target.closest('.cell-tip-trigger');
            if (onSameTrigger === trigger) return;
            hideTip();
        };
        window.setTimeout(function () {
            document.addEventListener('click', docClose, true);
        }, 0);
        escClose = function (e) {
            if (e.key === 'Escape') hideTip();
        };
        document.addEventListener('keydown', escClose, true);
        scrollClose = function () { hideTip(); };
        window.addEventListener('scroll', scrollClose, true);
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('.cell-tip-trigger');
        if (!t) return;
        e.stopPropagation();
        if (currentTrigger === t) {
            hideTip();
            return;
        }
        hideTip();
        showTip(t);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var t = e.target.closest('.cell-tip-trigger');
        if (!t || t !== document.activeElement) return;
        e.preventDefault();
        e.stopPropagation();
        if (currentTrigger === t) {
            hideTip();
            return;
        }
        hideTip();
        showTip(t);
    });
})();
</script>
<script>
(function () {
    var burger = document.getElementById('navBurger');
    var backdrop = document.getElementById('sidebarBackdrop');
    function isNarrow() {
        return window.matchMedia('(max-width:900px)').matches;
    }
    function closeNav() {
        document.body.classList.remove('layout-nav-open');
    }
    function toggleNav() {
        document.body.classList.toggle('layout-nav-open');
    }
    if (burger) {
        burger.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleNav();
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeNav);
    }
    document.querySelectorAll('.sidebar a.menu-link').forEach(function (a) {
        a.addEventListener('click', function () {
            if (isNarrow()) closeNav();
        });
    });
    window.addEventListener('resize', function () {
        if (!isNarrow()) closeNav();
    });
})();
</script>
<script>
(function () {
    function normalizeActionButtons() {
        var nodes = document.querySelectorAll('a, button, input[type="button"], input[type="submit"]');
        nodes.forEach(function (el) {
            if (el.classList && (el.classList.contains('menu-link') || el.classList.contains('logout-link') || el.classList.contains('locale-switch'))) return;
            var text = '';
            if (el.tagName === 'INPUT') {
                text = (el.value || '').trim();
            } else {
                text = (el.textContent || '').trim();
            }
            if (text === '编辑') {
                if (el.tagName === 'INPUT') el.value = 'E'; else el.textContent = 'E';
                el.classList.add('btn-action-edit');
            } else if (text === '删除') {
                if (el.tagName === 'INPUT') el.value = 'D'; else el.textContent = 'D';
                el.classList.add('btn-action-delete');
            } else if (text === '详情' || text === '查') {
                if (el.tagName === 'INPUT') el.value = 'I'; else el.textContent = 'I';
                el.classList.add('btn-action-info');
            } else if (text === '转送' || text === '回滚') {
                if (el.tagName === 'INPUT') el.value = 'F'; else el.textContent = 'F';
                el.classList.add('btn-action-forward');
            }
        });
    }

    function normalizePaginationLabels() {
        var pagerNodes = document.querySelectorAll('a, button, input[type="button"], input[type="submit"]');
        pagerNodes.forEach(function (el) {
            if (el.classList && (el.classList.contains('menu-link') || el.classList.contains('logout-link') || el.classList.contains('locale-switch'))) return;
            var text = '';
            if (el.tagName === 'INPUT') text = (el.value || '').trim();
            else text = (el.textContent || '').trim();
            if (text === '上一页') {
                if (el.tagName === 'INPUT') el.value = '<<'; else el.textContent = '<<';
            } else if (text === '下一页') {
                if (el.tagName === 'INPUT') el.value = '>>'; else el.textContent = '>>';
            }
        });
    }

    function normalizeModals() {
        var overlays = document.querySelectorAll('[id$="Modal"], [id*="Modal"]');
        overlays.forEach(function (overlay) {
            var style = (overlay.getAttribute('style') || '').toLowerCase();
            if (style.indexOf('position:fixed') === -1) return;
            overlay.style.inset = '0';
            overlay.style.background = 'rgba(0,0,0,.35)';
            overlay.style.zIndex = overlay.style.zIndex || '9999';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.padding = '12px';

            var inner = overlay.firstElementChild;
            if (!inner) return;
            inner.style.background = '#fff';
            inner.style.borderRadius = '12px';
            inner.style.padding = inner.style.padding || '16px';
            inner.style.maxWidth = inner.style.maxWidth || '720px';
            inner.style.width = inner.style.width || '100%';
            inner.style.boxShadow = '0 12px 30px rgba(15,23,42,.22)';
            inner.style.position = inner.style.position || 'relative';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        normalizeActionButtons();
        normalizePaginationLabels();
        normalizeModals();
    });
})();
</script>
</body>
</html>
