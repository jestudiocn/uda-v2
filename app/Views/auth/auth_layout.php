<?php
/** @var string $title */
/** @var string $contentView */
$__htmlLang = (string)($_SESSION['app_locale'] ?? 'zh-CN');
if (!in_array($__htmlLang, ['zh-CN', 'th-TH'], true)) {
    $__htmlLang = 'zh-CN';
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
        body {
            margin: 0;
            font-family: Arial, "Microsoft YaHei", "SimHei", sans-serif;
            background: #f4f6fb;
            color: #111827;
            font-size: 14px;
        }
        .auth-topbar {
            height: 54px;
            background: #ffc300;
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
        .auth-topbar-left {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .auth-topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        form.inline-locale { display: inline; margin: 0; padding: 0; }
        .auth-topbar button.locale-switch {
            height: 32px;
            padding: 0 10px;
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
        .auth-topbar button.locale-switch:hover { background: #fff8db; }

        .wrap {
            max-width: 460px;
            margin: 0 auto;
            padding: 72px 12px 24px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            border: none;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
        }
        /* 与主站「用户管理」页 card 内标题一致 */
        .wrap .card h2 {
            margin: 0 0 8px 0;
            font-size: 21px;
            font-weight: 700;
            color: #111827;
            line-height: 1.25;
        }
        .wrap .card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            line-height: 1.25;
        }
        .card h2 + .muted,
        .card .page-title + .muted {
            display: none !important;
        }
        .row { display: grid; gap: 6px; margin-bottom: 12px; }
        .muted { color: #6b7280; font-size: 12px; }
        .card label:not(:has(input)) {
            font-size: 13px;
            font-weight: 700;
            color: #111;
            display: block;
            margin-bottom: 2px;
        }
        .card label:not(:has(input)) + br {
            display: none;
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
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 14px;
            border: none;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="auth-topbar">
    <div class="auth-topbar-left"><?php echo htmlspecialchars(t('app.name', 'UDA-V2')); ?></div>
    <div class="auth-topbar-right">
        <form class="inline-locale" method="post" action="/locale">
            <input type="hidden" name="app_locale" value="zh-CN">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(function_exists('locale_redirect_current_uri') ? locale_redirect_current_uri() : '/login', ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="locale-switch"><?php echo htmlspecialchars(t('lang.switch.zh', '中文')); ?></button>
        </form>
        <form class="inline-locale" method="post" action="/locale">
            <input type="hidden" name="app_locale" value="th-TH">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(function_exists('locale_redirect_current_uri') ? locale_redirect_current_uri() : '/login', ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="locale-switch"><?php echo htmlspecialchars(t('lang.switch.th', 'ไทย')); ?></button>
        </form>
    </div>
</div>
<div class="wrap">
    <?php require $contentView; ?>
</div>
</body>
</html>
