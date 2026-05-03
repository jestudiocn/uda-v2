<?php
$l = file(__DIR__ . '/../app/Controllers/DispatchController.php');
for ($i = 43; $i <= 70; $i++) {
    echo ($i + 1) . ': ' . ($l[$i] ?? '');
}
