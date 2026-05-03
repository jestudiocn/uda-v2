<?php
$path = __DIR__ . '/../app/Controllers/DispatchController.php';
$keyByZh = json_decode(file_get_contents(__DIR__ . '/../lang/dispatch-key-by-zh.json'), true);
$c = file_get_contents($path);
$q = chr(39);
uksort($keyByZh, static function (string $a, string $b): int {
    return strlen($b) <=> strlen($a);
});
foreach ($keyByZh as $zh => $key) {
    if ($zh === '') {
        continue;
    }
    $esc = preg_quote($zh, '#');
    $fb = addcslashes($zh, "'\\");
    $repT = "t('" . addcslashes($key, "'\\") . "', '" . $fb . "')";
    foreach (['$error', '$message', '$title'] as $var) {
        $pat = '#' . preg_quote($var, '#') . '\s*=\s*' . $q . $esc . $q . ';#u';
        $c = preg_replace($pat, $var . ' = ' . $repT . ';', $c);
    }
}
file_put_contents($path, $c);
echo "round2 done\n";
