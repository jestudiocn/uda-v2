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
/** @var string $driverSameGeoBannerPrev */
/** @var string $driverSameGeoBannerNext */
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
$driverSameGeoBannerPrev = (string)($driverSameGeoBannerPrev ?? '');
$driverSameGeoBannerNext = (string)($driverSameGeoBannerNext ?? '');
$docNo = is_array($tokenRow) ? (string)($tokenRow['delivery_doc_no'] ?? '') : '';
$line = is_array($docMeta) ? (string)($docMeta['dispatch_line'] ?? '') : '';
$planDate = is_array($docMeta) ? (string)($docMeta['planned_delivery_date'] ?? '') : '';
$dash = '—';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>อัปโหลดรูป POD คนขับ · UDA</title>
    <style>
        body{font-family:"Sarabun","Noto Sans Thai",system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;padding:12px;background:#f1f5f9;color:#0f172a;font-size:16px;line-height:1.45;}
        .box{background:#fff;border-radius:12px;padding:14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
        h1{font-size:1.15rem;margin:0 0 8px 0;}
        .muted{color:#64748b;font-size:.9rem;}
        .btn{display:block;width:100%;text-align:center;padding:14px 12px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;border:none;font-size:1rem;cursor:pointer;box-sizing:border-box;}
        .btn:disabled,.btn.disabled{background:#94a3b8;cursor:not-allowed;}
        .btn-sec{margin-top:8px;background:#64748b;}
        .stop{border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-bottom:10px;}
        .stop h2{margin:0 0 6px 0;font-size:1rem;}
        .pod-field-label{display:block;margin:10px 0 6px;font-size:.85rem;color:#475569;font-weight:600;}
        .pod-field-label:first-of-type{margin-top:6px;}
        .file-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:4px;}
        .file-input-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
        .btn-file-pick{display:inline-block;width:auto;min-width:7.5rem;padding:10px 14px;border-radius:8px;background:#475569;color:#fff;font-weight:700;font-size:.9rem;cursor:pointer;text-align:center;box-sizing:border-box;}
        .btn-file-pick:hover{background:#334155;}
        .btn-file-pick.is-disabled{background:#94a3b8;cursor:not-allowed;pointer-events:none;}
        .file-name{flex:1;min-width:0;font-size:.85rem;color:#64748b;word-break:break-word;line-height:1.35;}
        .ok{color:#15803d;font-weight:600;}
        .err{color:#b91c1c;font-weight:600;}
        .pill{display:inline-block;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:6px;font-size:.8rem;margin-right:4px;}
        .pod-state-alert{
            margin:10px 0 0;padding:10px 12px;border-radius:8px;font-weight:700;font-size:.95rem;
            background:#fef3c7;color:#92400e;border:2px solid #f59e0b;
            animation:podBlink 1s ease-in-out infinite alternate;
        }
        @keyframes podBlink{
            from{opacity:1;box-shadow:0 0 0 0 rgba(245,158,11,.7);}
            to{opacity:.88;box-shadow:0 0 14px 4px rgba(245,158,11,.45);}
        }
        .same-geo-banner{
            margin:0 0 12px;padding:10px 12px;border-radius:10px;font-size:.9rem;font-weight:600;
            background:#ecfeff;color:#0c4a6e;border:1px solid #06b6d4;line-height:1.4;
        }
        .stop-same-geo{
            border-left:5px solid #0284c7;
            background:linear-gradient(90deg,rgba(224,242,254,.65) 0,rgba(255,255,255,0) 14px);
        }
        .same-geo-badge{
            display:inline-block;margin-left:8px;padding:2px 8px;border-radius:6px;font-size:.78rem;font-weight:700;
            background:#fef08a;color:#713f12;border:1px solid #eab308;vertical-align:middle;
        }
    </style>
</head>
<body>
<?php if ($driverError !== ''): ?>
    <div class="box err">
        <?php if ($driverError === 'invalid'): ?>
            ลิงก์ไม่ถูกต้องหรือหมดอายุ โปรดขอลิงก์ใหม่จากผู้ดูแลระบบ
        <?php elseif ($driverError === 'empty'): ?>
            ช่วงนี้ยังไม่มีข้อมูลลูกค้า
        <?php else: ?>
            เกิดข้อผิดพลาด โปรดลองใหม่หรือติดต่อผู้ดูแล (<?php echo htmlspecialchars($driverError); ?>)
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($flash === 'saved'): ?>
        <div class="box ok">บันทึกรูปหลักฐานการรับ (POD) เรียบร้อยแล้ว</div>
    <?php endif; ?>
    <div class="box">
        <h1>ใบจัดส่ง (<?php echo htmlspecialchars($docNo); ?>)</h1>
        <div class="muted">
            <?php if ($line !== ''): ?><span class="pill">สายส่ง <?php echo htmlspecialchars($line); ?></span><?php endif; ?>
            <?php if ($planDate !== ''): ?><span class="pill">นัดส่ง <?php echo htmlspecialchars($planDate); ?></span><?php endif; ?>
            ช่วงที่ <?php echo (int)$segmentIndex + 1; ?> / <?php echo max(1, $segmentTotal); ?> (สูงสุด <?php echo 6; ?> ลูกค้าต่อช่วง)
        </div>
    </div>
    <div class="box">
        <?php if ($mapsUrl !== null && $mapsUrl !== ''): ?>
            <a class="btn" href="<?php echo htmlspecialchars($mapsUrl); ?>" rel="noopener noreferrer">เปิด Google Maps — เส้นทางช่วงนี้</a>
            <p class="muted" style="margin:8px 0 0;">จุดเริ่มต้นคือตำแหน่งปัจจุบันของคุณบนแผนที่; จุดหมายนำทางของช่วงนี้คือลูกค้าคนสุดท้ายในรายการด้านล่าง</p>
        <?php else: ?>
            <p class="err">ไม่สามารถสร้างลิงก์นำทางได้ เนื่องจากขาดพิกัดหรือที่อยู่ลูกค้า โปรดให้ผู้ดูแลเพิ่มข้อมูลในระบบ</p>
        <?php endif; ?>
    </div>
    <div class="box">
        <h1 style="margin-bottom:10px;">ลูกค้าช่วงนี้ · อัปโหลดรูป POD จำนวน 2 รูป</h1>
        <?php if ($driverSameGeoBannerPrev !== ''): ?>
            <div class="same-geo-banner" role="status"><?php echo htmlspecialchars($driverSameGeoBannerPrev); ?></div>
        <?php endif; ?>
        <?php if ($driverSameGeoBannerNext !== ''): ?>
            <div class="same-geo-banner" role="status"><?php echo htmlspecialchars($driverSameGeoBannerNext); ?></div>
        <?php endif; ?>
        <p class="muted" style="margin:0 0 10px;">หมายเหตุพิกัดเดียวกัน: ลูกค้าที่อยู่<strong>ติดกันและพิกัดเดียวกัน</strong>ในช่วงนี้จะมีแถบสีด้านซ้ายและป้าย "พิกัดเดียวกัน" หากพิกัด<strong>ตรงกับลูกค้าคนสุดท้ายของช่วงก่อน หรือคนแรกของช่วงถัดไป</strong> แถบสีฟ้าด้านบนจะอธิบายว่าต้องส่งหลายลูกค้า จำนวนคนต่อช่วงและการแบ่งถุงไม่เปลี่ยน</p>
        <?php foreach ($segmentCustomers as $i => $row): ?>
            <?php
            $code = (string)($row['customer_code'] ?? '');
            $wx = (string)($row['wx_or_line'] ?? '');
            $phone = trim((string)($row['phone'] ?? ''));
            $th = trim((string)($row['community_name_th'] ?? ''));
            $hn = trim((string)($row['addr_house_no'] ?? ''));
            $rs = trim((string)($row['addr_road_soi'] ?? ''));
            $houseRoad = $dash;
            if ($hn !== '' && $rs !== '') {
                $houseRoad = $hn . ', ' . $rs;
            } elseif ($hn !== '') {
                $houseRoad = $hn;
            } elseif ($rs !== '') {
                $houseRoad = $rs;
            }
            $pc = (int)($row['piece_count'] ?? 0);
            $done = !empty($podDoneCodes[$code]);
            $podBlocked = !empty($row['pod_blocked']);
            $sgLen = (int)($row['driver_same_geo_run_len'] ?? 0);
            $sgIdx = (int)($row['driver_same_geo_run_index'] ?? 0);
            $sgHue = isset($row['driver_same_geo_css_hue']) ? (int)$row['driver_same_geo_css_hue'] : -1;
            $stopExtraClass = ($sgLen >= 2 && $sgIdx > 0 && $sgHue >= 0) ? ' stop-same-geo' : '';
            $stopInline = '';
            if ($sgLen >= 2 && $sgIdx > 0 && $sgHue >= 0) {
                $stopInline = ' style="border-left-color:hsl(' . $sgHue . ',58%,42%);background:linear-gradient(90deg,hsla(' . $sgHue . ',85%,94%,.75) 0,rgba(255,255,255,0) 16px);"';
            }
            ?>
            <div class="stop<?php echo $stopExtraClass; ?>"<?php echo $stopInline !== '' ? $stopInline : ''; ?>>
                <h2>#<?php echo (int)$i + 1; ?> <?php echo htmlspecialchars($code); ?> · <?php echo (int)$pc; ?> ชิ้น<?php if ($sgLen >= 2 && $sgIdx > 0): ?><span class="same-geo-badge">พิกัดเดียวกัน <?php echo (int)$sgIdx; ?>/<?php echo (int)$sgLen; ?></span><?php endif; ?></h2>
                <?php if ($podBlocked): ?>
                    <div class="pod-state-alert" role="alert">ลูกค้ารายนี้ถูกทำเครื่องหมายว่า (มีปัญหา) หรือ (หยุดชั่วคราว) ในระบบ: ห้ามส่งหรือเซ็นรับ ปิดการอัปโหลดรูปแล้ว</div>
                <?php endif; ?>
                <div class="muted">WeChat / Line: <?php echo htmlspecialchars($wx !== '' ? $wx : $dash); ?></div>
                <div class="muted" style="margin-top:4px;">โทรศัพท์: <?php echo htmlspecialchars($phone !== '' ? $phone : $dash); ?></div>
                <div style="margin-top:6px;">โครงการ (ภาษาไทย): <?php echo htmlspecialchars($th !== '' ? $th : $dash); ?></div>
                <div style="margin-top:4px;">เลขที่ / ซอย-ถนน: <?php echo htmlspecialchars($houseRoad); ?></div>
                <?php if ($podBlocked): ?>
                    <?php if ($done): ?>
                        <p class="muted" style="margin:10px 0 0;">มีประวัติการอัปโหลดแล้ว แต่เนื่องจากสถานะลูกค้าจึงแก้ไขหรืออัปโหลดเพิ่มไม่ได้</p>
                    <?php else: ?>
                        <p class="muted" style="margin:10px 0 0;">ปิดการอัปโหลดและส่งหลักฐานแล้ว (ปุ่มสีเทาใช้งานไม่ได้)</p>
                        <form method="post" action="/dispatch/driver/pod-upload" enctype="multipart/form-data" style="margin-top:10px;pointer-events:none;opacity:.55;">
                            <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="customer_code" value="<?php echo htmlspecialchars($code); ?>">
                            <span class="pod-field-label">รูปที่ 1</span>
                            <div class="file-row">
                                <span class="btn-file-pick is-disabled" aria-hidden="true">เลือกไฟล์</span>
                                <span class="file-name">ปิดการอัปโหลด</span>
                            </div>
                            <span class="pod-field-label">รูปที่ 2</span>
                            <div class="file-row">
                                <span class="btn-file-pick is-disabled" aria-hidden="true">เลือกไฟล์</span>
                                <span class="file-name">ปิดการอัปโหลด</span>
                            </div>
                            <button type="button" class="btn disabled" style="margin-top:10px;" disabled>ส่งรูปหลักฐานการรับ</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($done): ?>
                    <p class="ok" style="margin:8px 0 0;">อัปโหลดรูปครบแล้ว</p>
                <?php else: ?>
                    <?php $fid1 = 'pod_photo_1_' . (int)$i; $fid2 = 'pod_photo_2_' . (int)$i; ?>
                    <form class="js-pod-precheck-form" method="post" action="/dispatch/driver/pod-upload" enctype="multipart/form-data" style="margin-top:10px;">
                        <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="customer_code" value="<?php echo htmlspecialchars($code); ?>">
                        <span class="pod-field-label">รูปที่ 1</span>
                        <div class="file-row">
                            <input type="file" name="photo_1" id="<?php echo htmlspecialchars($fid1); ?>" class="file-input-sr-only" accept="image/*" capture="environment" required>
                            <label for="<?php echo htmlspecialchars($fid1); ?>" class="btn-file-pick">เลือกไฟล์</label>
                            <span class="file-name" data-for="<?php echo htmlspecialchars($fid1); ?>">ยังไม่ได้เลือกไฟล์</span>
                        </div>
                        <span class="pod-field-label">รูปที่ 2</span>
                        <div class="file-row">
                            <input type="file" name="photo_2" id="<?php echo htmlspecialchars($fid2); ?>" class="file-input-sr-only" accept="image/*" capture="environment" required>
                            <label for="<?php echo htmlspecialchars($fid2); ?>" class="btn-file-pick">เลือกไฟล์</label>
                            <span class="file-name" data-for="<?php echo htmlspecialchars($fid2); ?>">ยังไม่ได้เลือกไฟล์</span>
                        </div>
                        <button type="submit" class="btn" style="margin-top:10px;">ส่งรูปหลักฐานการรับ</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="muted" style="text-align:center;">ช่วงถัดไป: สแกน QR ของช่วงถัดไป หรือใช้ลิงก์ที่ผู้ดูแลให้</p>
    <script>
    (function () {
        var emptyFileMsg = 'ยังไม่ได้เลือกไฟล์';
        document.querySelectorAll('input.file-input-sr-only[type="file"]').forEach(function (inp) {
            inp.addEventListener('change', function () {
                var span = document.querySelector('.file-name[data-for="' + inp.id + '"]');
                if (!span) return;
                var f = inp.files && inp.files[0];
                span.textContent = f ? f.name : emptyFileMsg;
            });
        });
        /* เมื่อกลับจากแอปแผนที่/ไลน์ ให้รีเฟรชเพื่อดูสถานะล่าสุด */
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                document.body.setAttribute('data-driver-tab-was-hidden', '1');
            } else if (document.visibilityState === 'visible' && document.body.getAttribute('data-driver-tab-was-hidden') === '1') {
                window.location.reload();
            }
        });
        document.querySelectorAll('form.js-pod-precheck-form').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var btn = form.querySelector('button[type="submit"]');
                var tEl = form.querySelector('input[name="t"]');
                var cEl = form.querySelector('input[name="customer_code"]');
                if (!tEl || !cEl) {
                    HTMLFormElement.prototype.submit.call(form);
                    return;
                }
                var origText = btn ? btn.textContent : '';
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'กำลังตรวจสอบ…';
                }
                var url = '/dispatch/driver/pod-precheck?t=' + encodeURIComponent(tEl.value) + '&customer_code=' + encodeURIComponent(cEl.value);
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j || !j.ok || j.blocked) {
                            window.location.reload();
                            return;
                        }
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = origText;
                        }
                        HTMLFormElement.prototype.submit.call(form);
                    })
                    .catch(function () {
                        window.location.reload();
                    });
            });
        });
    })();
    </script>
<?php endif; ?>
</body>
</html>
