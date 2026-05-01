<?php

/**
 * 派送客户结构化地址 → 完整泰文 / 完整英文 一行展示（由 7 段字段整合，不含小区名）。
 */
final class DeliveryAddressLines
{
    /**
     * @param array{
     *   addr_house_no?:string,
     *   addr_road_soi?:string,
     *   addr_moo_village?:string,
     *   addr_tambon?:string,
     *   addr_amphoe?:string,
     *   addr_province?:string,
     *   addr_zipcode?:string
     * } $p
     * @return array{th:string,en:string}
     */
    public static function composeFromParts(array $p): array
    {
        $h = trim((string)($p['addr_house_no'] ?? ''));
        $r = trim((string)($p['addr_road_soi'] ?? ''));
        $m = trim((string)($p['addr_moo_village'] ?? ''));
        $t = trim((string)($p['addr_tambon'] ?? ''));
        $a = trim((string)($p['addr_amphoe'] ?? ''));
        $pv = trim((string)($p['addr_province'] ?? ''));
        $z = trim((string)($p['addr_zipcode'] ?? ''));
        $provZip = trim($pv . ($pv !== '' && $z !== '' ? ' ' : '') . $z);
        $mid = [];
        foreach ([$h, $r, $m, $t, $a] as $seg) {
            if ($seg !== '') {
                $mid[] = $seg;
            }
        }
        $thParts = $mid;
        if ($provZip !== '') {
            $thParts[] = $provZip;
        }
        $th = implode(' ', $thParts);
        $enParts = $mid;
        if ($provZip !== '') {
            $enParts[] = $provZip;
        }
        $en = implode(', ', $enParts);
        return ['th' => $th, 'en' => $en];
    }
}
