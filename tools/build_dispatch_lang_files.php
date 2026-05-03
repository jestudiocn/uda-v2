<?php
/**
 * 从 dispatch_all_zh.txt 生成 dispatch-zh-CN.php（排除仅用于拼接的片段前缀）。
 */
$raw = file_get_contents(__DIR__ . '/dispatch_all_zh.txt');
$lines = array_values(array_filter(array_map('trim', explode('---', $raw))));
$omitPrefixes = [
    '客户已出库：',
    '已保存对本单（',
    '已删除初步派送单 ',
    '已将派送单 ',
    '已生成路线分段与司机端链接（每段最多 ',
    '已追加客户，新绑定货件 ',
    '拣货单已完成：',
    '未知的客户编码：',
    '派送单已生成：',
    '已从「初步派送单」转入本列表：',
];
$filtered = [];
foreach ($lines as $zh) {
    if ($zh === '') {
        continue;
    }
    $skip = false;
    foreach ($omitPrefixes as $p) {
        if ($zh === $p || str_starts_with($zh, $p)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }
    $filtered[$zh] = true;
}
$unique = array_keys($filtered);
sort($unique);

$keyByZh = [];
$i = 0;
foreach ($unique as $zh) {
    $i++;
    $keyByZh[$zh] = 'dispatch.str.' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
}

$php = "<?php\n\nreturn [\n";
foreach ($keyByZh as $zh => $key) {
    $php .= "    '" . addslashes($key) . "' => '" . addslashes($zh) . "',\n";
}
// 拼接型 Flash / 动态文案
$extra = [
    'dispatch.flash.doc_created_prefix' => '派送单已生成：',
    'dispatch.flash.doc_created_bound' => '；绑定货件 %d 件',
    'dispatch.flash.doc_created_suffix' => '。本单在下方「初步派送单列表」中；请先点「调整」保存增删，再点「转入正式派送单列表」进入正式流程（分段、拣货、指派司机）。',
    'dispatch.flash.saved_adjust' => '已保存对本单（%s）的客户调整。可再次点「调整」继续修改，确认无误后请点「转入正式派送单列表」。',
    'dispatch.flash.reverted_to_prelim' => '已将派送单 %s 从正式流程退回至本「初步派送单列表」。可点「调整」修改后，再点「转入正式派送单列表」。',
    'dispatch.flash.deleted_prelim' => '已删除初步派送单 %s ，绑定客户已回到「分配派送单」列表。',
    'dispatch.flash.added_customers' => '已追加客户，新绑定货件 %d 件',
    'dispatch.flash.opened_formal' => '已从「初步派送单」转入本列表：%s。请先点「生成路线分段」；分段后可在本页指派司机，并与「派送单拣货表」并行出库。',
    'dispatch.flash.segments_generated' => '已生成路线分段与司机端链接（每段最多 %d 位客户，有效期 7 天）。本单已进入「派送单拣货表」；请回本页「指派」司机（可与拣货并行）。',
    'dispatch.flash.customer_outbound' => '客户已出库：%s',
    'dispatch.flash.pick_complete' => '拣货单已完成：%s',
    'dispatch.err.unknown_client_code' => '未知的客户编码：',
];
foreach ($extra as $k => $v) {
    $php .= "    '" . addslashes($k) . "' => '" . addslashes($v) . "',\n";
}
$php .= "];\n";

file_put_contents(__DIR__ . '/../lang/dispatch-zh-CN.php', $php);
file_put_contents(__DIR__ . '/../lang/dispatch-key-by-zh.json', json_encode($keyByZh, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "Wrote " . count($keyByZh) . " auto keys + " . count($extra) . " extra keys.\n";
