<?php
$f = file_get_contents(__DIR__ . '/../app/Controllers/DispatchController.php');
preg_match_all('/\$error\s*=\s*\'((?:\\\\\'|[^\'])*)\'\s*;/u', $f, $m1);
preg_match_all('/\$message\s*=\s*\'((?:\\\\\'|[^\'])*)\'\s*;/u', $f, $m2);
$all = [];
foreach (array_merge($m1[1], $m2[1]) as $s) {
    $raw = str_replace("\\'", "'", $s);
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $raw)) {
        $all[$raw] = true;
    }
}
$keys = array_keys($all);
sort($keys);
echo count($keys) . " unique strings\n";
file_put_contents(__DIR__ . '/dispatch_strings_zh.txt', implode("\n---\n", $keys));
