<?php
$f = file_get_contents(__DIR__ . '/../app/Controllers/DispatchController.php');
$patterns = [
    '/\$error\s*=\s*\'((?:\\\\\'|[^\'])*)\'/u',
    '/\$message\s*=\s*\'((?:\\\\\'|[^\'])*)\'/u',
    '/denyNoPermission\(\s*\'((?:\\\\\'|[^\'])*)\'/u',
    '/throw\s+new\s+RuntimeException\(\s*\'((?:\\\\\'|[^\'])*)\'/u',
    '/\$title\s*=\s*\'((?:\\\\\'|[^\'])*)\'/u',
];
$all = [];
foreach ($patterns as $re) {
    preg_match_all($re, $f, $m);
    foreach ($m[1] as $s) {
        $raw = str_replace("\\'", "'", $s);
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $raw)) {
            $all[$raw] = true;
        }
    }
}
$keys = array_keys($all);
sort($keys);
echo count($keys) . " unique\n";
file_put_contents(__DIR__ . '/dispatch_all_zh.txt', implode("\n---\n", $keys));
