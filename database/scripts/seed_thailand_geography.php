<?php
/**
 * 从 database/seeds/th_geo/*.json 导入泰国行政区到 th_geo_* 表。
 * 依赖：已执行 database/migrations/049_thailand_geography_master.sql
 * 用法（项目根目录）：php database/scripts/seed_thailand_geography.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/config/database.php';

/** @var mysqli $conn */
if (!$conn instanceof mysqli) {
    fwrite(STDERR, "database.php 未返回 mysqli\n");
    exit(1);
}

foreach (['th_geo_provinces', 'th_geo_districts', 'th_geo_subdistricts'] as $t) {
    $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$chk || $chk->num_rows === 0) {
        fwrite(STDERR, "表 {$t} 不存在，请先执行 049_thailand_geography_master.sql\n");
        exit(1);
    }
}

$dir = $root . '/database/seeds/th_geo';
$files = [
    'province' => $dir . '/province.json',
    'district' => $dir . '/district.json',
    'sub_district' => $dir . '/sub_district.json',
];
foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "缺少种子文件：{$path}\n请从 https://github.com/kongvut/thai-province-data 下载 api/latest 对应 JSON 到 database/seeds/th_geo/\n");
        exit(1);
    }
}

$conn->begin_transaction();
try {
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    $conn->query('TRUNCATE TABLE th_geo_subdistricts');
    $conn->query('TRUNCATE TABLE th_geo_districts');
    $conn->query('TRUNCATE TABLE th_geo_provinces');
    $conn->query('SET FOREIGN_KEY_CHECKS=1');

    $pj = file_get_contents($files['province']);
    if ($pj === false) {
        throw new RuntimeException('读取 province.json 失败');
    }
    $provinces = json_decode($pj, true, 512, JSON_THROW_ON_ERROR);
    $insP = $conn->prepare('INSERT INTO th_geo_provinces (id, name_th, name_en) VALUES (?, ?, ?)');
    if (!$insP) {
        throw new RuntimeException('prepare provinces: ' . $conn->error);
    }
    foreach ($provinces as $row) {
        if (!is_array($row) || !isset($row['id'])) {
            continue;
        }
        $id = (int)$row['id'];
        $nth = (string)($row['name_th'] ?? '');
        $nen = (string)($row['name_en'] ?? '');
        $insP->bind_param('iss', $id, $nth, $nen);
        $insP->execute();
    }
    $insP->close();
    echo 'Provinces: ' . count($provinces) . PHP_EOL;

    $dj = file_get_contents($files['district']);
    if ($dj === false) {
        throw new RuntimeException('读取 district.json 失败');
    }
    $districts = json_decode($dj, true, 512, JSON_THROW_ON_ERROR);
    $insD = $conn->prepare('INSERT INTO th_geo_districts (id, province_id, name_th, name_en) VALUES (?, ?, ?, ?)');
    if (!$insD) {
        throw new RuntimeException('prepare districts: ' . $conn->error);
    }
    $n = 0;
    foreach ($districts as $row) {
        if (!is_array($row) || !isset($row['id'], $row['province_id'])) {
            continue;
        }
        $id = (int)$row['id'];
        $pid = (int)$row['province_id'];
        $nth = (string)($row['name_th'] ?? '');
        $nen = (string)($row['name_en'] ?? '');
        $insD->bind_param('iiss', $id, $pid, $nth, $nen);
        $insD->execute();
        $n++;
    }
    $insD->close();
    echo 'Districts: ' . $n . PHP_EOL;

    $sj = file_get_contents($files['sub_district']);
    if ($sj === false) {
        throw new RuntimeException('读取 sub_district.json 失败');
    }
    $subs = json_decode($sj, true, 512, JSON_THROW_ON_ERROR);
    $insS = $conn->prepare('INSERT INTO th_geo_subdistricts (id, district_id, zipcode, name_th, name_en) VALUES (?, ?, ?, ?, ?)');
    if (!$insS) {
        throw new RuntimeException('prepare subdistricts: ' . $conn->error);
    }
    $m = 0;
    foreach ($subs as $row) {
        if (!is_array($row) || !isset($row['id'], $row['district_id'])) {
            continue;
        }
        $id = (int)$row['id'];
        $did = (int)$row['district_id'];
        $zipRaw = $row['zip_code'] ?? 0;
        $zip = str_pad((string)(int)$zipRaw, 5, '0', STR_PAD_LEFT);
        if (strlen($zip) > 5) {
            $zip = substr($zip, -5);
        }
        $nth = (string)($row['name_th'] ?? '');
        $nen = (string)($row['name_en'] ?? '');
        $insS->bind_param('iisss', $id, $did, $zip, $nth, $nen);
        $insS->execute();
        $m++;
    }
    $insS->close();
    echo 'Subdistricts: ' . $m . PHP_EOL;

    $conn->commit();
    echo "完成。\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, '失败：' . $e->getMessage() . "\n");
    exit(1);
}
