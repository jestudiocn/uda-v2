<?php
$path = __DIR__ . '/../app/Controllers/DispatchController.php';
$keyByZh = json_decode(file_get_contents(__DIR__ . '/../lang/dispatch-key-by-zh.json'), true);
if (!is_array($keyByZh)) {
    fwrite(STDERR, "bad json\n");
    exit(1);
}
$c = file_get_contents($path);
if ($c === false) {
    exit(1);
}

// 按长度降序，减少短串误替换
uksort($keyByZh, static function (string $a, string $b): int {
    return strlen($b) <=> strlen($a);
});

foreach ($keyByZh as $zh => $key) {
    if ($zh === '') {
        continue;
    }
    $esc = preg_quote($zh, '/');
    $fb = addcslashes($zh, "'\\");
    $repT = "t('" . addcslashes($key, "'\\") . "', '" . $fb . "')";
    foreach (['$error', '$message', '$title'] as $var) {
        $c = preg_replace('/' . preg_quote($var, '/') . '\s*=\s*\'' . $esc . '\';/u', $var . ' = ' . $repT . ';', $c);
    }
    $c = preg_replace('/denyNoPermission\(\s*\'' . $esc . '\s*\)/u', 'denyNoPermission(' . $repT . ')', $c);
    $c = preg_replace('/throw\s+new\s+RuntimeException\(\s*\'' . $esc . '\s*\)/u', 'throw new RuntimeException(' . $repT . ')', $c);
}

// 动态拼接 / Flash
$c = str_replace(
    "\$message = '派送单已生成：' . \$flashCreatedDoc;\n            if (\$flashBoundPieces > 0) {\n                \$message .= '；绑定货件 ' . \$flashBoundPieces . ' 件';\n            }\n            \$message .= '。本单在下方「初步派送单列表」中；请先点「调整」保存增删，再点「转入正式派送单列表」进入正式流程（分段、拣货、指派司机）。';",
    "\$message = t('dispatch.flash.doc_created_prefix', '派送单已生成：') . \$flashCreatedDoc;\n            if (\$flashBoundPieces > 0) {\n                \$message .= sprintf(t('dispatch.flash.doc_created_bound', '；绑定货件 %d 件'), \$flashBoundPieces);\n            }\n            \$message .= t('dispatch.flash.doc_created_suffix', '。本单在下方「初步派送单列表」中；请先点「调整」保存增删，再点「转入正式派送单列表」进入正式流程（分段、拣货、指派司机）。');",
    $c
);

$c = str_replace(
    "\$message = '已保存对本单（' . \$flashAdjustedDoc . '）的客户调整。可再次点「调整」继续修改，确认无误后请点「转入正式派送单列表」。';",
    "\$message = sprintf(t('dispatch.flash.saved_adjust', '已保存对本单（%s）的客户调整。可再次点「调整」继续修改，确认无误后请点「转入正式派送单列表」。'), \$flashAdjustedDoc);",
    $c
);

$c = str_replace(
    "\$message = '已将派送单 ' . \$flashRevertedDoc . ' 从正式流程退回至本「初步派送单列表」。可点「调整」修改后，再点「转入正式派送单列表」。';",
    "\$message = sprintf(t('dispatch.flash.reverted_to_prelim', '已将派送单 %s 从正式流程退回至本「初步派送单列表」。可点「调整」修改后，再点「转入正式派送单列表」。'), \$flashRevertedDoc);",
    $c
);

$c = str_replace(
    "\$message = '已删除初步派送单 ' . \$docPost . ' ，绑定客户已回到「分配派送单」列表。';",
    "\$message = sprintf(t('dispatch.flash.deleted_prelim', '已删除初步派送单 %s ，绑定客户已回到「分配派送单」列表。'), \$docPost);",
    $c
);

$c = str_replace(
    "\$message = '已追加客户，新绑定货件 ' . (int)\$bindRes['affected'] . ' 件';",
    "\$message = sprintf(t('dispatch.flash.added_customers', '已追加客户，新绑定货件 %d 件'), (int)\$bindRes['affected']);",
    $c
);

$c = str_replace(
    "\$message = '已从「初步派送单」转入本列表：' . \$flashOpenedFormal . '。请先点「生成路线分段」；分段后可在本页指派司机，并与「派送单拣货表」并行出库。';",
    "\$message = sprintf(t('dispatch.flash.opened_formal', '已从「初步派送单」转入本列表：%s。请先点「生成路线分段」；分段后可在本页指派司机，并与「派送单拣货表」并行出库。'), \$flashOpenedFormal);",
    $c
);

$c = str_replace(
    "\$message = '已生成路线分段与司机端链接（每段最多 ' . (string)self::DRIVER_SEGMENT_CUSTOMER_COUNT . ' 位客户，有效期 7 天）。本单已进入「派送单拣货表」；请回本页「指派」司机（可与拣货并行）。';",
    "\$message = sprintf(t('dispatch.flash.segments_generated', '已生成路线分段与司机端链接（每段最多 %d 位客户，有效期 7 天）。本单已进入「派送单拣货表」；请回本页「指派」司机（可与拣货并行）。'), (int)self::DRIVER_SEGMENT_CUSTOMER_COUNT);",
    $c
);

$c = str_replace(
    "\$message = '客户已出库：' . \$code;",
    "\$message = sprintf(t('dispatch.flash.customer_outbound', '客户已出库：%s'), \$code);",
    $c
);

$c = str_replace(
    "\$message = '拣货单已完成：' . \$doc;",
    "\$message = sprintf(t('dispatch.flash.pick_complete', '拣货单已完成：%s'), \$doc);",
    $c
);

$c = str_replace(
    "throw new RuntimeException('未知的客户编码：' . \$code);",
    "throw new RuntimeException(t('dispatch.err.unknown_client_code', '未知的客户编码：') . \$code);",
    $c
);

// denyNoPermission 默认中文
$c = str_replace(
    "private function denyNoPermission(string \$message = '无权限执行此操作'): void\n    {\n        http_response_code(403);\n        echo \$message;",
    "private function denyNoPermission(string \$message = ''): void\n    {\n        http_response_code(403);\n        if (\$message === '') {\n            \$message = t('dispatch.str.0052', '无权限执行此操作');\n        }\n        echo \$message;",
    $c
);

file_put_contents($path, $c);
echo "Done.\n";
