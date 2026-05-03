<?php
$d = __DIR__ . '/../app/Views/dispatch';
foreach (glob($d . '/*.php') as $f) {
    preg_match_all('/[\x{4e00}-\x{9fff}]/u', file_get_contents($f), $m);
    echo basename($f) . "\t" . count($m[0]) . "\n";
}
foreach (glob($d . '/forwarding/*.php') as $f) {
    preg_match_all('/[\x{4e00}-\x{9fff}]/u', file_get_contents($f), $m);
    echo str_replace($d . '/', '', $f) . "\t" . count($m[0]) . "\n";
}
