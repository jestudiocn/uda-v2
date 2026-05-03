<?php
$c = file_get_contents(__DIR__ . '/../app/Controllers/DispatchController.php');
$zh = '派送表未建立，请先执行 migration：021_dispatch_core_tables.sql';
$q = chr(39);
$esc = preg_quote($zh, '#');
$re = '#throw\s+new\s+RuntimeException\(\s*' . $q . $esc . $q . '\s*\)#u';
$line = "                throw new RuntimeException('派送表未建立，请先执行 migration：021_dispatch_core_tables.sql');";
echo (preg_match($re, $line) ? "line ok\n" : "line fail\n");
echo (preg_match($re, $c) ? "file ok\n" : "file fail\n");
$zh2 = '无权限访问派送模块';
$esc2 = preg_quote($zh2, '#');
$re2 = '#\$this->denyNoPermission\(\s*' . $q . $esc2 . $q . '\s*\)#u';
echo (preg_match($re2, $c) ? "deny ok\n" : "deny fail\n");
