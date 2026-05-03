<?php
$path = __DIR__ . '/../app/Controllers/DispatchController.php';
$keyByZh = json_decode(file_get_contents(__DIR__ . '/../lang/dispatch-key-by-zh.json'), true);
if (!is_array($keyByZh)) {
    exit(1);
}
$c = file_get_contents($path);
uksort($keyByZh, static function (string $a, string $b): int {
    return strlen($b) <=> strlen($a);
});
$q = chr(39);
foreach ($keyByZh as $zh => $key) {
    if ($zh === '') {
        continue;
    }
    $esc = preg_quote($zh, '#');
    $fb = addcslashes($zh, "'\\");
    $repT = "t('" . addcslashes($key, "'\\") . "', '" . $fb . "')";
    $c = preg_replace(
        '#\$this->denyNoPermission\(\s*' . $q . $esc . $q . '\s*\)#u',
        '$this->denyNoPermission(' . $repT . ')',
        $c
    );
    $c = preg_replace(
        '#throw\s+new\s+RuntimeException\(\s*' . $q . $esc . $q . '\s*\)#u',
        'throw new RuntimeException(' . $repT . ')',
        $c
    );
}
$c = str_replace(
    "private function denyNoPermission(string \$message = '无权限执行此操作'): void\n    {\n        http_response_code(403);\n        echo \$message;",
    "private function denyNoPermission(string \$message = ''): void\n    {\n        http_response_code(403);\n        if (\$message === '') {\n            \$message = t('dispatch.str.0052', '无权限执行此操作');\n        }\n        echo \$message;",
    $c
);
file_put_contents($path, $c);
echo "patched deny + runtime\n";
