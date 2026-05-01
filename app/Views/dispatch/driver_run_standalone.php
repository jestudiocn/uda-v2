<?php
/** @var string $token */
/** @var string $driverError */
/** @var string $flash */
/** @var array<string,mixed>|null $tokenRow */
/** @var list<array<string,mixed>> $segmentCustomers */
/** @var string|null $mapsUrl */
/** @var array<string,mixed>|null $docMeta */
/** @var int $segmentTotal */
/** @var int $segmentIndex */
/** @var array<string,bool> $podDoneCodes */
$token = (string)($token ?? '');
$driverError = (string)($driverError ?? '');
$flash = (string)($flash ?? '');
$tokenRow = $tokenRow ?? null;
$segmentCustomers = $segmentCustomers ?? [];
$mapsUrl = $mapsUrl ?? null;
$docMeta = $docMeta ?? null;
$segmentTotal = (int)($segmentTotal ?? 0);
$segmentIndex = (int)($segmentIndex ?? 0);
$podDoneCodes = $podDoneCodes ?? [];
$docNo = is_array($tokenRow) ? (string)($tokenRow['delivery_doc_no'] ?? '') : '';
$line = is_array($docMeta) ? (string)($docMeta['dispatch_line'] ?? '') : '';
$planDate = is_array($docMeta) ? (string)($docMeta['planned_delivery_date'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>UDA-V2 内部管理系统</title>
    <style>
        body{font-family:system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif;margin:0;padding:12px;background:#f1f5f9;color:#0f172a;font-size:16px;line-height:1.45;}
        .box{background:#fff;border-radius:12px;padding:14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
        h1{font-size:1.15rem;margin:0 0 8px 0;}
        .muted{color:#64748b;font-size:.9rem;}
        .btn{display:block;width:100%;text-align:center;padding:14px 12px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;border:none;font-size:1rem;cursor:pointer;box-sizing:border-box;}
        .btn:disabled,.btn.disabled{background:#94a3b8;cursor:not-allowed;}
        .btn-sec{margin-top:8px;background:#64748b;}
        .stop{border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-bottom:10px;}
        .stop h2{margin:0 0 6px 0;font-size:1rem;}
        label{display:block;margin:6px 0 4px;font-size:.85rem;color:#475569;}
        input[type=file]{width:100%;font-size:.9rem;}
        .ok{color:#15803d;font-weight:600;}
        .err{color:#b91c1c;font-weight:600;}
        .pill{display:inline-block;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:6px;font-size:.8rem;margin-right:4px;}
    </style>
</head>
<body>
<?php if ($driverError !== ''): ?>
    <div class="box err">
        <?php if ($driverError === 'invalid'): ?>
            链接无效或已过期，请向后台重新生成并索取新链接。
        <?php elseif ($driverError === 'empty'): ?>
            本段暂无客户数据。
        <?php else: ?>
            发生错误，请重试或联系后台（<?php echo htmlspecialchars($driverError); ?>）
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($flash === 'saved'): ?>
        <div class="box ok">签收照片已保存。</div>
    <?php endif; ?>
    <div class="box">
        <h1>派送单（<?php echo htmlspecialchars($docNo); ?>）</h1>
        <div class="muted">
            <?php if ($line !== ''): ?><span class="pill">派送线 <?php echo htmlspecialchars($line); ?></span><?php endif; ?>
            <?php if ($planDate !== ''): ?><span class="pill">预计 <?php echo htmlspecialchars($planDate); ?></span><?php endif; ?>
            第 <?php echo (int)$segmentIndex + 1; ?> / <?php echo max(1, $segmentTotal); ?> 段（每段最多 <?php echo 6; ?> 位客户）
        </div>
    </div>
    <div class="box">
        <?php if ($mapsUrl !== null && $mapsUrl !== ''): ?>
            <a class="btn" href="<?php echo htmlspecialchars($mapsUrl); ?>" rel="noopener noreferrer">打开 Google Maps — 本段路线</a>
            <p class="muted" style="margin:8px 0 0;">起点为打开手机地图时的当前位置；本段导航终点为下列清单中的最后一位客户。</p>
        <?php else: ?>
            <p class="err">无法生成导航链接：缺少客户坐标或地址，请让后台在系统中补充。</p>
        <?php endif; ?>
    </div>
    <div class="box">
        <h1 style="margin-bottom:10px;">本段客户 · 上传签收照片（2 张）</h1>
        <?php foreach ($segmentCustomers as $i => $row): ?>
            <?php
            $code = (string)($row['customer_code'] ?? '');
            $wx = (string)($row['wx_or_line'] ?? '');
            $phone = trim((string)($row['phone'] ?? ''));
            $th = trim((string)($row['community_name_th'] ?? ''));
            $hn = trim((string)($row['addr_house_no'] ?? ''));
            $rs = trim((string)($row['addr_road_soi'] ?? ''));
            $houseRoad = '—';
            if ($hn !== '' && $rs !== '') {
                $houseRoad = $hn . ', ' . $rs;
            } elseif ($hn !== '') {
                $houseRoad = $hn;
            } elseif ($rs !== '') {
                $houseRoad = $rs;
            }
            $pc = (int)($row['piece_count'] ?? 0);
            $done = !empty($podDoneCodes[$code]);
            ?>
            <div class="stop">
                <h2>#<?php echo (int)$i + 1; ?> <?php echo htmlspecialchars($code); ?> · <?php echo (int)$pc; ?> 件</h2>
                <div class="muted">微信/Line：<?php echo htmlspecialchars($wx !== '' ? $wx : '—'); ?></div>
                <div class="muted" style="margin-top:4px;">电话：<?php echo htmlspecialchars($phone !== '' ? $phone : '—'); ?></div>
                <div style="margin-top:6px;">泰文小区：<?php echo htmlspecialchars($th !== '' ? $th : '—'); ?></div>
                <div style="margin-top:4px;">门牌号 / 巷（路）：<?php echo htmlspecialchars($houseRoad); ?></div>
                <?php if ($done): ?>
                    <p class="ok" style="margin:8px 0 0;">照片已全部上传。</p>
                <?php else: ?>
                    <form method="post" action="/dispatch/driver/pod-upload" enctype="multipart/form-data" style="margin-top:10px;">
                        <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="customer_code" value="<?php echo htmlspecialchars($code); ?>">
                        <label>照片 1</label>
                        <input type="file" name="photo_1" accept="image/*" capture="environment" required>
                        <label>照片 2</label>
                        <input type="file" name="photo_2" accept="image/*" capture="environment" required>
                        <button type="submit" class="btn" style="margin-top:10px;">提交签收照片</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="muted" style="text-align:center;">下一段：请扫描下一段二维码，或使用后台提供的下一段链接。</p>
<?php endif; ?>
</body>
</html>
