<?php
require_once __DIR__ . '/Concerns/AuditLogTrait.php';
require_once __DIR__ . '/../Lib/DeliveryAddressLines.php';

/**
 * 派送业务：委托客户、派送客户（收件人）、面单（一原始面单一行）。
 */
class DispatchController
{
    use AuditLogTrait;
    private function writeAuditLog(
        mysqli $conn,
        string $moduleKey,
        string $actionKey,
        ?string $targetType = null,
        ?int $targetId = null,
        array $detail = []
    ): void {
        $this->writeStandardAuditLog($conn, $moduleKey, $actionKey, $targetType, $targetId, $detail);
    }

    private function denyNoPermission(string $message = '无权限执行此操作'): void
    {
        http_response_code(403);
        echo $message;
        exit;
    }

    private function hasAnyPermission(array $keys): bool
    {
        if (!function_exists('hasPermissionKey')) {
            return false;
        }
        foreach ($keys as $key) {
            if (hasPermissionKey((string)$key)) {
                return true;
            }
        }
        return false;
    }

    private function requireDispatchMenu(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问派送模块');
        }
    }

    private function tableExists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        $ok = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $ok;
    }

    private function ensureDispatchSchema(mysqli $conn): void
    {
        foreach (['dispatch_consigning_clients', 'dispatch_delivery_customers', 'dispatch_waybills'] as $t) {
            if (!$this->tableExists($conn, $t)) {
                throw new RuntimeException('派送表未建立，请先执行 migration：021_dispatch_core_tables.sql');
            }
        }
    }

    private function columnExists(mysqli $conn, string $table, string $column): bool
    {
        if (!$this->tableExists($conn, $table)) {
            return false;
        }
        $safeT = $conn->real_escape_string($table);
        $safeC = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeT}` LIKE '{$safeC}'");
        $ok = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $ok;
    }

    /**
     * 联合查询 `dispatch_delivery_customers`（别名 dc）时选用完整泰/英文地址列；未执行 migration 051 等时以空串占位，避免 Unknown column。
     */
    private function sqlJoinDeliveryCustomerAddrColumns(mysqli $conn, string $alias = 'dc'): string
    {
        $hasTh = $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_th_full');
        $hasEn = $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_en_full');
        $th = $hasTh ? "{$alias}.addr_th_full" : "'' AS addr_th_full";
        $en = $hasEn ? "{$alias}.addr_en_full" : "'' AS addr_en_full";

        return "{$th}, {$en}";
    }

    private function routePrimaryEqualsOt(?string $routePrimary): bool
    {
        return mb_strtoupper(trim((string)$routePrimary), 'UTF-8') === 'OT';
    }

    /**
     * 派送客户主路线从 OT 改为非 OT 时，删除转发客户库中同客户编码的记录（与 OT 自动推送规则对称）。
     */
    private function removeForwardCustomerAfterDeliveryRouteLeavesOt(
        mysqli $conn,
        string $customerCodeForForward,
        string $oldRoutePrimary,
        string $newRoutePrimary
    ): void {
        if (!$this->routePrimaryEqualsOt($oldRoutePrimary) || $this->routePrimaryEqualsOt($newRoutePrimary)) {
            return;
        }
        if (!$this->tableExists($conn, 'dispatch_forward_customers')) {
            return;
        }
        $code = trim($customerCodeForForward);
        if ($code === '') {
            return;
        }
        $stmt = $conn->prepare('DELETE FROM dispatch_forward_customers WHERE customer_code = ?');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $code);
        $affected = 0;
        try {
            $stmt->execute();
            $affected = (int)$stmt->affected_rows;
        } catch (Throwable $e) {
            $affected = 0;
        }
        $stmt->close();
        if ($affected > 0) {
            $this->writeAuditLog($conn, 'dispatch', 'dispatch.forwarding.customer.delete_on_ot_revoke', 'dispatch_forward_customer', null, [
                'customer_code' => $code,
                'old_route_primary' => trim((string)$oldRoutePrimary),
                'new_route_primary' => trim((string)$newRoutePrimary),
            ]);
        }
        $normSuppress = strtoupper(trim($code));
        if ($normSuppress !== '' && $this->tableExists($conn, 'dispatch_forward_customer_ot_sync_suppress')) {
            $sx = $conn->prepare('DELETE FROM dispatch_forward_customer_ot_sync_suppress WHERE customer_code = ?');
            if ($sx) {
                $sx->bind_param('s', $normSuppress);
                $sx->execute();
                $sx->close();
            }
        }
    }

    private function ensureDispatchOrderV2(mysqli $conn): void
    {
        if (!$this->columnExists($conn, 'dispatch_waybills', 'order_status')) {
            throw new RuntimeException('订单扩展字段未建立，请先执行 migration：022_dispatch_waybill_order_fields.sql');
        }
    }

    /** @return list<string> */
    private function orderStatusCatalog(): array
    {
        return ['待入库', '部分入库', '已入库', '待自取', '待转发', '已出库', '已自取', '已转发', '已派送', '问题件'];
    }

    private function resolvePage(): int
    {
        $page = (int)($_GET['page'] ?? 1);
        return $page > 0 ? $page : 1;
    }

    private function resolvePerPage(): int
    {
        $perPage = (int)($_GET['per_page'] ?? 20);
        return in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
    }

    /**
     * 定位：「纬度数字.小数,经度数字.小数」，仅数字与小数点，逗号两侧可空格；留空表示无坐标。
     *
     * @return array{ok: bool, lat: ?float, lng: ?float, error: string}
     */
    private function parseDeliveryGeoPosition(string $raw): array
    {
        $s = trim(str_replace('，', ',', trim($raw)));
        if ($s === '') {
            return ['ok' => true, 'lat' => null, 'lng' => null, 'error' => ''];
        }
        if (!preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $s)) {
            return [
                'ok' => false,
                'lat' => null,
                'lng' => null,
                'error' => '定位须为「纬度,经度」数字格式，中间逗号分隔，可含小数点（例：13.756331,100.501765 或 18.726413, 98.939623）',
            ];
        }
        $parts = preg_split('/\s*,\s*/', $s, 2);
        if ($parts === false || count($parts) !== 2) {
            return [
                'ok' => false,
                'lat' => null,
                'lng' => null,
                'error' => '定位解析失败',
            ];
        }
        $lat = (float)$parts[0];
        $lng = (float)$parts[1];
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return [
                'ok' => false,
                'lat' => null,
                'lng' => null,
                'error' => '纬度须在 -90～90、经度须在 -180～180 之间',
            ];
        }
        return ['ok' => true, 'lat' => $lat, 'lng' => $lng, 'error' => ''];
    }

    /** @return list<string> */
    private function deliveryCustomerStateCatalog(): array
    {
        return ['正常', '异常', '暂停', '转发'];
    }

    private function normalizeDeliveryCustomerState(?string $raw): string
    {
        $s = trim((string)$raw);
        return in_array($s, $this->deliveryCustomerStateCatalog(), true) ? $s : '正常';
    }

    private function buildRoutesCombined(string $rp, string $rs): string
    {
        $rp = trim($rp);
        $rs = trim($rs);
        if ($rp === '' && $rs === '') {
            return '';
        }
        if ($rp === '') {
            return $rs;
        }
        if ($rs === '') {
            return $rp;
        }
        return $rp . ' - ' . $rs;
    }

    private function normalizeDeliveryGeoStatus(string $routePrimary, ?float $lat, ?float $lng, string $rawStatus = ''): string
    {
        $raw = trim($rawStatus);
        $hasGeo = $lat !== null && $lng !== null;
        if ($hasGeo) {
            return '已定位';
        }
        $rp = strtoupper(trim($routePrimary));
        if ($rp === 'OT' || $rp === 'UDA') {
            return '免定位(OT/UDA)';
        }
        if ($raw === '待补定位(准客户)' || $raw === '缺失待补') {
            return $raw;
        }
        return '缺失待补';
    }

    /**
     * 由 7 段结构化地址生成完整泰文 / 完整英文一行（不含小区名）。
     *
     * @return array{th:string,en:string}
     */
    private function deliveryComposedFullAddresses(
        string $houseNo,
        string $roadSoi,
        string $mooVillage,
        string $tambon,
        string $amphoe,
        string $province,
        string $zipcode
    ): array {
        return DeliveryAddressLines::composeFromParts([
            'addr_house_no' => $houseNo,
            'addr_road_soi' => $roadSoi,
            'addr_moo_village' => $mooVillage,
            'addr_tambon' => $tambon,
            'addr_amphoe' => $amphoe,
            'addr_province' => $province,
            'addr_zipcode' => $zipcode,
        ]);
    }

    /**
     * @return array{0: ?int, 1: string, 2: string} [delivery_customer_id, match_status, normalized_code]
     */
    private function resolveDeliveryMatch(mysqli $conn, int $consigningClientId, string $deliveryCustomerCode): array
    {
        $code = trim($deliveryCustomerCode);
        if ($code === '') {
            return [null, 'no_recipient_code', ''];
        }
        $stmt = $conn->prepare('
            SELECT id FROM dispatch_delivery_customers
            WHERE consigning_client_id = ? AND customer_code = ? AND status = 1
            LIMIT 1
        ');
        if (!$stmt) {
            return [null, 'recipient_not_found', $code];
        }
        $stmt->bind_param('is', $consigningClientId, $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return [(int)$row['id'], 'matched', $code];
        }
        return [null, 'recipient_not_found', $code];
    }

    private function financePartiesForSelect(mysqli $conn): array
    {
        if (!$this->tableExists($conn, 'finance_parties')) {
            return [];
        }
        $rows = [];
        $res = $conn->query('SELECT id, party_name FROM finance_parties WHERE status = 1 ORDER BY party_name ASC');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function activeConsigningClients(mysqli $conn): array
    {
        $rows = [];
        $res = $conn->query('SELECT id, client_code, client_name FROM dispatch_consigning_clients WHERE status = 1 ORDER BY client_code ASC');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        return $rows;
    }

    /** @return ?array<string, mixed> */
    private function consigningClientRowById(mysqli $conn, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare('
            SELECT id, client_code, client_name, status
            FROM dispatch_consigning_clients
            WHERE id = ?
            LIMIT 1
        ');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? $row : null;
    }

    /** 按启用中的委托客户编号解析 id；不存在或未启用返回 0 */
    private function consigningClientIdByCode(mysqli $conn, string $clientCode): int
    {
        $code = trim($clientCode);
        if ($code === '') {
            return 0;
        }
        $stmt = $conn->prepare('SELECT id FROM dispatch_consigning_clients WHERE client_code = ? AND status = 1 LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $code);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return 0;
        }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }

    /**
     * @param list<array<string, mixed>> $clients
     * @return array{id: int, must_select: bool, single: bool}
     */
    private function resolveConsigningFilterForOrders(array $clients, int $requestedId): array
    {
        $n = count($clients);
        if ($n === 0) {
            return ['id' => 0, 'must_select' => false, 'single' => false];
        }
        if ($n === 1) {
            return ['id' => (int)$clients[0]['id'], 'must_select' => false, 'single' => true];
        }
        if ($requestedId > 0) {
            foreach ($clients as $c) {
                if ((int)$c['id'] === $requestedId) {
                    return ['id' => $requestedId, 'must_select' => false, 'single' => false];
                }
            }
        }
        // 内部账号默认可看全量，不再强制先选委托客户。
        return ['id' => 0, 'must_select' => false, 'single' => false];
    }

    private function sendCsvDownload(string $filename, string $body): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        echo "\xEF\xBB\xBF" . $body;
        exit;
    }

    /** 将 CSV 表头（中文或旧英文别名）规范为程序内部使用的英文字段名 */
    private function canonicalOrderImportCsvField(string $rawKey): string
    {
        $k = trim($rawKey);
        if (str_starts_with($k, "\xEF\xBB\xBF")) {
            $k = trim(substr($k, 3));
        }
        if ($k === '') {
            return '';
        }
        static $aliases = [
            'consigning_client_code' => 'consigning_client_code',
            'original_tracking_no' => 'original_tracking_no',
            'original_order_id' => 'original_tracking_no',
            'delivery_customer_code' => 'delivery_customer_code',
            'delivery_order_id' => 'delivery_customer_code',
            'weight_kg' => 'weight_kg',
            'length_cm' => 'length_cm',
            'width_cm' => 'width_cm',
            'height_cm' => 'height_cm',
            'volume_m3' => 'volume_m3',
            'quantity' => 'quantity',
            'inbound_batch' => 'inbound_batch',
            'order_status' => 'order_status',
            '委托客户编码' => 'consigning_client_code',
            '委托客户编号' => 'consigning_client_code',
            '委托客户' => 'consigning_client_code',
            '原始单号' => 'original_tracking_no',
            '原始订单号' => 'original_tracking_no',
            '厂家批号' => 'original_tracking_no',
            '派送客户编号' => 'delivery_customer_code',
            '派送单号' => 'delivery_customer_code',
            '派送客户' => 'delivery_customer_code',
            '重量(kg)' => 'weight_kg',
            '重量（kg）' => 'weight_kg',
            '重量kg' => 'weight_kg',
            '毛重(kg)' => 'weight_kg',
            '毛重（kg）' => 'weight_kg',
            '长(cm)' => 'length_cm',
            '长（cm）' => 'length_cm',
            '长' => 'length_cm',
            '宽(cm)' => 'width_cm',
            '宽（cm）' => 'width_cm',
            '宽' => 'width_cm',
            '高(cm)' => 'height_cm',
            '高（cm）' => 'height_cm',
            '高' => 'height_cm',
            '体积(m³)' => 'volume_m3',
            '体积(m3)' => 'volume_m3',
            '体积（m³）' => 'volume_m3',
            '体积（m3）' => 'volume_m3',
            '数量' => 'quantity',
            '入库批次' => 'inbound_batch',
            '订单状态' => 'order_status',
        ];
        return $aliases[$k] ?? $k;
    }

    /** 派送客户 CSV 表头 → 内部字段名（无法识别的表头返回空字符串，导入时忽略该列） */
    private function canonicalDeliveryImportCsvField(string $rawKey): string
    {
        $k = trim($rawKey);
        if (str_starts_with($k, "\xEF\xBB\xBF")) {
            $k = trim(substr($k, 3));
        }
        if ($k === '') {
            return '';
        }
        static $aliases = [
            'consigning_client_code' => 'consigning_client_code',
            '委托客户编码' => 'consigning_client_code',
            '委托客户编号' => 'consigning_client_code',
            'customer_code' => 'customer_code',
            '派送客户编号' => 'customer_code',
            '客户编号' => 'customer_code',
            'wechat_id' => 'wechat_id',
            '微信号' => 'wechat_id',
            '微信' => 'wechat_id',
            'line_id' => 'line_id',
            'Line' => 'line_id',
            'LINE' => 'line_id',
            'line' => 'line_id',
            'recipient_name' => 'recipient_name',
            '收件人' => 'recipient_name',
            'phone' => 'phone',
            '电话' => 'phone',
            '电话号' => 'phone',
            '电话号码' => 'phone',
            '联系电话' => 'phone',
            'lane_or_house_no' => 'addr_house_no',
            '巷/门牌号' => 'addr_house_no',
            '巷/门牌' => 'addr_house_no',
            'address_main' => 'addr_road_soi',
            '地址（不含巷/门牌）' => 'addr_road_soi',
            '地址(不含巷/门牌)' => 'addr_road_soi',
            '地址（不包含门牌）' => 'addr_road_soi',
            '地址(不包含门牌)' => 'addr_road_soi',
            '地址 (不包含门牌)' => 'addr_road_soi',
            '地址（不含门牌）' => 'addr_road_soi',
            '地址(不含门牌)' => 'addr_road_soi',
            '地址不含门牌' => 'addr_road_soi',
            'addr_house_no' => 'addr_house_no',
            'house_number' => 'addr_house_no',
            'House Number' => 'addr_house_no',
            'บ้านเลขที่' => 'addr_house_no',
            '门牌号' => 'addr_house_no',
            'road_soi' => 'addr_road_soi',
            'Road(Soi)' => 'addr_road_soi',
            'Road（Soi）' => 'addr_road_soi',
            'ถนน（ซอย）' => 'addr_road_soi',
            '路（巷）' => 'addr_road_soi',
            'moo_village' => 'addr_moo_village',
            'Moo(Village)' => 'addr_moo_village',
            'Moo（Village）' => 'addr_moo_village',
            'หมู่บ้าน' => 'addr_moo_village',
            '村' => 'addr_moo_village',
            'tambon' => 'addr_tambon',
            'Tambon(Subdistrict)' => 'addr_tambon',
            'Tambon（Subdistrict）' => 'addr_tambon',
            'ตำบล（แขวง）' => 'addr_tambon',
            '镇（街道）（乡）' => 'addr_tambon',
            'amphoe' => 'addr_amphoe',
            'Amphoe(District)' => 'addr_amphoe',
            'Amphoe （District）' => 'addr_amphoe',
            'อำเภอ（เขต）' => 'addr_amphoe',
            '县（区）' => 'addr_amphoe',
            'province' => 'addr_province',
            'Province' => 'addr_province',
            '府' => 'addr_province',
            'zipcode' => 'addr_zipcode',
            'Zipcode' => 'addr_zipcode',
            '邮编' => 'addr_zipcode',
            'geo_status' => 'geo_status',
            '定位状态' => 'geo_status',
            'geo_position' => 'geo_position',
            '定位' => 'geo_position',
            'GPS' => 'geo_position',
            '坐标' => 'geo_position',
            'route_primary' => 'route_primary',
            '主路线' => 'route_primary',
            'route_secondary' => 'route_secondary',
            '副路线' => 'route_secondary',
            'community_name_en' => 'community_name_en',
            '小区英文名' => 'community_name_en',
            'community_name_th' => 'community_name_th',
            '小区泰文名' => 'community_name_th',
            '小区泰语名' => 'community_name_th',
            'customer_state' => 'customer_state',
            '客户状态' => 'customer_state',
            'customer_requirement' => 'customer_requirements',
            'customer_requirements' => 'customer_requirements',
            '客户要求' => 'customer_requirements',
            '客户需求' => 'customer_requirements',
        ];
        return $aliases[$k] ?? '';
    }

    /**
     * 未执行 022 迁移时的写入（无 order_status / import_date 等字段）。
     *
     * @return array{ok: bool, error: string}
     */
    private function insertWaybillRowLegacy(
        mysqli $conn,
        int $ccId,
        string $track,
        string $dcode,
        float $weightF,
        float $volumeF,
        float $qtyF,
        string $batch,
        string $source,
        int $actorId
    ): array {
        [$dcId, $matchStatus, $storedCode] = $this->resolveDeliveryMatch($conn, $ccId, $dcode);
        $stmt = null;
        $withActor = $actorId > 0;
        if ($dcId === null) {
            if ($withActor) {
                $stmt = $conn->prepare('
                    INSERT INTO dispatch_waybills (
                        consigning_client_id, original_tracking_no, delivery_customer_code,
                        delivery_customer_id, weight_kg, volume_m3, quantity, inbound_batch,
                        source, match_status, created_by
                    ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)
                ');
                if ($stmt) {
                    $stmt->bind_param(
                        'issddssssi',
                        $ccId,
                        $track,
                        $storedCode,
                        $weightF,
                        $volumeF,
                        $qtyF,
                        $batch,
                        $source,
                        $matchStatus,
                        $actorId
                    );
                }
            } else {
                $stmt = $conn->prepare('
                    INSERT INTO dispatch_waybills (
                        consigning_client_id, original_tracking_no, delivery_customer_code,
                        delivery_customer_id, weight_kg, volume_m3, quantity, inbound_batch,
                        source, match_status
                    ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
                ');
                if ($stmt) {
                    $stmt->bind_param(
                        'issddssss',
                        $ccId,
                        $track,
                        $storedCode,
                        $weightF,
                        $volumeF,
                        $qtyF,
                        $batch,
                        $source,
                        $matchStatus
                    );
                }
            }
        } elseif ($withActor) {
            $stmt = $conn->prepare('
                INSERT INTO dispatch_waybills (
                    consigning_client_id, original_tracking_no, delivery_customer_code,
                    delivery_customer_id, weight_kg, volume_m3, quantity, inbound_batch,
                    source, match_status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            if ($stmt) {
                $stmt->bind_param(
                    'issiddssssi',
                    $ccId,
                    $track,
                    $storedCode,
                    $dcId,
                    $weightF,
                    $volumeF,
                    $qtyF,
                    $batch,
                    $source,
                    $matchStatus,
                    $actorId
                );
            }
        } else {
            $stmt = $conn->prepare('
                INSERT INTO dispatch_waybills (
                    consigning_client_id, original_tracking_no, delivery_customer_code,
                    delivery_customer_id, weight_kg, volume_m3, quantity, inbound_batch,
                    source, match_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            if ($stmt) {
                $stmt->bind_param(
                    'issiddssss',
                    $ccId,
                    $track,
                    $storedCode,
                    $dcId,
                    $weightF,
                    $volumeF,
                    $qtyF,
                    $batch,
                    $source,
                    $matchStatus
                );
            }
        }
        if (!$stmt) {
            return ['ok' => false, 'error' => '保存失败'];
        }
        try {
            $stmt->execute();
            $stmt->close();
            return ['ok' => true, 'error' => ''];
        } catch (mysqli_sql_exception $e) {
            $errno = (int)$e->getCode();
            $stmt->close();
            if ($errno === 1062) {
                return ['ok' => false, 'error' => 'duplicate'];
            }
            return ['ok' => false, 'error' => '保存失败'];
        }
    }

    /**
     * @return array{ok: bool, error: string}
     */
    private function insertWaybillRow(
        mysqli $conn,
        int $ccId,
        string $track,
        string $dcode,
        float $weightF,
        float $lengthF,
        float $widthF,
        float $heightF,
        float $volumeF,
        float $qtyF,
        string $batch,
        string $source,
        int $actorId,
        string $importDate,
        string $orderStatus
    ): array {
        if (!$this->columnExists($conn, 'dispatch_waybills', 'order_status')) {
            return $this->insertWaybillRowLegacy($conn, $ccId, $track, $dcode, $weightF, $volumeF, $qtyF, $batch, $source, $actorId);
        }
        $orderStatus = trim($orderStatus) !== '' ? trim($orderStatus) : '待入库';
        if (!in_array($orderStatus, $this->orderStatusCatalog(), true)) {
            $orderStatus = '待入库';
        }
        [$dcId, $matchStatus, $storedCode] = $this->resolveDeliveryMatch($conn, $ccId, $dcode);
        $stmt = null;
        $withActor = $actorId > 0;
        if ($dcId === null) {
            if ($withActor) {
                $stmt = $conn->prepare('
                    INSERT INTO dispatch_waybills (
                        consigning_client_id, original_tracking_no, delivery_customer_code,
                        delivery_customer_id, weight_kg, length_cm, width_cm, height_cm, volume_m3, quantity, inbound_batch,
                        source, match_status, order_status, import_date, scanned_at, delivered_at, created_by
                    ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), ?)
                ');
                if ($stmt) {
                    $bt0 = 'iss' . 'dddd' . 'd' . str_repeat('s', 5) . 'i';
                    $stmt->bind_param(
                        $bt0,
                        $ccId,
                        $track,
                        $storedCode,
                        $weightF,
                        $lengthF,
                        $widthF,
                        $heightF,
                        $volumeF,
                        $qtyF,
                        $batch,
                        $source,
                        $matchStatus,
                        $orderStatus,
                        $importDate,
                        $actorId
                    );
                }
            } else {
                $stmt = $conn->prepare('
                    INSERT INTO dispatch_waybills (
                        consigning_client_id, original_tracking_no, delivery_customer_code,
                        delivery_customer_id, weight_kg, length_cm, width_cm, height_cm, volume_m3, quantity, inbound_batch,
                        source, match_status, order_status, import_date, scanned_at, delivered_at
                    ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())
                ');
                if ($stmt) {
                    $stmt->bind_param(
                        'iss' . 'dddd' . 'd' . str_repeat('s', 5),
                        $ccId,
                        $track,
                        $storedCode,
                        $weightF,
                        $lengthF,
                        $widthF,
                        $heightF,
                        $volumeF,
                        $qtyF,
                        $batch,
                        $source,
                        $matchStatus,
                        $orderStatus,
                        $importDate
                    );
                }
            }
        } elseif ($withActor) {
            $stmt = $conn->prepare('
                INSERT INTO dispatch_waybills (
                    consigning_client_id, original_tracking_no, delivery_customer_code,
                    delivery_customer_id, weight_kg, length_cm, width_cm, height_cm, volume_m3, quantity, inbound_batch,
                    source, match_status, order_status, import_date, scanned_at, delivered_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), ?)
            ');
            if ($stmt) {
                $bt = 'iss' . 'i' . 'dddd' . 'd' . str_repeat('s', 5) . 'i';
                $stmt->bind_param(
                    $bt,
                    $ccId,
                    $track,
                    $storedCode,
                    $dcId,
                    $weightF,
                    $lengthF,
                    $widthF,
                    $heightF,
                    $volumeF,
                    $qtyF,
                    $batch,
                    $source,
                    $matchStatus,
                    $orderStatus,
                    $importDate,
                    $actorId
                );
            }
        } else {
            $stmt = $conn->prepare('
                INSERT INTO dispatch_waybills (
                    consigning_client_id, original_tracking_no, delivery_customer_code,
                    delivery_customer_id, weight_kg, length_cm, width_cm, height_cm, volume_m3, quantity, inbound_batch,
                    source, match_status, order_status, import_date, scanned_at, delivered_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())
            ');
            if ($stmt) {
                $stmt->bind_param(
                    'iss' . 'i' . 'dddd' . 'd' . str_repeat('s', 5),
                    $ccId,
                    $track,
                    $storedCode,
                    $dcId,
                    $weightF,
                    $lengthF,
                    $widthF,
                    $heightF,
                    $volumeF,
                    $qtyF,
                    $batch,
                    $source,
                    $matchStatus,
                    $orderStatus,
                    $importDate
                );
            }
        }
        if (!$stmt) {
            return ['ok' => false, 'error' => '保存失败'];
        }
        try {
            $stmt->execute();
            $stmt->close();
            return ['ok' => true, 'error' => ''];
        } catch (mysqli_sql_exception $e) {
            $errno = (int)$e->getCode();
            $stmt->close();
            if ($errno === 1062) {
                return ['ok' => false, 'error' => 'duplicate'];
            }
            return ['ok' => false, 'error' => '保存失败'];
        }
    }

    /**
     * 行内修改订单的派送客户编号，并重算匹配结果。
     *
     * @return array{ok: bool, error: string, row: ?array<string, mixed>}
     */
    private function updateWaybillDeliveryCustomerCode(mysqli $conn, int $waybillId, string $newCode): array
    {
        if ($waybillId <= 0) {
            return ['ok' => false, 'error' => '参数无效', 'row' => null];
        }
        $stmt = $conn->prepare('SELECT id, consigning_client_id, delivery_customer_code, order_status FROM dispatch_waybills WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'error' => '查询失败', 'row' => null];
        }
        $stmt->bind_param('i', $waybillId);
        $stmt->execute();
        $base = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$base) {
            return ['ok' => false, 'error' => '订单不存在', 'row' => null];
        }
        $ccId = (int)($base['consigning_client_id'] ?? 0);
        [$dcId, $matchStatus, $storedCode] = $this->resolveDeliveryMatch($conn, $ccId, trim($newCode));
        if ($dcId === null) {
            $up = $conn->prepare('
                UPDATE dispatch_waybills
                SET delivery_customer_code = ?, delivery_customer_id = NULL, match_status = ?, order_status = ?, delivered_at = NOW()
                WHERE id = ?
                LIMIT 1
            ');
            if (!$up) {
                return ['ok' => false, 'error' => '更新失败', 'row' => null];
            }
            $resetStatus = '待入库';
            $up->bind_param('sssi', $storedCode, $matchStatus, $resetStatus, $waybillId);
        } else {
            $up = $conn->prepare('
                UPDATE dispatch_waybills
                SET delivery_customer_code = ?, delivery_customer_id = ?, match_status = ?, order_status = ?, delivered_at = NOW()
                WHERE id = ?
                LIMIT 1
            ');
            if (!$up) {
                return ['ok' => false, 'error' => '更新失败', 'row' => null];
            }
            $resetStatus = '待入库';
            $up->bind_param('sissi', $storedCode, $dcId, $matchStatus, $resetStatus, $waybillId);
        }
        $ok = $up->execute();
        $up->close();
        if (!$ok) {
            return ['ok' => false, 'error' => '更新失败', 'row' => null];
        }
        $this->writeAuditLog(
            $conn,
            'dispatch',
            'dispatch.waybill.customer_code.update',
            'waybill',
            $waybillId,
            [
                'old_customer_code' => (string)($base['delivery_customer_code'] ?? ''),
                'new_customer_code' => $storedCode,
                'old_order_status' => (string)($base['order_status'] ?? ''),
                'new_order_status' => '待入库',
            ]
        );
        $addrCols = $this->sqlJoinDeliveryCustomerAddrColumns($conn, 'dc');
        $q = $conn->prepare("
            SELECT
                w.id, w.delivery_customer_code, w.match_status,
                dc.wechat_id, dc.line_id, {$addrCols}, dc.latitude, dc.longitude,
                dc.route_primary, dc.route_secondary, dc.routes_combined,
                dc.community_name_en, dc.community_name_th
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE w.id = ?
            LIMIT 1
        ");
        if (!$q) {
            return ['ok' => true, 'error' => '', 'row' => null];
        }
        $q->bind_param('i', $waybillId);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        return ['ok' => true, 'error' => '', 'row' => is_array($row) ? $row : null];
    }

    private function normalizeArrivalScanTrackingNo(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        // 扫码枪可能附带后缀（如 @123），比对原始单号前先去掉。
        $s = (string)preg_replace('/@\d+$/', '', $s);
        return trim($s);
    }

    private function buildAbsoluteUrl(string $pathWithQuery): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $scheme = $https ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
        return $scheme . '://' . $host . $pathWithQuery;
    }

    private function pdfEscape(string $text): string
    {
        $text = str_replace("\\", "\\\\", $text);
        $text = str_replace("(", "\\(", $text);
        $text = str_replace(")", "\\)", $text);
        return str_replace(["\r", "\n"], ' ', $text);
    }

    private function renderArrivalLabelHtml(array $label): string
    {
        $rp = trim((string)($label['route_primary'] ?? ''));
        $rs = trim((string)($label['route_secondary'] ?? ''));
        $routes = ($rp !== '' && $rs !== '') ? ($rp . ' / ' . $rs) : ($rp !== '' ? $rp : ($rs !== '' ? $rs : '-'));
        $esc = static function ($v): string {
            return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        };
        $html = '<!doctype html><html><head><meta charset="utf-8">';
        $html .= '<style>';
        $html .= '@page{size:75mm 100mm;margin:0;}';
        $html .= 'html,body{margin:0;padding:0;width:75mm;height:100mm;font-family:Tahoma,"Noto Sans Thai","Segoe UI",Arial,sans-serif;color:#111;overflow:hidden;}';
        $html .= '.wrap{width:75mm;height:100mm;box-sizing:border-box;padding:4mm;}';
        $html .= '.line{margin-bottom:2.4mm;font-size:10.5pt;line-height:1.2;}';
        $html .= '.line:last-child{margin-bottom:0;}';
        $html .= '.k{display:inline-block;min-width:24mm;font-weight:700;}';
        $html .= '.v{font-weight:600;}';
        $html .= '</style></head><body><div class="wrap">';
        $html .= '<div class="line"><span class="k">客户编码</span><span class="v">' . $esc($label['customer_code'] ?? '-') . '</span></div>';
        $html .= '<div class="line"><span class="k">微信号</span><span class="v">' . $esc($label['wechat_id'] ?? '-') . '</span></div>';
        $html .= '<div class="line"><span class="k">主/副路线</span><span class="v">' . $esc($routes) . '</span></div>';
        $html .= '<div class="line"><span class="k">泰文小区</span><span class="v">' . $esc($label['community_name_th'] ?? '-') . '</span></div>';
        $addrLine = trim((string)($label['addr_th_full'] ?? ''));
        if ($addrLine === '') {
            $addrLine = trim((string)($label['lane_or_house_no'] ?? ''));
        }
        $html .= '<div class="line"><span class="k">完整地址</span><span class="v">' . $esc($addrLine !== '' ? $addrLine : '-') . '</span></div>';
        $html .= '<div class="line"><span class="k">未派送总件数</span><span class="v">' . $esc($label['pending_count'] ?? 0) . '</span></div>';
        $html .= '<div class="line"><span class="k">原始单号</span><span class="v">' . $esc($label['tracking_no'] ?? '-') . '</span></div>';
        $html .= '</div></body></html>';
        return $html;
    }

    private function browserExecutablePaths(): array
    {
        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];
        $paths = [];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                $paths[] = $p;
            }
        }
        return $paths;
    }

    private function localFileUri(string $path): string
    {
        $norm = str_replace('\\', '/', $path);
        if (!preg_match('/^[A-Za-z]:\//', $norm)) {
            return 'file://' . $norm;
        }
        $parts = explode('/', $norm);
        $drive = array_shift($parts);
        $encParts = [];
        foreach ($parts as $part) {
            $encParts[] = rawurlencode($part);
        }
        return 'file:///' . $drive . '/' . implode('/', $encParts);
    }

    private function generateArrivalLabelPdfFile(string $token, ?string &$reason = null): ?string
    {
        $reason = '';
        $dir = rtrim((string)sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'uda-arrival-label-pdf';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $pdfPath = $dir . DIRECTORY_SEPARATOR . $token . '.pdf';
        $htmlPath = $dir . DIRECTORY_SEPARATOR . $token . '.html';
        $browsers = $this->browserExecutablePaths();
        if (!$browsers) {
            $reason = 'browser_not_found';
            return null;
        }
        $storeDir = __DIR__ . '/../../storage/arrival-label-pdf';
        $jsonPath = $storeDir . '/' . $token . '.json';
        if (!is_file($jsonPath)) {
            $reason = 'token_not_found';
            return null;
        }
        $raw = @file_get_contents($jsonPath);
        $item = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($item)) {
            $reason = 'token_invalid';
            return null;
        }
        $expiresAt = (int)($item['expires_at'] ?? 0);
        if ($expiresAt <= 0 || $expiresAt < time()) {
            $reason = 'token_expired';
            return null;
        }
        $label = is_array($item['label'] ?? null) ? $item['label'] : [];
        $html = $this->renderArrivalLabelHtml($label);
        if ($html === '' || @file_put_contents($htmlPath, $html) === false) {
            $reason = 'html_write_failed';
            return null;
        }
        $htmlUrl = $this->localFileUri($htmlPath);
        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }
        $profileDir = $dir . DIRECTORY_SEPARATOR . 'profile';
        if (!is_dir($profileDir)) {
            @mkdir($profileDir, 0777, true);
        }
        foreach ($browsers as $browserPath) {
            if (is_file($pdfPath)) {
                @unlink($pdfPath);
            }
            $cmd = [
                $browserPath,
                '--headless',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--no-first-run',
                '--no-default-browser-check',
                '--user-data-dir=' . $profileDir,
                '--print-to-pdf=' . $pdfPath,
                $htmlUrl,
            ];
            $desc = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($cmd, $desc, $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($proc)) {
                $reason = 'proc_open_failed_' . basename($browserPath);
                continue;
            }
            $startAt = microtime(true);
            $timedOut = false;
            while (true) {
                $status = proc_get_status($proc);
                if (!is_array($status) || empty($status['running'])) {
                    break;
                }
                if ((microtime(true) - $startAt) >= 4.5) {
                    $timedOut = true;
                    @proc_terminate($proc, 9);
                    break;
                }
                usleep(100000);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    @fclose($pipe);
                }
            }
            $code = @proc_close($proc);
            if ($timedOut) {
                $reason = 'render_timeout_' . basename($browserPath);
                continue;
            }
            if ($code !== 0) {
                $reason = 'browser_exit_' . (string)$code . '_' . basename($browserPath);
                continue;
            }
            if (!is_file($pdfPath) || (int)@filesize($pdfPath) <= 0) {
                $reason = 'pdf_not_generated_' . basename($browserPath);
                continue;
            }
            $reason = 'ok_' . basename($browserPath);
            return $pdfPath;
        }
        return null;
    }

    private function renderArrivalLabelPdfBinary(array $label): string
    {
        $wPt = 100.0 / 25.4 * 72.0;
        $hPt = 75.0 / 25.4 * 72.0;
        $rp = trim((string)($label['route_primary'] ?? ''));
        $rs = trim((string)($label['route_secondary'] ?? ''));
        $routes = ($rp !== '' && $rs !== '') ? ($rp . ' / ' . $rs) : ($rp !== '' ? $rp : ($rs !== '' ? $rs : '-'));
        $lines = [
            '客户编码: ' . (string)($label['customer_code'] ?? '-'),
            '微信号: ' . (string)($label['wechat_id'] ?? '-'),
            '主/副路线: ' . $routes,
            '泰文小区: ' . (string)($label['community_name_th'] ?? '-'),
            '完整地址: ' . (trim((string)($label['addr_th_full'] ?? '')) !== '' ? (string)$label['addr_th_full'] : (string)($label['lane_or_house_no'] ?? '-')),
            '未派送总件数: ' . (string)($label['pending_count'] ?? '0'),
            '原始单号: ' . (string)($label['tracking_no'] ?? '-'),
        ];
        $y = $hPt - 20.0;
        $lineGap = 18.0;
        $stream = "BT\n/F1 11 Tf\n";
        foreach ($lines as $line) {
            $stream .= sprintf("1 0 0 1 10 %.2f Tm (%s) Tj\n", $y, $this->pdfEscape($line));
            $y -= $lineGap;
            if ($y < 12) {
                break;
            }
        }
        $stream .= "ET\n";
        $len = strlen($stream);
        $objs = [];
        $objs[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objs[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objs[] = sprintf(
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            $wPt,
            $hPt
        );
        $objs[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objs[] = "5 0 obj\n<< /Length {$len} >>\nstream\n{$stream}endstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objs as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj;
        }
        $xrefPos = strlen($pdf);
        $count = count($objs) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        return $pdf;
    }

    private function issueArrivalLabelPdfUrl(array $label, ?string &$pdfMode = null, ?string &$pdfDebug = null): string
    {
        $dir = __DIR__ . '/../../storage/arrival-label-pdf';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $token = bin2hex(random_bytes(16));
        $payload = [
            'expires_at' => time() + 300,
            'label' => $label,
        ];
        @file_put_contents($dir . '/' . $token . '.json', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $pdfReason = '';
        $pdfFile = $this->generateArrivalLabelPdfFile($token, $pdfReason);
        $pdfMode = ($pdfFile !== null && is_file($pdfFile) && (int)@filesize($pdfFile) > 0) ? 'edge' : 'fallback';
        $pdfDebug = $pdfReason;
        return $this->buildAbsoluteUrl('/dispatch/arrival-label-pdf?t=' . urlencode($token));
    }

    /**
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   tracking_no?: string,
     *   status?: string,
     *   label?: array<string, mixed>,
     *   pdf_url?: string
     * }
     */
    private function handleArrivalScanSubmit(mysqli $conn, string $rawTrackingNo): array
    {
        $trackingNo = $this->normalizeArrivalScanTrackingNo($rawTrackingNo);
        if ($trackingNo === '') {
            return ['ok' => false, 'error' => '请输入或扫描单号', 'code' => 'empty'];
        }
        $hasAutoForwardOptOut = $this->columnExists($conn, 'dispatch_waybills', 'auto_forward_opt_out');
        $hasInboundScanCount = $this->columnExists($conn, 'dispatch_waybills', 'inbound_scan_count');
        $optOutSelect = $hasAutoForwardOptOut
            ? 'COALESCE(w.auto_forward_opt_out, 0) AS auto_forward_opt_out,'
            : '0 AS auto_forward_opt_out,';
        $scanCountSelect = $hasInboundScanCount
            ? 'COALESCE(w.inbound_scan_count, 0) AS inbound_scan_count,'
            : '0 AS inbound_scan_count,';
        $scanCountUpdate = $hasInboundScanCount
            ? ', inbound_scan_count = inbound_scan_count + 1'
            : '';
        $stmt = $conn->prepare("
            SELECT
                w.id,
                w.original_tracking_no,
                w.order_status,
                {$optOutSelect}
                {$scanCountSelect}
                COALESCE(w.quantity, 1) AS quantity,
                w.delivery_customer_id,
                w.delivery_customer_code,
                dc.customer_code,
                dc.wechat_id,
                dc.route_primary,
                dc.customer_state,
                dc.route_secondary,
                dc.community_name_th,
                dc.addr_th_full
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE w.original_tracking_no = ?
            ORDER BY w.id DESC
            LIMIT 1
        ");
        if (!$stmt) {
            return ['ok' => false, 'error' => '查询失败，请稍后再试', 'code' => 'db_error'];
        }
        $stmt->bind_param('s', $trackingNo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return [
                'ok' => false,
                'error' => '无此单号',
                'code' => 'no_waybill',
                'tracking_no' => $trackingNo,
            ];
        }

        $waybillId = (int)($row['id'] ?? 0);
        $currentStatus = trim((string)($row['order_status'] ?? ''));
        $closingOrDone = ['已派送', '已出库', '已自取', '已转发', '待自取', '待转发'];
        if (in_array($currentStatus, $closingOrDone, true)) {
            $this->writeAuditLog(
                $conn,
                'dispatch',
                'dispatch.package_ops.arrival_scan.blocked',
                'waybill',
                $waybillId,
                ['tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo), 'status' => $currentStatus]
            );
            return [
                'ok' => false,
                'error' => '订单即将完成或已完成',
                'code' => 'order_near_or_done',
                'tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo),
                'status' => $currentStatus,
            ];
        }
        $deliveryId = (int)($row['delivery_customer_id'] ?? 0);
        $matchedCode = trim((string)($row['customer_code'] ?? ''));
        if ($matchedCode === '') {
            $matchedCode = trim((string)($row['delivery_customer_code'] ?? ''));
        }
        if ($deliveryId <= 0 || $matchedCode === '') {
            $up = $conn->prepare('
                UPDATE dispatch_waybills
                SET order_status = ?, scanned_at = NOW(), delivered_at = NOW()' . $scanCountUpdate . '
                WHERE id = ?
                LIMIT 1
            ');
            if (!$up) {
                return ['ok' => false, 'error' => '更新失败，请稍后再试', 'code' => 'db_error'];
            }
            $problemStatus = '问题件';
            $up->bind_param('si', $problemStatus, $waybillId);
            $up->execute();
            $up->close();
            $this->writeAuditLog(
                $conn,
                'dispatch',
                'dispatch.package_ops.arrival_scan.problem',
                'waybill',
                $waybillId,
                ['tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo), 'reason' => 'no_customer_code']
            );
            return [
                'ok' => false,
                'error' => '无客户编码',
                'code' => 'no_customer_code',
                'tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo),
                'status' => $problemStatus,
            ];
        }

        $routePrimary = trim((string)($row['route_primary'] ?? ''));
        $customerState = trim((string)($row['customer_state'] ?? ''));
        $isOtRoute = mb_strtoupper($routePrimary, 'UTF-8') === 'OT';
        $isUdaRoute = mb_strtoupper($routePrimary, 'UTF-8') === 'UDA';
        $isForwardState = $customerState === '转发';
        $optOut = (int)($row['auto_forward_opt_out'] ?? 0) === 1;
        $newScanCount = ((int)($row['inbound_scan_count'] ?? 0)) + 1;
        $requiredQty = (int)ceil(max(1.0, (float)($row['quantity'] ?? 1)));
        if ($newScanCount < $requiredQty) {
            $inboundStatus = '部分入库';
        } else {
            // 达到件数后：客户状态=转发优先；其次 UDA 走待自取；再次 OT 走待转发；其余已入库
            if ($isForwardState) {
                $inboundStatus = '待转发';
            } elseif ($isUdaRoute) {
                $inboundStatus = '待自取';
            } elseif ($isOtRoute && !$optOut) {
                $inboundStatus = '待转发';
            } else {
                $inboundStatus = '已入库';
            }
        }
        $up = $conn->prepare('
            UPDATE dispatch_waybills
            SET order_status = ?, scanned_at = NOW(), delivered_at = NOW()' . $scanCountUpdate . '
            WHERE id = ?
            LIMIT 1
        ');
        if (!$up) {
            return ['ok' => false, 'error' => '更新失败，请稍后再试', 'code' => 'db_error'];
        }
        $up->bind_param('si', $inboundStatus, $waybillId);
        $up->execute();
        $up->close();
        $this->writeAuditLog(
            $conn,
            'dispatch',
            'dispatch.package_ops.arrival_scan.inbound',
            'waybill',
            $waybillId,
            ['tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo), 'status' => $inboundStatus]
        );

        $pendingCount = 0;
        $count = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM dispatch_waybills
            WHERE delivery_customer_id = ?
              AND COALESCE(order_status, '') <> '已派送'
        ");
        if ($count) {
            $count->bind_param('i', $deliveryId);
            $count->execute();
            $pendingCount = (int)(($count->get_result()->fetch_assoc())['c'] ?? 0);
            $count->close();
        }

        $label = [
            'tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo),
            'customer_code' => $matchedCode,
            'wechat_id' => trim((string)($row['wechat_id'] ?? '')),
            'route_primary' => trim((string)($row['route_primary'] ?? '')),
            'route_secondary' => trim((string)($row['route_secondary'] ?? '')),
            'community_name_th' => trim((string)($row['community_name_th'] ?? '')),
            'addr_th_full' => trim((string)($row['addr_th_full'] ?? '')),
            'pending_count' => $pendingCount,
        ];

        $pdfMode = 'fallback';
        $pdfDebug = '';
        $pdfUrl = $this->issueArrivalLabelPdfUrl($label, $pdfMode, $pdfDebug);
        return [
            'ok' => true,
            'error' => '',
            'code' => 'printed',
            'tracking_no' => (string)($row['original_tracking_no'] ?? $trackingNo),
            'status' => $inboundStatus,
            'label' => $label,
            'pdf_url' => $pdfUrl,
            'pdf_mode' => $pdfMode,
            'pdf_debug' => $pdfDebug,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   customer_code?: string,
     *   total?: int,
     *   rows?: array<int, array<string, mixed>>
     * }
     */
    private function querySelfPickupCandidates(mysqli $conn, string $rawCustomerCode): array
    {
        $customerCode = trim($rawCustomerCode);
        if ($customerCode === '') {
            return ['ok' => false, 'error' => '请输入客户编码', 'code' => 'empty_customer_code'];
        }
        $excluded = ['已派送', '待入库', '部分入库', '未入库', '问题件', '已出库', '已自取', '已转发', '待转发'];
        $stmt = $conn->prepare("
            SELECT
                w.id,
                w.original_tracking_no,
                w.order_status,
                w.scanned_at,
                COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)) AS resolved_customer_code
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE (TRIM(COALESCE(dc.customer_code, '')) = ? OR TRIM(COALESCE(w.delivery_customer_code, '')) = ?)
              AND COALESCE(w.order_status, '') NOT IN (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ORDER BY w.id DESC
            LIMIT 1000
        ");
        if (!$stmt) {
            return ['ok' => false, 'error' => '查询失败，请稍后再试', 'code' => 'db_error'];
        }
        $stmt->bind_param(
            'sssssssssss',
            $customerCode,
            $customerCode,
            $excluded[0],
            $excluded[1],
            $excluded[2],
            $excluded[3],
            $excluded[4],
            $excluded[5],
            $excluded[6],
            $excluded[7],
            $excluded[8]
        );
        $stmt->execute();
        $q = $stmt->get_result();
        $rows = [];
        while ($q && ($row = $q->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'original_tracking_no' => (string)($row['original_tracking_no'] ?? ''),
                'order_status' => (string)($row['order_status'] ?? ''),
                'scanned_at' => trim((string)($row['scanned_at'] ?? '')),
                'customer_code' => (string)($row['resolved_customer_code'] ?? $customerCode),
            ];
        }
        $stmt->close();
        return [
            'ok' => true,
            'error' => '',
            'code' => 'ok',
            'customer_code' => $customerCode,
            'total' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param list<mixed> $rawIds
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   customer_code?: string,
     *   picked_count?: int,
     *   skipped_count?: int
     * }
     */
    private function submitSelfPickup(mysqli $conn, string $rawCustomerCode, array $rawIds): array
    {
        $customerCode = trim($rawCustomerCode);
        if ($customerCode === '') {
            return ['ok' => false, 'error' => '请输入客户编码', 'code' => 'empty_customer_code'];
        }
        $ids = [];
        foreach ($rawIds as $v) {
            $id = (int)$v;
            if ($id > 0) $ids[$id] = $id;
        }
        $ids = array_values($ids);
        if (!$ids) {
            return ['ok' => false, 'error' => '请先勾选订单', 'code' => 'empty_selection'];
        }
        $excluded = ['已派送', '待入库', '部分入库', '未入库', '问题件', '已出库', '已自取', '已转发', '待转发'];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $types = 'ss' . str_repeat('i', count($ids)) . 'sssssssss';
        $params = array_merge([$customerCode, $customerCode], $ids, $excluded);
        $sql = "
            SELECT w.id
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE (TRIM(COALESCE(dc.customer_code, '')) = ? OR TRIM(COALESCE(w.delivery_customer_code, '')) = ?)
              AND w.id IN ($in)
              AND COALESCE(w.order_status, '') NOT IN (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => '提交失败，请稍后再试', 'code' => 'db_error'];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $q = $stmt->get_result();
        $validIds = [];
        while ($q && ($row = $q->fetch_assoc())) {
            $wid = (int)($row['id'] ?? 0);
            if ($wid > 0) $validIds[] = $wid;
        }
        $stmt->close();
        if (!$validIds) {
            return ['ok' => false, 'error' => '无可更新订单（可能状态已变化）', 'code' => 'no_valid_waybills'];
        }
        $in2 = implode(',', array_fill(0, count($validIds), '?'));
        $types2 = 's' . str_repeat('i', count($validIds));
        $params2 = array_merge(['已自取'], $validIds);
        $up = $conn->prepare("UPDATE dispatch_waybills SET order_status = ?, delivered_at = NOW() WHERE id IN ($in2)");
        if (!$up) {
            return ['ok' => false, 'error' => '更新失败，请稍后再试', 'code' => 'db_error'];
        }
        $up->bind_param($types2, ...$params2);
        $up->execute();
        $pickedCount = (int)$up->affected_rows;
        $up->close();
        $this->writeAuditLog(
            $conn,
            'dispatch',
            'dispatch.package_ops.self_pickup.submit',
            'waybill_batch',
            null,
            [
                'customer_code' => $customerCode,
                'selected_ids' => $ids,
                'updated_ids' => $validIds,
                'picked_count' => $pickedCount,
                'skipped_count' => max(0, count($ids) - count($validIds)),
            ]
        );
        return [
            'ok' => true,
            'error' => '',
            'code' => 'ok',
            'customer_code' => $customerCode,
            'picked_count' => $pickedCount,
            'skipped_count' => max(0, count($ids) - count($validIds)),
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   customer_code?: string,
     *   total?: int,
     *   rows?: array<int, array<string, mixed>>
     * }
     */
    private function queryForwardPushCandidates(mysqli $conn, string $rawCustomerCode): array
    {
        $customerCode = trim($rawCustomerCode);
        if ($customerCode === '') {
            return ['ok' => false, 'error' => '请输入客户编码', 'code' => 'empty_customer_code'];
        }
        $stmt = $conn->prepare("
            SELECT
                w.id,
                w.original_tracking_no,
                w.order_status,
                w.scanned_at,
                COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)) AS resolved_customer_code
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE (TRIM(COALESCE(dc.customer_code, '')) = ? OR TRIM(COALESCE(w.delivery_customer_code, '')) = ?)
              AND COALESCE(w.order_status, '') = '已入库'
            ORDER BY w.id DESC
            LIMIT 1000
        ");
        if (!$stmt) {
            return ['ok' => false, 'error' => '查询失败，请稍后再试', 'code' => 'db_error'];
        }
        $stmt->bind_param('ss', $customerCode, $customerCode);
        $stmt->execute();
        $q = $stmt->get_result();
        $rows = [];
        while ($q && ($row = $q->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'original_tracking_no' => (string)($row['original_tracking_no'] ?? ''),
                'order_status' => (string)($row['order_status'] ?? ''),
                'scanned_at' => trim((string)($row['scanned_at'] ?? '')),
                'customer_code' => (string)($row['resolved_customer_code'] ?? $customerCode),
            ];
        }
        $stmt->close();
        return [
            'ok' => true,
            'error' => '',
            'code' => 'ok',
            'customer_code' => $customerCode,
            'total' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param list<mixed> $rawIds
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   customer_code?: string,
     *   pushed_count?: int,
     *   skipped_count?: int
     * }
     */
    private function submitForwardPush(mysqli $conn, string $rawCustomerCode, array $rawIds): array
    {
        $customerCode = trim($rawCustomerCode);
        if ($customerCode === '') {
            return ['ok' => false, 'error' => '请输入客户编码', 'code' => 'empty_customer_code'];
        }
        $ids = [];
        foreach ($rawIds as $v) {
            $id = (int)$v;
            if ($id > 0) $ids[$id] = $id;
        }
        $ids = array_values($ids);
        if (!$ids) {
            return ['ok' => false, 'error' => '请先勾选订单', 'code' => 'empty_selection'];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $types = 'ss' . str_repeat('i', count($ids)) . 's';
        $params = array_merge([$customerCode, $customerCode], $ids, ['已入库']);
        $sql = "
            SELECT w.id
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE (TRIM(COALESCE(dc.customer_code, '')) = ? OR TRIM(COALESCE(w.delivery_customer_code, '')) = ?)
              AND w.id IN ($in)
              AND COALESCE(w.order_status, '') = ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => '提交失败，请稍后再试', 'code' => 'db_error'];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $q = $stmt->get_result();
        $validIds = [];
        while ($q && ($row = $q->fetch_assoc())) {
            $wid = (int)($row['id'] ?? 0);
            if ($wid > 0) $validIds[] = $wid;
        }
        $stmt->close();
        if (!$validIds) {
            return ['ok' => false, 'error' => '无可推送订单（可能状态已变化）', 'code' => 'no_valid_waybills'];
        }

        $in2 = implode(',', array_fill(0, count($validIds), '?'));
        $types2 = 's' . str_repeat('i', count($validIds));
        $params2 = array_merge(['待转发'], $validIds);
        $up = $conn->prepare("UPDATE dispatch_waybills SET order_status = ?, delivered_at = NOW() WHERE id IN ($in2)");
        if (!$up) {
            return ['ok' => false, 'error' => '更新失败，请稍后再试', 'code' => 'db_error'];
        }
        $up->bind_param($types2, ...$params2);
        $up->execute();
        $pushedCount = (int)$up->affected_rows;
        $up->close();

        $this->writeAuditLog(
            $conn,
            'dispatch',
            'dispatch.package_ops.forward_push.submit',
            'waybill_batch',
            null,
            [
                'customer_code' => $customerCode,
                'selected_ids' => $ids,
                'updated_ids' => $validIds,
                'pushed_count' => $pushedCount,
                'skipped_count' => max(0, count($ids) - count($validIds)),
            ]
        );
        return [
            'ok' => true,
            'error' => '',
            'code' => 'ok',
            'customer_code' => $customerCode,
            'pushed_count' => $pushedCount,
            'skipped_count' => max(0, count($ids) - count($validIds)),
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   keyword?: string,
     *   total?: int,
     *   rows?: array<int, array<string, mixed>>
     * }
     */
    private function queryStatusFixCandidates(mysqli $conn, string $rawTrackingNo, string $rawCustomerCode): array
    {
        $trackingNo = trim($rawTrackingNo);
        $customerCode = trim($rawCustomerCode);
        if ($trackingNo === '' && $customerCode === '') {
            return ['ok' => false, 'error' => '请至少输入原始单号或客户代码之一', 'code' => 'empty_query'];
        }
        $where = [];
        $types = '';
        $params = [];
        if ($trackingNo !== '') {
            $where[] = 'w.original_tracking_no LIKE ?';
            $types .= 's';
            $params[] = '%' . $trackingNo . '%';
        }
        if ($customerCode !== '') {
            $where[] = "(TRIM(COALESCE(dc.customer_code, '')) = ? OR TRIM(COALESCE(w.delivery_customer_code, '')) = ?)";
            $types .= 'ss';
            $params[] = $customerCode;
            $params[] = $customerCode;
        }
        $whereSql = implode(' AND ', $where);
        $sql = "
            SELECT
                w.id,
                w.original_tracking_no,
                w.order_status,
                w.scanned_at,
                COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)) AS resolved_customer_code
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE {$whereSql}
            ORDER BY w.id DESC
            LIMIT 1000
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => '查询失败，请稍后再试', 'code' => 'db_error'];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $q = $stmt->get_result();
        $rows = [];
        while ($q && ($row = $q->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'original_tracking_no' => (string)($row['original_tracking_no'] ?? ''),
                'order_status' => (string)($row['order_status'] ?? ''),
                'scanned_at' => trim((string)($row['scanned_at'] ?? '')),
                'customer_code' => (string)($row['resolved_customer_code'] ?? ''),
            ];
        }
        $stmt->close();
        return [
            'ok' => true,
            'error' => '',
            'code' => 'ok',
            'tracking_no' => $trackingNo,
            'customer_code' => $customerCode,
            'total' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $statusUpdates
     * @return array{
     *   ok: bool,
     *   error: string,
     *   code: string,
     *   updated_count?: int
     * }
     */
    private function submitStatusFix(mysqli $conn, array $statusUpdates): array
    {
        if (!$statusUpdates) {
            return ['ok' => false, 'error' => '请先选择状态后再送出', 'code' => 'empty_selection'];
        }
        $allowedStatuses = $this->orderStatusCatalog();
        $allowedSet = [];
        foreach ($allowedStatuses as $st) {
            $st = trim((string)$st);
            if ($st !== '') $allowedSet[$st] = true;
        }
        $pairs = [];
        foreach ($statusUpdates as $k => $v) {
            $id = (int)$k;
            $status = trim((string)$v);
            if ($id <= 0 || $status === '') continue;
            if ($allowedSet && !isset($allowedSet[$status])) continue;
            $pairs[] = ['id' => $id, 'status' => $status];
        }
        if (!$pairs) {
            return ['ok' => false, 'error' => '未找到有效的状态修改项', 'code' => 'invalid_selection'];
        }
        $up = $conn->prepare('UPDATE dispatch_waybills SET order_status = ?, delivered_at = NOW() WHERE id = ? LIMIT 1');
        if (!$up) {
            return ['ok' => false, 'error' => '更新失败，请稍后再试', 'code' => 'db_error'];
        }
        $updated = 0;
        foreach ($pairs as $p) {
            $sid = (int)$p['id'];
            $sst = (string)$p['status'];
            $up->bind_param('si', $sst, $sid);
            $up->execute();
            if ((int)$up->affected_rows > 0) $updated++;
        }
        $up->close();
        $this->writeAuditLog(
            $conn,
            'dispatch',
            'dispatch.package_ops.status_fix.submit',
            'waybill_batch',
            null,
            ['updates' => $pairs, 'updated_count' => $updated]
        );
        return ['ok' => true, 'error' => '', 'code' => 'ok', 'updated_count' => $updated];
    }

    /**
     * @return array{ok: bool, error: string}
     */
    private function insertDeliveryCustomerFull(
        mysqli $conn,
        int $ccId,
        string $customerCode,
        string $wechat,
        string $line,
        string $recipientName,
        string $phone,
        string $addrHouseNo,
        string $addrRoadSoi,
        string $addrMooVillage,
        string $addrTambon,
        string $addrAmphoe,
        string $addrProvince,
        string $addrZipcode,
        string $geoStatus,
        ?float $lat,
        ?float $lng,
        string $rp,
        string $rs,
        string $en,
        string $th,
        string $customerState = '正常',
        string $customerRequirement = ''
    ): array {
        $hasBizState = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state');
        $hasPhoneCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'phone');
        $hasCreatedMarkedCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'created_marked_at');
        $hasRequirementCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_requirements');
        $hasRecipientCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'recipient_name');
        $hasCoords = $lat !== null && $lng !== null;
        if (!$hasBizState) {
            if ($hasCoords) {
                $stmt = $conn->prepare('
                    INSERT INTO dispatch_delivery_customers (
                        consigning_client_id, customer_code, wechat_id, line_id,
                        latitude, longitude,
                        route_primary, route_secondary, community_name_en, community_name_th, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ');
                if ($stmt) {
                    $stmt->bind_param(
                        'isssddssss',
                        $ccId,
                        $customerCode,
                        $wechat,
                        $line,
                        $lat,
                        $lng,
                        $rp,
                        $rs,
                        $en,
                        $th
                    );
                }
            } else {
                $stmt = $conn->prepare('
                    INSERT INTO dispatch_delivery_customers (
                        consigning_client_id, customer_code, wechat_id, line_id,
                        latitude, longitude,
                        route_primary, route_secondary, community_name_en, community_name_th, status
                    ) VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, 1)
                ');
                if ($stmt) {
                    $stmt->bind_param(
                        'isssssss',
                        $ccId,
                        $customerCode,
                        $wechat,
                        $line,
                        $rp,
                        $rs,
                        $en,
                        $th
                    );
                }
            }
            if (!$stmt) {
                return ['ok' => false, 'error' => '保存失败'];
            }
            try {
                $stmt->execute();
                $newId = (int)$conn->insert_id;
                if ($newId > 0 && $hasPhoneCol) {
                    $upPhone = $conn->prepare('UPDATE dispatch_delivery_customers SET phone = ? WHERE id = ? LIMIT 1');
                    if ($upPhone) {
                        $upPhone->bind_param('si', $phone, $newId);
                        $upPhone->execute();
                        $upPhone->close();
                    }
                }
                if ($newId > 0 && $hasRecipientCol) {
                    $upRecipient = $conn->prepare('UPDATE dispatch_delivery_customers SET recipient_name = ? WHERE id = ? LIMIT 1');
                    if ($upRecipient) {
                        $upRecipient->bind_param('si', $recipientName, $newId);
                        $upRecipient->execute();
                        $upRecipient->close();
                    }
                }
                if ($newId > 0 && $hasCreatedMarkedCol) {
                    $upMark = $conn->prepare('UPDATE dispatch_delivery_customers SET created_marked_at = COALESCE(created_marked_at, NOW()) WHERE id = ? LIMIT 1');
                    if ($upMark) {
                        $upMark->bind_param('i', $newId);
                        $upMark->execute();
                        $upMark->close();
                    }
                }
                if ($newId > 0 && $hasRequirementCol) {
                    $upReq = $conn->prepare('UPDATE dispatch_delivery_customers SET customer_requirements = ? WHERE id = ? LIMIT 1');
                    if ($upReq) {
                        $upReq->bind_param('si', $customerRequirement, $newId);
                        $upReq->execute();
                        $upReq->close();
                    }
                }
                if ($newId > 0 && $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_house_no')) {
                    $comp = $this->deliveryComposedFullAddresses($addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode);
                    $fullTh = $comp['th'];
                    $fullEn = $comp['en'];
                    $geoStatusNorm = $this->normalizeDeliveryGeoStatus($rp, $lat, $lng, $geoStatus);
                    $upAddr = $conn->prepare('UPDATE dispatch_delivery_customers SET addr_house_no = ?, addr_road_soi = ?, addr_moo_village = ?, addr_tambon = ?, addr_amphoe = ?, addr_province = ?, addr_zipcode = ?, addr_th_full = ?, addr_en_full = ?, geo_status = ? WHERE id = ? LIMIT 1');
                    if ($upAddr) {
                        $upAddr->bind_param('ssssssssssi', $addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode, $fullTh, $fullEn, $geoStatusNorm, $newId);
                        $upAddr->execute();
                        $upAddr->close();
                    }
                }
                $stmt->close();
                $this->notifyForwardCustomerSyncFromDelivery($conn, $customerCode);
                return ['ok' => true, 'error' => ''];
            } catch (mysqli_sql_exception $e) {
                $errno = (int)$e->getCode();
                $stmt->close();
                if ($errno === 1062) {
                    return ['ok' => false, 'error' => 'duplicate'];
                }
                return ['ok' => false, 'error' => '保存失败'];
            }
        }

        $st = $this->normalizeDeliveryCustomerState($customerState);
        $routesCombined = $this->buildRoutesCombined($rp, $rs);
        if ($hasCoords) {
            $stmt = $conn->prepare('
                INSERT INTO dispatch_delivery_customers (
                    consigning_client_id, customer_code, wechat_id, line_id,
                    latitude, longitude,
                    route_primary, route_secondary, routes_combined,
                    community_name_en, community_name_th, customer_state, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ');
            if ($stmt) {
                $stmt->bind_param(
                    'isssddssssss',
                    $ccId,
                    $customerCode,
                    $wechat,
                    $line,
                    $lat,
                    $lng,
                    $rp,
                    $rs,
                    $routesCombined,
                    $en,
                    $th,
                    $st
                );
            }
        } else {
            $stmt = $conn->prepare('
                INSERT INTO dispatch_delivery_customers (
                    consigning_client_id, customer_code, wechat_id, line_id,
                    latitude, longitude,
                    route_primary, route_secondary, routes_combined,
                    community_name_en, community_name_th, customer_state, status
                ) VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, 1)
            ');
            if ($stmt) {
                $stmt->bind_param(
                    'isssssssss',
                    $ccId,
                    $customerCode,
                    $wechat,
                    $line,
                    $rp,
                    $rs,
                    $routesCombined,
                    $en,
                    $th,
                    $st
                );
            }
        }
        if (!$stmt) {
            return ['ok' => false, 'error' => '保存失败'];
        }
        try {
            $stmt->execute();
            $newId = (int)$conn->insert_id;
            if ($newId > 0 && $hasPhoneCol) {
                $upPhone = $conn->prepare('UPDATE dispatch_delivery_customers SET phone = ? WHERE id = ? LIMIT 1');
                if ($upPhone) {
                    $upPhone->bind_param('si', $phone, $newId);
                    $upPhone->execute();
                    $upPhone->close();
                }
            }
            if ($newId > 0 && $hasRecipientCol) {
                $upRecipient = $conn->prepare('UPDATE dispatch_delivery_customers SET recipient_name = ? WHERE id = ? LIMIT 1');
                if ($upRecipient) {
                    $upRecipient->bind_param('si', $recipientName, $newId);
                    $upRecipient->execute();
                    $upRecipient->close();
                }
            }
            if ($newId > 0 && $hasCreatedMarkedCol) {
                $upMark = $conn->prepare('UPDATE dispatch_delivery_customers SET created_marked_at = COALESCE(created_marked_at, NOW()) WHERE id = ? LIMIT 1');
                if ($upMark) {
                    $upMark->bind_param('i', $newId);
                    $upMark->execute();
                    $upMark->close();
                }
            }
            if ($newId > 0 && $hasRequirementCol) {
                $upReq = $conn->prepare('UPDATE dispatch_delivery_customers SET customer_requirements = ? WHERE id = ? LIMIT 1');
                if ($upReq) {
                    $upReq->bind_param('si', $customerRequirement, $newId);
                    $upReq->execute();
                    $upReq->close();
                }
            }
            if ($newId > 0 && $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_house_no')) {
                $comp = $this->deliveryComposedFullAddresses($addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode);
                $fullTh = $comp['th'];
                $fullEn = $comp['en'];
                $geoStatusNorm = $this->normalizeDeliveryGeoStatus($rp, $lat, $lng, $geoStatus);
                $upAddr = $conn->prepare('UPDATE dispatch_delivery_customers SET addr_house_no = ?, addr_road_soi = ?, addr_moo_village = ?, addr_tambon = ?, addr_amphoe = ?, addr_province = ?, addr_zipcode = ?, addr_th_full = ?, addr_en_full = ?, geo_status = ? WHERE id = ? LIMIT 1');
                if ($upAddr) {
                    $upAddr->bind_param('ssssssssssi', $addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode, $fullTh, $fullEn, $geoStatusNorm, $newId);
                    $upAddr->execute();
                    $upAddr->close();
                }
            }
            $stmt->close();
            $this->notifyForwardCustomerSyncFromDelivery($conn, $customerCode);
            return ['ok' => true, 'error' => ''];
        } catch (mysqli_sql_exception $e) {
            $errno = (int)$e->getCode();
            $stmt->close();
            if ($errno === 1062) {
                return ['ok' => false, 'error' => 'duplicate'];
            }
            return ['ok' => false, 'error' => '保存失败'];
        }
    }

    private function findDeliveryCustomerIdByCode(mysqli $conn, int $ccId, string $customerCode): int
    {
        if ($ccId <= 0 || $customerCode === '') {
            return 0;
        }
        $stmt = $conn->prepare('SELECT id FROM dispatch_delivery_customers WHERE consigning_client_id = ? AND customer_code = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('is', $ccId, $customerCode);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return 0;
        }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }

    /** 若转发库已有同客户编码，则用派送端当前值更新该行（不新增转发行）。 */
    private function notifyForwardCustomerSyncFromDelivery(mysqli $conn, string $customerCode): void
    {
        $code = trim($customerCode);
        if ($code === '') {
            return;
        }
        require_once __DIR__ . '/ForwardingController.php';
        (new ForwardingController())->syncForwardCustomerFromDeliveryIfExists($conn, $code);
    }

    /**
     * 导入用：按主键与委托客户覆盖更新一行（与编辑表单写入字段一致）。
     *
     * @return array{ok: bool, error: string}
     */
    private function updateDeliveryCustomerRowFromImport(
        mysqli $conn,
        int $deliveryId,
        int $ccId,
        string $customerCode,
        string $wechat,
        string $line,
        string $recipientName,
        string $phone,
        string $addrHouseNo,
        string $addrRoadSoi,
        string $addrMooVillage,
        string $addrTambon,
        string $addrAmphoe,
        string $addrProvince,
        string $addrZipcode,
        string $geoStatus,
        ?float $lat,
        ?float $lng,
        string $rp,
        string $rs,
        string $en,
        string $th,
        string $custStateNorm,
        string $customerRequirement = ''
    ): array {
        $hasCustState = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state');
        $hasPhoneCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'phone');
        $hasAddrGeoUpdatedCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'address_geo_updated_at');
        $hasRequirementCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_requirements');
        $hasRecipientCol = $this->columnExists($conn, 'dispatch_delivery_customers', 'recipient_name');
        $oldH = '';
        $oldR = '';
        $oldM = '';
        $oldT = '';
        $oldA = '';
        $oldP = '';
        $oldZ = '';
        $oldLat = null;
        $oldLng = null;
        $oldRoutePrimary = '';
        $oldCustomerCodeForForward = '';
        $oldStmt = $conn->prepare('
            SELECT customer_code, route_primary,
                addr_house_no, addr_road_soi, addr_moo_village, addr_tambon, addr_amphoe, addr_province, addr_zipcode,
                latitude, longitude
            FROM dispatch_delivery_customers WHERE id = ? AND consigning_client_id = ? LIMIT 1
        ');
        if ($oldStmt) {
            $oldStmt->bind_param('ii', $deliveryId, $ccId);
            try {
                $oldStmt->execute();
                $oldRow = $oldStmt->get_result()->fetch_assoc();
                if (is_array($oldRow)) {
                    $oldCustomerCodeForForward = trim((string)($oldRow['customer_code'] ?? ''));
                    $oldRoutePrimary = trim((string)($oldRow['route_primary'] ?? ''));
                    $oldH = trim((string)($oldRow['addr_house_no'] ?? ''));
                    $oldR = trim((string)($oldRow['addr_road_soi'] ?? ''));
                    $oldM = trim((string)($oldRow['addr_moo_village'] ?? ''));
                    $oldT = trim((string)($oldRow['addr_tambon'] ?? ''));
                    $oldA = trim((string)($oldRow['addr_amphoe'] ?? ''));
                    $oldP = trim((string)($oldRow['addr_province'] ?? ''));
                    $oldZ = trim((string)($oldRow['addr_zipcode'] ?? ''));
                    $oldLat = ($oldRow['latitude'] ?? null) !== null && (string)$oldRow['latitude'] !== '' ? (float)$oldRow['latitude'] : null;
                    $oldLng = ($oldRow['longitude'] ?? null) !== null && (string)$oldRow['longitude'] !== '' ? (float)$oldRow['longitude'] : null;
                }
            } catch (mysqli_sql_exception $e) {
                // ignore
            }
            $oldStmt->close();
        }
        $routesCombined = $this->buildRoutesCombined($rp, $rs);
        $hasCoords = $lat !== null && $lng !== null;
        $u = null;
        if ($hasCustState) {
            if ($hasCoords) {
                $u = $conn->prepare('
                    UPDATE dispatch_delivery_customers SET
                        customer_code = ?, wechat_id = ?, line_id = ?,
                        latitude = ?, longitude = ?,
                        route_primary = ?, route_secondary = ?, routes_combined = ?,
                        community_name_en = ?, community_name_th = ?, customer_state = ?
                    WHERE id = ? AND consigning_client_id = ?
                ');
                if ($u) {
                    $u->bind_param(
                        'sssddssssssii',
                        $customerCode,
                        $wechat,
                        $line,
                        $lat,
                        $lng,
                        $rp,
                        $rs,
                        $routesCombined,
                        $en,
                        $th,
                        $custStateNorm,
                        $deliveryId,
                        $ccId
                    );
                }
            } else {
                $u = $conn->prepare('
                    UPDATE dispatch_delivery_customers SET
                        customer_code = ?, wechat_id = ?, line_id = ?,
                        latitude = NULL, longitude = NULL,
                        route_primary = ?, route_secondary = ?, routes_combined = ?,
                        community_name_en = ?, community_name_th = ?, customer_state = ?
                    WHERE id = ? AND consigning_client_id = ?
                ');
                if ($u) {
                    $u->bind_param(
                        'sssssssssii',
                        $customerCode,
                        $wechat,
                        $line,
                        $rp,
                        $rs,
                        $routesCombined,
                        $en,
                        $th,
                        $custStateNorm,
                        $deliveryId,
                        $ccId
                    );
                }
            }
        } elseif ($hasCoords) {
            $u = $conn->prepare('
                UPDATE dispatch_delivery_customers SET
                    customer_code = ?, wechat_id = ?, line_id = ?,
                    latitude = ?, longitude = ?,
                    route_primary = ?, route_secondary = ?,
                    community_name_en = ?, community_name_th = ?
                WHERE id = ? AND consigning_client_id = ?
            ');
            if ($u) {
                $u->bind_param(
                    'sssddssssii',
                    $customerCode,
                    $wechat,
                    $line,
                    $lat,
                    $lng,
                    $rp,
                    $rs,
                    $en,
                    $th,
                    $deliveryId,
                    $ccId
                );
            }
        } else {
            $u = $conn->prepare('
                UPDATE dispatch_delivery_customers SET
                    customer_code = ?, wechat_id = ?, line_id = ?,
                    latitude = NULL, longitude = NULL,
                    route_primary = ?, route_secondary = ?,
                    community_name_en = ?, community_name_th = ?
                WHERE id = ? AND consigning_client_id = ?
            ');
            if ($u) {
                $u->bind_param(
                    'sssssssii',
                    $customerCode,
                    $wechat,
                    $line,
                    $rp,
                    $rs,
                    $en,
                    $th,
                    $deliveryId,
                    $ccId
                );
            }
        }
        if (!$u) {
            return ['ok' => false, 'error' => '保存失败'];
        }
        try {
            $u->execute();
            if ($hasPhoneCol) {
                $upPhone = $conn->prepare('UPDATE dispatch_delivery_customers SET phone = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                if ($upPhone) {
                    $upPhone->bind_param('sii', $phone, $deliveryId, $ccId);
                    $upPhone->execute();
                    $upPhone->close();
                }
            }
            if ($hasRecipientCol) {
                $upRecipient = $conn->prepare('UPDATE dispatch_delivery_customers SET recipient_name = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                if ($upRecipient) {
                    $upRecipient->bind_param('sii', $recipientName, $deliveryId, $ccId);
                    $upRecipient->execute();
                    $upRecipient->close();
                }
            }
            if ($hasRequirementCol) {
                $upReq = $conn->prepare('UPDATE dispatch_delivery_customers SET customer_requirements = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                if ($upReq) {
                    $upReq->bind_param('sii', $customerRequirement, $deliveryId, $ccId);
                    $upReq->execute();
                    $upReq->close();
                }
            }
            if ($this->columnExists($conn, 'dispatch_delivery_customers', 'addr_house_no')) {
                $comp = $this->deliveryComposedFullAddresses($addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode);
                $fullTh = $comp['th'];
                $fullEn = $comp['en'];
                $geoStatusNorm = $this->normalizeDeliveryGeoStatus($rp, $lat, $lng, $geoStatus);
                $upAddr = $conn->prepare('UPDATE dispatch_delivery_customers SET addr_house_no = ?, addr_road_soi = ?, addr_moo_village = ?, addr_tambon = ?, addr_amphoe = ?, addr_province = ?, addr_zipcode = ?, addr_th_full = ?, addr_en_full = ?, geo_status = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                if ($upAddr) {
                    $upAddr->bind_param('ssssssssssii', $addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode, $fullTh, $fullEn, $geoStatusNorm, $deliveryId, $ccId);
                    $upAddr->execute();
                    $upAddr->close();
                }
            }
            $changedAddress = trim($addrHouseNo) !== $oldH || trim($addrRoadSoi) !== $oldR || trim($addrMooVillage) !== $oldM
                || trim($addrTambon) !== $oldT || trim($addrAmphoe) !== $oldA || trim($addrProvince) !== $oldP || trim($addrZipcode) !== $oldZ
                || $lat !== $oldLat || $lng !== $oldLng;
            if ($hasAddrGeoUpdatedCol && $changedAddress) {
                $upMark = $conn->prepare('UPDATE dispatch_delivery_customers SET address_geo_updated_at = NOW() WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                if ($upMark) {
                    $upMark->bind_param('ii', $deliveryId, $ccId);
                    $upMark->execute();
                    $upMark->close();
                }
            }
            $this->removeForwardCustomerAfterDeliveryRouteLeavesOt($conn, $oldCustomerCodeForForward, $oldRoutePrimary, $rp);
            $u->close();
            $this->notifyForwardCustomerSyncFromDelivery($conn, $customerCode);
            return ['ok' => true, 'error' => ''];
        } catch (mysqli_sql_exception $e) {
            $u->close();
            return ['ok' => false, 'error' => '保存失败'];
        }
    }

    /**
     * 导入用：同委托客户下客户编号已存在则整行覆盖，否则新增。
     *
     * @return array{ok: bool, error: string, action: string}
     */
    private function upsertDeliveryCustomerFromImport(
        mysqli $conn,
        int $ccId,
        string $customerCode,
        string $wechat,
        string $line,
        string $recipientName,
        string $phone,
        string $addrHouseNo,
        string $addrRoadSoi,
        string $addrMooVillage,
        string $addrTambon,
        string $addrAmphoe,
        string $addrProvince,
        string $addrZipcode,
        string $geoStatus,
        ?float $lat,
        ?float $lng,
        string $rp,
        string $rs,
        string $en,
        string $th,
        string $custStateRaw,
        string $customerRequirement = ''
    ): array {
        $custNorm = $this->normalizeDeliveryCustomerState($custStateRaw);
        $existingId = $this->findDeliveryCustomerIdByCode($conn, $ccId, $customerCode);
        if ($existingId > 0) {
            $up = $this->updateDeliveryCustomerRowFromImport(
                $conn,
                $existingId,
                $ccId,
                $customerCode,
                $wechat,
                $line,
                $recipientName,
                $phone,
                $addrHouseNo,
                $addrRoadSoi,
                $addrMooVillage,
                $addrTambon,
                $addrAmphoe,
                $addrProvince,
                $addrZipcode,
                $geoStatus,
                $lat,
                $lng,
                $rp,
                $rs,
                $en,
                $th,
                $custNorm,
                $customerRequirement
            );
            if ($up['ok']) {
                return ['ok' => true, 'error' => '', 'action' => 'update'];
            }
            return ['ok' => false, 'error' => $up['error'] !== '' ? $up['error'] : '覆盖更新失败', 'action' => ''];
        }
        $ins = $this->insertDeliveryCustomerFull(
            $conn,
            $ccId,
            $customerCode,
            $wechat,
            $line,
            $recipientName,
            $phone,
            $addrHouseNo,
            $addrRoadSoi,
            $addrMooVillage,
            $addrTambon,
            $addrAmphoe,
            $addrProvince,
            $addrZipcode,
            $geoStatus,
            $lat,
            $lng,
            $rp,
            $rs,
            $en,
            $th,
            $custStateRaw,
            $customerRequirement
        );
        if ($ins['ok']) {
            return ['ok' => true, 'error' => '', 'action' => 'insert'];
        }
        if ($ins['error'] === 'duplicate') {
            $id2 = $this->findDeliveryCustomerIdByCode($conn, $ccId, $customerCode);
            if ($id2 > 0) {
                $up2 = $this->updateDeliveryCustomerRowFromImport(
                    $conn,
                    $id2,
                    $ccId,
                    $customerCode,
                    $wechat,
                    $line,
                    $recipientName,
                    $phone,
                    $addrHouseNo,
                    $addrRoadSoi,
                    $addrMooVillage,
                    $addrTambon,
                    $addrAmphoe,
                    $addrProvince,
                    $addrZipcode,
                    $geoStatus,
                    $lat,
                    $lng,
                    $rp,
                    $rs,
                    $en,
                    $th,
                    $custNorm,
                    $customerRequirement
                );
                if ($up2['ok']) {
                    return ['ok' => true, 'error' => '', 'action' => 'update'];
                }
                return ['ok' => false, 'error' => $up2['error'] !== '' ? $up2['error'] : '覆盖更新失败', 'action' => ''];
            }
        }
        return ['ok' => false, 'error' => $ins['error'] !== '' ? $ins['error'] : '保存失败', 'action' => ''];
    }

    public function index(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.waybills.view', 'dispatch.waybills.import', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限查看订单列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'dispatch_waybills')) {
            $schemaReady = false;
            $ordersSchemaV2 = false;
            $title = '派送业务';
            $contentView = __DIR__ . '/../Views/dispatch/hub.php';
            require __DIR__ . '/../Views/layouts/main.php';
            return;
        }
        $this->ensureDispatchSchema($conn);
        $ordersSchemaV2 = $this->columnExists($conn, 'dispatch_waybills', 'order_status');
        $migrationHint = $ordersSchemaV2
            ? ''
            : '尚未执行数据库脚本 022：当前为「基础订单」模式（无订单状态/导入日期/扫描派送时间等栏位）。请执行 database/migrations/022_dispatch_waybill_order_fields.sql 后刷新，即可使用完整订单字段与派送照片表。';

        $message = '';
        $error = '';
        $showOrderImportLink = $this->hasAnyPermission(['dispatch.waybills.import', 'dispatch.manage']);
        $canWaybillEdit = $this->hasAnyPermission(['dispatch.waybills.customer_code.edit', 'dispatch.waybills.edit', 'dispatch.manage']);
        $canWaybillDelete = $this->hasAnyPermission(['dispatch.waybills.edit', 'dispatch.manage']);
        $boundCcId = isset($_SESSION['auth_dispatch_consigning_client_id']) ? (int)$_SESSION['auth_dispatch_consigning_client_id'] : 0;
        $hideConsigningSelectors = $boundCcId > 0;

        $consigningOptions = $this->activeConsigningClients($conn);
        $requestedCc = (int)($_GET['consigning_client_id'] ?? 0);
        $dispatchBoundClientMissing = false;
        if ($boundCcId > 0) {
            $boundRow = $this->consigningClientRowById($conn, $boundCcId);
            $consigningOptions = $boundRow ? [$boundRow] : [];
            $dispatchBoundClientMissing = $boundRow === null;
            $filterResolved = ['id' => $boundCcId, 'must_select' => false, 'single' => true];
        } else {
            $filterResolved = $this->resolveConsigningFilterForOrders($consigningOptions, $requestedCc);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waybill_customer_code_update'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$this->hasAnyPermission(['dispatch.waybills.customer_code.edit', 'dispatch.manage'])) {
                echo json_encode(['ok' => false, 'error' => '无权限修改客户编码'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $waybillId = (int)($_POST['waybill_id'] ?? 0);
            $newCode = trim((string)($_POST['delivery_customer_code'] ?? ''));
            if ($waybillId <= 0) {
                echo json_encode(['ok' => false, 'error' => '参数无效'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $up = $this->updateWaybillDeliveryCustomerCode($conn, $waybillId, $newCode);
            if (!$up['ok']) {
                echo json_encode(['ok' => false, 'error' => $up['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok' => true, 'row' => $up['row']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waybill_delete'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canWaybillDelete) {
                echo json_encode(['ok' => false, 'error' => '无权限删除订单'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $waybillId = (int)($_POST['waybill_id'] ?? 0);
            if ($waybillId <= 0) {
                echo json_encode(['ok' => false, 'error' => '参数无效'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $chk = $conn->prepare('SELECT id, consigning_client_id FROM dispatch_waybills WHERE id = ? LIMIT 1');
            if (!$chk) {
                echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $chk->bind_param('i', $waybillId);
            $chk->execute();
            $wrow = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$wrow) {
                echo json_encode(['ok' => false, 'error' => '订单不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $wCc = (int)($wrow['consigning_client_id'] ?? 0);
            if ($hideConsigningSelectors && $wCc !== $boundCcId) {
                echo json_encode(['ok' => false, 'error' => '无权删除该订单'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $del = $conn->prepare('DELETE FROM dispatch_waybills WHERE id = ? LIMIT 1');
            if (!$del) {
                echo json_encode(['ok' => false, 'error' => '删除失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $del->bind_param('i', $waybillId);
            try {
                $okDel = $del->execute();
            } catch (mysqli_sql_exception $e) {
                $okDel = false;
            }
            $del->close();
            if (!$okDel) {
                echo json_encode(['ok' => false, 'error' => '删除失败（可能仍被其他业务引用）'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->writeAuditLog($conn, 'dispatch', 'dispatch.waybill.delete', 'waybill', $waybillId, []);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $qTrack = trim((string)($_GET['q_track'] ?? ''));
        $qCustomerCode = trim((string)($_GET['q_customer_code'] ?? ''));
        $qWechat = trim((string)($_GET['q_wechat'] ?? ''));
        $qInbound = trim((string)($_GET['q_inbound'] ?? ''));
        $qStatus = trim((string)($_GET['q_status'] ?? ''));
        $qScanDate = trim((string)($_GET['q_scan_date'] ?? ''));
        if ($qStatus !== '' && !in_array($qStatus, $this->orderStatusCatalog(), true)) {
            $qStatus = '';
        }

        $page = $this->resolvePage();
        $perPage = $this->resolvePerPage();
        $offset = ($page - 1) * $perPage;
        $rows = [];
        $total = 0;

        if (!$filterResolved['must_select'] || $filterResolved['id'] > 0) {
            $w = ['1=1'];
            $types = '';
            $params = [];
            if ($filterResolved['id'] > 0) {
                $w[] = 'w.consigning_client_id = ?';
                $types .= 'i';
                $params[] = $filterResolved['id'];
            }
            if ($qTrack !== '') {
                $w[] = 'w.original_tracking_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qTrack . '%';
            }
            if ($qCustomerCode !== '') {
                $w[] = '(w.delivery_customer_code LIKE ? OR dc.customer_code LIKE ?)';
                $types .= 'ss';
                $like = '%' . $qCustomerCode . '%';
                $params[] = $like;
                $params[] = $like;
            }
            if ($qWechat !== '') {
                $w[] = 'dc.wechat_id LIKE ?';
                $types .= 's';
                $params[] = '%' . $qWechat . '%';
            }
            if ($qInbound !== '') {
                $w[] = 'w.inbound_batch LIKE ?';
                $types .= 's';
                $params[] = '%' . $qInbound . '%';
            }
            if ($ordersSchemaV2 && $qStatus !== '') {
                $w[] = 'w.order_status = ?';
                $types .= 's';
                $params[] = $qStatus;
            }
            if ($ordersSchemaV2 && $qScanDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $qScanDate)) {
                $w[] = 'DATE(w.scanned_at) = ?';
                $types .= 's';
                $params[] = $qScanDate;
            }
            $whereSql = implode(' AND ', $w);
            if (isset($_GET['export']) && (string)$_GET['export'] === 'current') {
                $sqlExport = "SELECT
                        w.original_tracking_no, w.delivery_customer_code,
                        COALESCE(NULLIF(dc.wechat_id, ''), '') AS wechat_id,
                        COALESCE(NULLIF(dc.line_id, ''), '') AS line_id,
                        w.quantity, w.weight_kg, w.length_cm, w.width_cm, w.height_cm, w.volume_m3, w.inbound_batch,
                        COALESCE(DATE_FORMAT(w.scanned_at, '%Y-%m-%d %H:%i:%s'), '') AS scanned_at,
                        COALESCE(DATE_FORMAT(w.delivered_at, '%Y-%m-%d %H:%i:%s'), '') AS status_updated_at,
                        COALESCE(w.order_status, '') AS order_status,
                        cc.client_code AS consigning_client_code,
                        cc.client_name AS consigning_client_name
                    FROM dispatch_waybills w
                    INNER JOIN dispatch_consigning_clients cc ON cc.id = w.consigning_client_id
                    LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
                    WHERE {$whereSql}
                    ORDER BY w.id DESC";
                $stmtExp = $conn->prepare($sqlExport);
                $rowsExp = [];
                if ($stmtExp) {
                    if ($types !== '') {
                        $stmtExp->bind_param($types, ...$params);
                    }
                    $stmtExp->execute();
                    $resExp = $stmtExp->get_result();
                    while ($resExp && ($er = $resExp->fetch_assoc())) {
                        $rowsExp[] = $er;
                    }
                    $stmtExp->close();
                }
                $csv = "原始单号,客户编码,微信/Line,数量,重量,长,宽,高,入库批次,入库时间,最后状态更新时间,订单状态,委托客户\n";
                foreach ($rowsExp as $er) {
                    $wx = trim((string)($er['wechat_id'] ?? ''));
                    $ln = trim((string)($er['line_id'] ?? ''));
                    $wxLine = $wx === '' ? $ln : ($ln === '' ? $wx : ($wx . ' / ' . $ln));
                    $consigning = trim((string)($er['consigning_client_code'] ?? '') . ' ' . (string)($er['consigning_client_name'] ?? ''));
                    $vals = [
                        (string)($er['original_tracking_no'] ?? ''),
                        (string)($er['delivery_customer_code'] ?? ''),
                        $wxLine,
                        (string)($er['quantity'] ?? ''),
                        (string)($er['weight_kg'] ?? ''),
                        (string)($er['length_cm'] ?? ''),
                        (string)($er['width_cm'] ?? ''),
                        (string)($er['height_cm'] ?? ''),
                        (string)($er['inbound_batch'] ?? ''),
                        (string)($er['scanned_at'] ?? ''),
                        (string)($er['status_updated_at'] ?? ''),
                        (string)($er['order_status'] ?? ''),
                        $consigning,
                    ];
                    $esc = array_map(static function (string $v): string {
                        return '"' . str_replace('"', '""', $v) . '"';
                    }, $vals);
                    $csv .= implode(',', $esc) . "\n";
                }
                $this->sendCsvDownload('订单查询结果.csv', $csv);
            }
            $sqlCount = "SELECT COUNT(*) AS c FROM dispatch_waybills w
                LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
                WHERE {$whereSql}";
            $stmt = $conn->prepare($sqlCount);
            if ($stmt && $types !== '') {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $total = (int)(($stmt->get_result()->fetch_assoc())['c'] ?? 0);
                $stmt->close();
            } elseif ($stmt && $types === '') {
                $stmt->execute();
                $total = (int)(($stmt->get_result()->fetch_assoc())['c'] ?? 0);
                $stmt->close();
            }
            $typesList = $types . 'ii';
            $paramsList = array_merge($params, [$perPage, $offset]);
            $addrColsList = $this->sqlJoinDeliveryCustomerAddrColumns($conn, 'dc');
            $sqlList = "SELECT w.*, cc.client_code AS consigning_client_code, cc.client_name AS consigning_client_name,
                    dc.customer_code AS resolved_customer_code, dc.wechat_id AS resolved_wechat, dc.line_id AS resolved_line,
                    {$addrColsList}, dc.latitude, dc.longitude, dc.route_primary, dc.route_secondary, dc.routes_combined,
                    dc.community_name_en, dc.community_name_th
                FROM dispatch_waybills w
                INNER JOIN dispatch_consigning_clients cc ON cc.id = w.consigning_client_id
                LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
                WHERE {$whereSql}
                ORDER BY w.id DESC
                LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sqlList);
            if ($stmt) {
                if ($typesList !== 'ii') {
                    $stmt->bind_param($typesList, ...$paramsList);
                } else {
                    $stmt->bind_param('ii', $perPage, $offset);
                }
                $stmt->execute();
                $q = $stmt->get_result();
                while ($q && ($row = $q->fetch_assoc())) {
                    $rows[] = $row;
                }
                $stmt->close();
            }
        }

        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        $schemaReady = true;
        $orderStatusCatalog = $this->orderStatusCatalog();
        $consigningUiId = $hideConsigningSelectors
            ? $boundCcId
            : ($filterResolved['single'] ? (int)$filterResolved['id'] : $requestedCc);
        $title = '派送业务 / 订单查询';
        $contentView = __DIR__ . '/../Views/dispatch/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function packageOps(): void
    {
        $this->requireDispatchMenu();
        $canArrivalScan = $this->hasAnyPermission(['dispatch.package_ops.arrival_scan', 'dispatch.manage']);
        $canSelfPickup = $this->hasAnyPermission(['dispatch.package_ops.self_pickup', 'dispatch.manage']);
        $canForwardPush = $this->hasAnyPermission(['dispatch.package_ops.status_fix', 'dispatch.manage']);
        $canStatusFix = $this->hasAnyPermission(['dispatch.package_ops.status_fix', 'dispatch.manage']);
        if (!$canArrivalScan && !$canSelfPickup && !$canForwardPush && !$canStatusFix) {
            $this->denyNoPermission('无权限执行货件操作');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'dispatch_waybills')) {
            $schemaReady = false;
            $title = '派送业务';
            $contentView = __DIR__ . '/../Views/dispatch/hub.php';
            require __DIR__ . '/../Views/layouts/main.php';
            return;
        }
        $this->ensureDispatchSchema($conn);
        $ordersSchemaV2 = $this->columnExists($conn, 'dispatch_waybills', 'order_status');
        $migrationHint = $ordersSchemaV2
            ? ''
            : '尚未执行数据库脚本 022：当前为「基础订单」模式（无订单状态/导入日期/扫描派送时间等栏位）。请执行 database/migrations/022_dispatch_waybill_order_fields.sql 后刷新。';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arrival_scan_submit'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canArrivalScan) {
                echo json_encode(['ok' => false, 'error' => '无权限执行到件扫描', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $rawTrackingNo = (string)($_POST['tracking_no'] ?? '');
            $ret = $this->handleArrivalScanSubmit($conn, $rawTrackingNo);
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['self_pickup_query'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canSelfPickup) {
                echo json_encode(['ok' => false, 'error' => '无权限执行自取录入', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ret = $this->querySelfPickupCandidates($conn, (string)($_POST['customer_code'] ?? ''));
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['self_pickup_submit'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canSelfPickup) {
                echo json_encode(['ok' => false, 'error' => '无权限执行自取录入', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $rawIds = $_POST['waybill_ids'] ?? [];
            if (!is_array($rawIds)) $rawIds = [];
            $ret = $this->submitSelfPickup($conn, (string)($_POST['customer_code'] ?? ''), $rawIds);
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_push_query'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canForwardPush) {
                echo json_encode(['ok' => false, 'error' => '无权限执行手动推送待转发', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ret = $this->queryForwardPushCandidates($conn, (string)($_POST['customer_code'] ?? ''));
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_push_submit'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canForwardPush) {
                echo json_encode(['ok' => false, 'error' => '无权限执行手动推送待转发', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $rawIds = $_POST['waybill_ids'] ?? [];
            if (!is_array($rawIds)) $rawIds = [];
            $ret = $this->submitForwardPush($conn, (string)($_POST['customer_code'] ?? ''), $rawIds);
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_fix_query'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canStatusFix) {
                echo json_encode(['ok' => false, 'error' => '无权限执行货件状态修正', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ret = $this->queryStatusFixCandidates(
                $conn,
                (string)($_POST['tracking_no'] ?? ''),
                (string)($_POST['customer_code'] ?? '')
            );
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_fix_submit'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canStatusFix) {
                echo json_encode(['ok' => false, 'error' => '无权限执行货件状态修正', 'code' => 'no_permission'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$ordersSchemaV2) {
                echo json_encode([
                    'ok' => false,
                    'error' => '当前数据库未启用订单状态字段，请先执行 migration 022',
                    'code' => 'schema_missing',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $rawJson = (string)($_POST['status_updates_json'] ?? '');
            $updates = json_decode($rawJson, true);
            if (!is_array($updates)) $updates = [];
            $ret = $this->submitStatusFix($conn, $updates);
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $schemaReady = true;
        $cainiaoTemplateUrl = trim((string)(getenv('DISPATCH_CAINIAO_TEMPLATE_URL') ?: ''));
        $orderStatusCatalog = $this->orderStatusCatalog();
        $title = '派送业务 / 货件操作';
        $contentView = __DIR__ . '/../Views/dispatch/package_ops.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arrivalLabelPdf(): void
    {
        $token = trim((string)($_GET['t'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $dir = __DIR__ . '/../../storage/arrival-label-pdf';
        $file = $dir . '/' . $token . '.json';
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $raw = @file_get_contents($file);
        $item = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($item)) {
            @unlink($file);
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $expiresAt = (int)($item['expires_at'] ?? 0);
        if ($expiresAt <= 0 || $expiresAt < time()) {
            @unlink($file);
            http_response_code(410);
            echo 'Expired';
            return;
        }
        $label = is_array($item['label'] ?? null) ? $item['label'] : [];
        $pdf = '';
        $pdfFile = $dir . '/' . $token . '.pdf';
        if (!is_file($pdfFile) || (int)@filesize($pdfFile) <= 0) {
            $tmpReason = '';
            $pdfFile = $this->generateArrivalLabelPdfFile($token, $tmpReason);
        }
        if ($pdfFile !== null && is_file($pdfFile)) {
            $bin = @file_get_contents($pdfFile);
            if (is_string($bin) && $bin !== '') $pdf = $bin;
        }
        $pdfMode = 'edge';
        if ($pdf === '') {
            $pdf = $this->renderArrivalLabelPdfBinary($label);
            $pdfMode = 'fallback';
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="arrival-label.pdf"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Arrival-Pdf-Mode: ' . $pdfMode);
        echo $pdf;
    }

    public function arrivalLabelHtml(): void
    {
        $token = trim((string)($_GET['t'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $dir = __DIR__ . '/../../storage/arrival-label-pdf';
        $file = $dir . '/' . $token . '.json';
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $raw = @file_get_contents($file);
        $item = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($item)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $expiresAt = (int)($item['expires_at'] ?? 0);
        if ($expiresAt <= 0 || $expiresAt < time()) {
            @unlink($file);
            http_response_code(410);
            echo 'Expired';
            return;
        }
        $label = is_array($item['label'] ?? null) ? $item['label'] : [];
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $this->renderArrivalLabelHtml($label);
    }

    public function qzCertificate(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.waybills.edit', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限获取打印证书');
        }
        header('Content-Type: text/plain; charset=utf-8');
        $certPath = trim((string)(getenv('QZ_CERT_PATH') ?: ''));
        if ($certPath === '' || !is_file($certPath)) {
            http_response_code(500);
            echo '';
            return;
        }
        $cert = @file_get_contents($certPath);
        if ($cert === false) {
            http_response_code(500);
            echo '';
            return;
        }
        echo $cert;
    }

    public function qzSign(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.waybills.edit', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限执行打印签名');
        }
        header('Content-Type: text/plain; charset=utf-8');
        $raw = (string)($_POST['request'] ?? '');
        if ($raw === '') {
            http_response_code(400);
            echo '';
            return;
        }
        $keyPath = trim((string)(getenv('QZ_PRIVATE_KEY_PATH') ?: ''));
        if ($keyPath === '' || !is_file($keyPath)) {
            http_response_code(500);
            echo '';
            return;
        }
        $pass = (string)(getenv('QZ_PRIVATE_KEY_PASSPHRASE') ?: '');
        $pem = @file_get_contents($keyPath);
        if ($pem === false || $pem === '') {
            http_response_code(500);
            echo '';
            return;
        }
        $pkey = @openssl_pkey_get_private($pem, $pass !== '' ? $pass : null);
        if ($pkey === false) {
            http_response_code(500);
            echo '';
            return;
        }
        $ok = @openssl_sign($raw, $signature, $pkey, OPENSSL_ALGO_SHA256);
        @openssl_free_key($pkey);
        if (!$ok) {
            http_response_code(500);
            echo '';
            return;
        }
        echo base64_encode($signature);
    }

    /** 批量 CSV 与手工录入订单（独立页） */
    public function orderImport(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.waybills.import', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限导入订单');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'dispatch_waybills')) {
            $schemaReady = false;
            $ordersSchemaV2 = false;
            $title = '派送业务';
            $contentView = __DIR__ . '/../Views/dispatch/hub.php';
            require __DIR__ . '/../Views/layouts/main.php';
            return;
        }
        $this->ensureDispatchSchema($conn);
        $ordersSchemaV2 = $this->columnExists($conn, 'dispatch_waybills', 'order_status');
        $migrationHint = $ordersSchemaV2
            ? ''
            : '尚未执行数据库脚本 022：当前为「基础订单」模式（无订单状态/导入日期/扫描派送时间等栏位）。请执行 database/migrations/022_dispatch_waybill_order_fields.sql 后刷新，即可使用完整订单字段与派送照片表。';

        $message = '';
        $error = '';
        $importFailureDetails = [];
        $actorId = isset($_SESSION['auth_user_id']) ? (int)$_SESSION['auth_user_id'] : 0;
        $boundCcId = isset($_SESSION['auth_dispatch_consigning_client_id']) ? (int)$_SESSION['auth_dispatch_consigning_client_id'] : 0;
        $hideConsigningSelectors = $boundCcId > 0;
        $importDate = date('Y-m-d');

        if (isset($_GET['export']) && (string)$_GET['export'] === 'order_csv_template') {
            $header = "委托客户编码,原始单号,派送客户编号,重量(kg),长(cm),宽(cm),高(cm),体积(m³),数量,入库批次,订单状态\n";
            $example = "CLIENT001,TH1234567890,R001,1.5,30,20,10,0.02,1,BATCH20260401,待入库\n";
            $this->sendCsvDownload('订单导入模板.csv', $header . $example);
        }

        $consigningOptions = $this->activeConsigningClients($conn);
        $dispatchBoundClientMissing = false;
        if ($boundCcId > 0) {
            $boundRow = $this->consigningClientRowById($conn, $boundCcId);
            $consigningOptions = $boundRow ? [$boundRow] : [];
            $dispatchBoundClientMissing = $boundRow === null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_import_batch'])) {
            if ($dispatchBoundClientMissing) {
                $error = '当前账号绑定的委托客户不存在或已删除，无法操作。请联系管理员检查用户绑定。';
            } else {
                $ts = trim((string)($_POST['import_batch_created_at'] ?? ''));
                $today = date('Y-m-d');
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ts) || substr($ts, 0, 10) !== $today) {
                    $error = '请选择当日有效的导入时间';
                } else {
                    $wDel = "source = 'import' AND DATE(created_at) = ? AND created_at = ?";
                    $typesDel = 'ss';
                    $paramsDel = [$today, $ts];
                    if ($boundCcId > 0) {
                        $wDel .= ' AND consigning_client_id = ?';
                        $typesDel .= 'i';
                        $paramsDel[] = $boundCcId;
                    }
                    $stmtDel = $conn->prepare("DELETE FROM dispatch_waybills WHERE {$wDel}");
                    if ($stmtDel) {
                        $stmtDel->bind_param($typesDel, ...$paramsDel);
                        $stmtDel->execute();
                        $deleted = (int)$stmtDel->affected_rows;
                        $stmtDel->close();
                        $message = "已删除该批次导入订单 {$deleted} 条（仅含 CSV 导入，且为当日所选时间）";
                    } else {
                        $error = '删除失败，请稍后重试';
                    }
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_orders_csv'])) {
            if ($dispatchBoundClientMissing) {
                $error = '当前账号绑定的委托客户不存在或已删除，无法导入。请联系管理员检查用户绑定。';
            } elseif (!isset($_FILES['orders_csv']) || !is_uploaded_file((string)($_FILES['orders_csv']['tmp_name'] ?? ''))) {
                $error = '请选择要上传的 CSV 文件';
            } else {
                $tmp = (string)$_FILES['orders_csv']['tmp_name'];
                $fh = fopen($tmp, 'rb');
                if (!$fh) {
                    $error = '无法读取上传文件';
                } else {
                    $headerRow = fgetcsv($fh);
                    $ok = 0;
                    $dup = 0;
                    $fail = 0;
                    $failureLog = [];
                    $failureLogMax = 200;
                    $csvRowIndex = 1;
                    while (($row = fgetcsv($fh)) !== false) {
                        $csvRowIndex++;
                        if ($row === [null] || $row === false || (count($row) === 1 && trim((string)($row[0] ?? '')) === '')) {
                            continue;
                        }
                        $map = [];
                        if (is_array($headerRow)) {
                            foreach ($headerRow as $i => $key) {
                                $k = trim((string)$key);
                                if (str_starts_with($k, "\xEF\xBB\xBF")) {
                                    $k = trim(substr($k, 3));
                                }
                                if ($k !== '') {
                                    $canon = $this->canonicalOrderImportCsvField($k);
                                    if ($canon !== '') {
                                        $map[$canon] = trim((string)($row[$i] ?? ''));
                                    }
                                }
                            }
                        }
                        $ccCode = (string)($map['consigning_client_code'] ?? '');
                        $track = (string)($map['original_tracking_no'] ?? '');
                        $dcode = (string)($map['delivery_customer_code'] ?? '');
                        $w = (float)($map['weight_kg'] ?? 0);
                        $len = (float)($map['length_cm'] ?? 0);
                        $wid = (float)($map['width_cm'] ?? 0);
                        $hei = (float)($map['height_cm'] ?? 0);
                        $v = (float)($map['volume_m3'] ?? 0);
                        $q = (float)($map['quantity'] ?? 1);
                        $batch = (string)($map['inbound_batch'] ?? '');
                        $statusRaw = trim((string)($map['order_status'] ?? ''));
                        $status = $statusRaw !== '' ? $statusRaw : '待入库';
                        if ($ccCode === '' || $track === '') {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => '缺少委托客户或原始单号（表头需能对应到：委托客户编码/委托客户、原始单号/厂家批号等；勿改表头首格编码）',
                                ];
                            }
                            continue;
                        }
                        $ccRow = null;
                        $stCc = $conn->prepare('SELECT id FROM dispatch_consigning_clients WHERE client_code = ? AND status = 1 LIMIT 1');
                        if ($stCc) {
                            $stCc->bind_param('s', $ccCode);
                            $stCc->execute();
                            $ccRow = $stCc->get_result()->fetch_assoc();
                            $stCc->close();
                        }
                        if (!$ccRow) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => '委托客户编码「' . $ccCode . '」不存在或未启用',
                                ];
                            }
                            continue;
                        }
                        $ccId = (int)$ccRow['id'];
                        if ($boundCcId > 0 && $ccId !== $boundCcId) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => '委托客户「' . $ccCode . '」与当前账号绑定的委托客户不一致',
                                ];
                            }
                            continue;
                        }
                        $ins = $this->insertWaybillRow($conn, $ccId, $track, $dcode, $w, $len, $wid, $hei, $v, $q > 0 ? $q : 1.0, $batch, 'import', $actorId, $importDate, $status);
                        if ($ins['ok']) {
                            $ok++;
                        } elseif ($ins['error'] === 'duplicate') {
                            $dup++;
                        } else {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $err = trim((string)($ins['error'] ?? ''));
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => $err !== '' ? ('保存失败：' . $err) : '保存失败（未知原因）',
                                ];
                            }
                        }
                    }
                    fclose($fh);
                    $importFailureDetails = $failureLog;
                    $message = "导入完成：成功 {$ok} 条，重复跳过 {$dup} 条，失败 {$fail} 条";
                    if ($fail > $failureLogMax) {
                        $message .= '（下方仅列出前 ' . (string)$failureLogMax . ' 条失败原因）';
                    }
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_waybill'])) {
            if ($dispatchBoundClientMissing) {
                $error = '当前账号绑定的委托客户不存在或已删除，无法录入。请联系管理员检查用户绑定。';
            } else {
                $ccId = $boundCcId > 0 ? $boundCcId : (int)($_POST['consigning_client_id'] ?? 0);
                $track = trim((string)($_POST['original_tracking_no'] ?? ''));
                $dcode = trim((string)($_POST['delivery_customer_code'] ?? ''));
                $weight = (string)($_POST['weight_kg'] ?? '0');
                $length = (string)($_POST['length_cm'] ?? '0');
                $width = (string)($_POST['width_cm'] ?? '0');
                $height = (string)($_POST['height_cm'] ?? '0');
                $volume = (string)($_POST['volume_m3'] ?? '0');
                $qty = (string)($_POST['quantity'] ?? '1');
                $batch = trim((string)($_POST['inbound_batch'] ?? ''));
                $statusRaw = trim((string)($_POST['order_status'] ?? ''));
                $status = $statusRaw !== '' ? $statusRaw : '待入库';
                $source = 'import';
                if ($ccId <= 0) {
                    $error = '请选择委托客户';
                } elseif ($track === '' || mb_strlen($track) > 120) {
                    $error = '原始单号不能为空且不超过 120 字';
                } elseif (mb_strlen($batch) > 100) {
                    $error = '入库批次过长';
                } elseif (mb_strlen($dcode) > 60) {
                    $error = '派送客户编号过长';
                } else {
                    $weightF = (float)$weight;
                    $lengthF = (float)$length;
                    $widthF = (float)$width;
                    $heightF = (float)$height;
                    $volumeF = (float)$volume;
                    $qtyF = (float)$qty;
                    if ($weightF < 0 || $lengthF < 0 || $widthF < 0 || $heightF < 0 || $volumeF < 0 || $qtyF <= 0) {
                        $error = '重量、长宽高、体积不能为负，数量须大于 0';
                    } else {
                        $ins = $this->insertWaybillRow($conn, $ccId, $track, $dcode, $weightF, $lengthF, $widthF, $heightF, $volumeF, $qtyF, $batch, $source, $actorId, $importDate, $status);
                        if ($ins['ok']) {
                            $message = '已新增订单';
                        } elseif ($ins['error'] === 'duplicate') {
                            $error = '同一委托客户、原始单号与入库批次已存在';
                        } else {
                            $error = $ins['error'];
                        }
                    }
                }
            }
        }

        $importBatchOptions = [];
        if (!$dispatchBoundClientMissing) {
            $today = date('Y-m-d');
            if ($boundCcId > 0) {
                $stmtB = $conn->prepare("
                    SELECT created_at, COUNT(*) AS cnt
                    FROM dispatch_waybills
                    WHERE source = 'import' AND DATE(created_at) = ? AND consigning_client_id = ?
                    GROUP BY created_at
                    ORDER BY created_at DESC
                ");
                if ($stmtB) {
                    $stmtB->bind_param('si', $today, $boundCcId);
                    $stmtB->execute();
                    $rb = $stmtB->get_result();
                    while ($rb && ($br = $rb->fetch_assoc())) {
                        $importBatchOptions[] = [
                            'created_at' => (string)($br['created_at'] ?? ''),
                            'cnt' => (int)($br['cnt'] ?? 0),
                        ];
                    }
                    $stmtB->close();
                }
            } else {
                $stmtB = $conn->prepare("
                    SELECT created_at, COUNT(*) AS cnt
                    FROM dispatch_waybills
                    WHERE source = 'import' AND DATE(created_at) = ?
                    GROUP BY created_at
                    ORDER BY created_at DESC
                ");
                if ($stmtB) {
                    $stmtB->bind_param('s', $today);
                    $stmtB->execute();
                    $rb = $stmtB->get_result();
                    while ($rb && ($br = $rb->fetch_assoc())) {
                        $importBatchOptions[] = [
                            'created_at' => (string)($br['created_at'] ?? ''),
                            'cnt' => (int)($br['cnt'] ?? 0),
                        ];
                    }
                    $stmtB->close();
                }
            }
        }

        $title = '派送业务 / 订单导入';
        $contentView = __DIR__ . '/../Views/dispatch/order_import.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function consigningClients(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.consigning_clients.view', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限查看委托客户');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureDispatchSchema($conn);
        $message = '';
        $error = '';
        $canEdit = $this->hasAnyPermission(['dispatch.consigning_clients.edit', 'dispatch.manage']);

        if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_consigning_client'])) {
            $delId = (int)($_POST['consigning_client_id'] ?? 0);
            if ($delId <= 0) {
                $error = '参数无效';
            } else {
                $unboundUsers = 0;
                if ($this->tableExists($conn, 'users') && $this->columnExists($conn, 'users', 'dispatch_consigning_client_id')) {
                    $cntSt = $conn->prepare('SELECT COUNT(*) AS c FROM users WHERE dispatch_consigning_client_id = ?');
                    if ($cntSt) {
                        $cntSt->bind_param('i', $delId);
                        $cntSt->execute();
                        $cntRow = $cntSt->get_result()->fetch_assoc();
                        $cntSt->close();
                        $unboundUsers = (int)($cntRow['c'] ?? 0);
                    }
                    if ($unboundUsers > 0) {
                        $clr = $conn->prepare('UPDATE users SET dispatch_consigning_client_id = NULL WHERE dispatch_consigning_client_id = ?');
                        if ($clr) {
                            $clr->bind_param('i', $delId);
                            try {
                                $clr->execute();
                            } catch (mysqli_sql_exception $e) {
                                $error = '解除用户绑定失败，未删除委托客户';
                            }
                            $clr->close();
                        } else {
                            $error = '解除用户绑定失败，未删除委托客户';
                        }
                    }
                }
                if ($error === '') {
                    $stmt = $conn->prepare('DELETE FROM dispatch_consigning_clients WHERE id = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('i', $delId);
                        try {
                            if ($stmt->execute() && $stmt->affected_rows > 0) {
                                $this->writeAuditLog($conn, 'dispatch', 'dispatch.consigning_client.delete', 'dispatch_consigning_client', $delId, []);
                                $message = '已删除委托客户（其下派送客户与订单已一并删除）';
                                if ($unboundUsers > 0) {
                                    $message .= '；已自动解除 ' . $unboundUsers . ' 个登录账号与该委托客户的绑定，请到「系统管理 → 用户管理」为相关账号重新指定委托客户（如需）。';
                                }
                            } else {
                                $error = '删除失败或记录不存在';
                            }
                        } catch (mysqli_sql_exception $e) {
                            $em = $e->getMessage();
                            $error = (stripos($em, '1451') !== false || stripos($em, 'foreign key') !== false || stripos($em, 'Cannot delete') !== false)
                                ? '删除失败：仍有其他数据引用该委托客户（外键约束），请联系开发人员检查数据库。'
                                : '删除失败';
                        }
                        $stmt->close();
                    } else {
                        $error = '删除失败';
                    }
                }
            }
        }

        if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_consigning_client_edit'])) {
            $eid = (int)($_POST['consigning_client_id'] ?? 0);
            $ename = trim((string)($_POST['client_name'] ?? ''));
            $eparty = (int)($_POST['party_id'] ?? 0);
            $eremark = trim((string)($_POST['remark'] ?? ''));
            $estatus = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;
            if ($eid <= 0) {
                $error = '参数无效';
            } elseif ($ename === '' || mb_strlen($ename) > 160) {
                $error = '委托客户名称不能为空且不超过 160 字';
            } elseif (mb_strlen($eremark) > 255) {
                $error = '备注过长';
            } else {
                $up = null;
                if ($eparty <= 0) {
                    $up = $conn->prepare('UPDATE dispatch_consigning_clients SET client_name = ?, party_id = NULL, remark = ?, status = ? WHERE id = ? LIMIT 1');
                    if ($up) {
                        $up->bind_param('ssii', $ename, $eremark, $estatus, $eid);
                    }
                } else {
                    $up = $conn->prepare('UPDATE dispatch_consigning_clients SET client_name = ?, party_id = ?, remark = ?, status = ? WHERE id = ? LIMIT 1');
                    if ($up) {
                        $up->bind_param('sisii', $ename, $eparty, $eremark, $estatus, $eid);
                    }
                }
                if (!$up) {
                    $error = '保存失败';
                } else {
                    try {
                        if ($up->execute()) {
                            $this->writeAuditLog($conn, 'dispatch', 'dispatch.consigning_client.update', 'dispatch_consigning_client', $eid, []);
                            $message = '已保存委托客户修改';
                        } else {
                            $error = '保存失败';
                        }
                    } catch (mysqli_sql_exception $e) {
                        $error = '保存失败';
                    }
                    $up->close();
                }
            }
        }

        if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consigning_client'])) {
            $code = trim((string)($_POST['client_code'] ?? ''));
            $name = trim((string)($_POST['client_name'] ?? ''));
            $remark = trim((string)($_POST['remark'] ?? ''));
            $partyId = (int)($_POST['party_id'] ?? 0);
            if ($code === '' || mb_strlen($code) > 40) {
                $error = '委托客户编号不能为空且不超过 40 字';
            } elseif ($name === '' || mb_strlen($name) > 160) {
                $error = '委托客户名称不能为空且不超过 160 字';
            } elseif (mb_strlen($remark) > 255) {
                $error = '备注过长';
            } else {
                $partySql = $partyId > 0 ? $partyId : null;
                if ($partySql === null) {
                    $stmt = $conn->prepare('
                        INSERT INTO dispatch_consigning_clients (client_code, client_name, party_id, status, remark)
                        VALUES (?, ?, NULL, 1, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param('sss', $code, $name, $remark);
                        if ($stmt->execute()) {
                            $message = '已新增委托客户';
                        } elseif ($conn->errno === 1062) {
                            $error = '委托客户编号已存在';
                        } else {
                            $error = '保存失败';
                        }
                        $stmt->close();
                    } else {
                        $error = '保存失败';
                    }
                } else {
                    $stmt = $conn->prepare('
                        INSERT INTO dispatch_consigning_clients (client_code, client_name, party_id, status, remark)
                        VALUES (?, ?, ?, 1, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param('ssis', $code, $name, $partyId, $remark);
                        if ($stmt->execute()) {
                            $message = '已新增委托客户';
                        } elseif ($conn->errno === 1062) {
                            $error = '委托客户编号已存在';
                        } else {
                            $error = '保存失败';
                        }
                        $stmt->close();
                    } else {
                        $error = '保存失败';
                    }
                }
            }
        }

        $rows = [];
        $res = $conn->query('
            SELECT c.*, fp.party_name
            FROM dispatch_consigning_clients c
            LEFT JOIN finance_parties fp ON fp.id = c.party_id
            ORDER BY c.id DESC
        ');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }

        $parties = $this->financePartiesForSelect($conn);
        $title = '派送业务 / 委托客户';
        $contentView = __DIR__ . '/../Views/dispatch/consigning_clients.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function deliveryCustomers(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.delivery_customers.view', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限查看派送客户');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureDispatchSchema($conn);
        $deliveryCustomerSchemaV2 = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state');
        $deliveryCustomerHasPhone = $this->columnExists($conn, 'dispatch_delivery_customers', 'phone');
        $deliveryCustomerHasCreatedMark = $this->columnExists($conn, 'dispatch_delivery_customers', 'created_marked_at');
        $deliveryCustomerHasAddressMark = $this->columnExists($conn, 'dispatch_delivery_customers', 'address_geo_updated_at');
        $deliveryCustomerSchemaV3 = $deliveryCustomerHasPhone && $deliveryCustomerHasCreatedMark && $deliveryCustomerHasAddressMark;
        $migrationHintV3 = $deliveryCustomerSchemaV3
            ? ''
            : '尚未执行数据库脚本 026：请执行 database/migrations/026_dispatch_delivery_customer_phone_and_change_marks.sql，以启用电话字段与「新/改」时间标记。';
        $deliveryCustomerStateCatalog = $this->deliveryCustomerStateCatalog();
        $message = '';
        $error = '';
        $importFailureDetails = [];
        $canEdit = $this->hasAnyPermission(['dispatch.delivery_customers.edit', 'dispatch.manage']);

        $boundCcId = isset($_SESSION['auth_dispatch_consigning_client_id']) ? (int)$_SESSION['auth_dispatch_consigning_client_id'] : 0;
        $hideConsigningSelectors = $boundCcId > 0;

        $consigningOptions = $this->activeConsigningClients($conn);
        $dispatchBoundClientMissing = false;
        if ($hideConsigningSelectors) {
            $boundRow = $this->consigningClientRowById($conn, $boundCcId);
            $consigningOptions = $boundRow ? [$boundRow] : [];
            $dispatchBoundClientMissing = $boundRow === null;
            $filterCcId = $boundCcId;
        } else {
            $filterCcId = (int)($_GET['consigning_client_id'] ?? 0);
            if ($filterCcId > 0 && !empty($consigningOptions)) {
                $inList = false;
                foreach ($consigningOptions as $o) {
                    if ((int)$o['id'] === $filterCcId) {
                        $inList = true;
                        break;
                    }
                }
                if (!$inList) {
                    $filterCcId = 0;
                }
            }
            // filterCcId === 0 表示「全部委托客户」，列表跨客户分页展示
        }

        if (isset($_GET['export']) && (string)$_GET['export'] === 'delivery_csv_template') {
            $exCc = 'CLIENT001';
            if ($filterCcId > 0) {
                foreach ($consigningOptions as $o) {
                    if ((int)($o['id'] ?? 0) === $filterCcId) {
                        $c = trim((string)($o['client_code'] ?? ''));
                        if ($c !== '') {
                            $exCc = $c;
                        }
                        break;
                    }
                }
            } elseif (!empty($consigningOptions)) {
                $c0 = trim((string)($consigningOptions[0]['client_code'] ?? ''));
                if ($c0 !== '') {
                    $exCc = $c0;
                }
            }
            $hdr = "委托客户编码,派送客户编号,微信号,Line,收件人,电话,门牌号,路（巷）,村,镇（街道）（乡）,县（区）,府,Zipcode,定位,定位状态,主路线,副路线,小区英文名,小区泰文名,客户状态,客户要求\n";
            $exCcCsv = str_contains($exCc, ',') || str_contains($exCc, '"') || str_contains($exCc, "\n") || str_contains($exCc, "\r")
                ? '"' . str_replace('"', '""', $exCc) . '"'
                : $exCc;
            $ex = $exCcCsv . ',R001,my_wx,line_x,张三,0812345678,99/1,Soi Ramkhamhaeng,Moo 5,Hua Mak,Bang Kapi,Bangkok,10240,"13.756331, 100.501765",已定位,主A,副B,TownEn,ทาวน์ไทย,正常,需先电话联络后送货' . "\n";
            $this->sendCsvDownload('派送客户导入模板.csv', $hdr . $ex);
        }

        $dqCustomerCode = trim((string)($_GET['q_customer_code'] ?? ''));
        $dqWechat = trim((string)($_GET['q_wechat'] ?? ''));
        $dqRoutePrimary = trim((string)($_GET['q_route_primary'] ?? ''));
        $dqCustomerState = trim((string)($_GET['q_customer_state'] ?? ''));
        if ($dqCustomerState !== '' && !in_array($dqCustomerState, $deliveryCustomerStateCatalog, true)) {
            $dqCustomerState = '';
        }
        $deliveryCustomerHasGeoProfile = $this->columnExists($conn, 'dispatch_delivery_customers', 'geo_status');
        $deliveryCustomerGeoProfileHint = $deliveryCustomerHasGeoProfile
            ? ''
            : '尚未执行数据库脚本 048：请执行 database/migrations/048_dispatch_delivery_customer_thai_address_profile.sql 后，即可在列表中显示「完整泰文地址 / 定位状态」并按其筛选。';
        $deliveryCustomerGeoStatusCatalog = ['已定位', '免定位(OT/UDA)', '待补定位(准客户)', '缺失待补'];
        $dqGeoStatus = trim((string)($_GET['q_geo_status'] ?? ''));
        if ($dqGeoStatus !== '' && !in_array($dqGeoStatus, $deliveryCustomerGeoStatusCatalog, true)) {
            $dqGeoStatus = '';
        }
        $dqAddrTh = trim((string)($_GET['q_addr_th'] ?? ''));
        if (mb_strlen($dqAddrTh) > 200) {
            $dqAddrTh = mb_substr($dqAddrTh, 0, 200);
        }
        $dqAmphoe = trim((string)($_GET['q_amphoe'] ?? ''));
        if (mb_strlen($dqAmphoe) > 160) {
            $dqAmphoe = mb_substr($dqAmphoe, 0, 160);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_customer_state_update'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => '无权限'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state')) {
                echo json_encode(['ok' => false, 'error' => '请先执行数据库迁移：024_dispatch_delivery_customer_state_routes.sql'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $did = (int)($_POST['delivery_id'] ?? 0);
            $st = $this->normalizeDeliveryCustomerState((string)($_POST['customer_state'] ?? '正常'));
            if ($did <= 0) {
                echo json_encode(['ok' => false, 'error' => '参数无效'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $chk = $conn->prepare('SELECT id, consigning_client_id FROM dispatch_delivery_customers WHERE id = ? LIMIT 1');
            if (!$chk) {
                echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $chk->bind_param('i', $did);
            try {
                $chk->execute();
            } catch (mysqli_sql_exception $e) {
                $chk->close();
                echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$exists) {
                echo json_encode(['ok' => false, 'error' => '记录不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ownerCcId = (int)($exists['consigning_client_id'] ?? 0);
            if ($hideConsigningSelectors && $ownerCcId !== $boundCcId) {
                echo json_encode(['ok' => false, 'error' => '无权修改该记录'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $upd = $conn->prepare('UPDATE dispatch_delivery_customers SET customer_state = ? WHERE id = ? AND consigning_client_id = ?');
            if (!$upd || !$upd->bind_param('sii', $st, $did, $ownerCcId)) {
                if ($upd) {
                    $upd->close();
                }
                echo json_encode(['ok' => false, 'error' => '更新失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $okExec = $upd->execute();
            } catch (mysqli_sql_exception $e) {
                $okExec = false;
            }
            $upd->close();
            if (!$okExec) {
                echo json_encode(['ok' => false, 'error' => '更新失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_delivery_customer'])) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => '无权限'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($dispatchBoundClientMissing) {
                echo json_encode(['ok' => false, 'error' => '当前账号绑定异常，无法删除'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $delId = (int)($_POST['delivery_id'] ?? 0);
            if ($delId <= 0) {
                echo json_encode(['ok' => false, 'error' => '参数无效'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $chk = $conn->prepare('SELECT id, consigning_client_id FROM dispatch_delivery_customers WHERE id = ? LIMIT 1');
            if (!$chk) {
                echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $chk->bind_param('i', $delId);
            $chk->execute();
            $ex = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$ex) {
                echo json_encode(['ok' => false, 'error' => '记录不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ownerCcId = (int)($ex['consigning_client_id'] ?? 0);
            if ($hideConsigningSelectors && $ownerCcId !== $boundCcId) {
                echo json_encode(['ok' => false, 'error' => '无权删除该记录'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $del = $conn->prepare('DELETE FROM dispatch_delivery_customers WHERE id = ? AND consigning_client_id = ? LIMIT 1');
            if (!$del) {
                echo json_encode(['ok' => false, 'error' => '删除失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $del->bind_param('ii', $delId, $ownerCcId);
            try {
                $okDel = $del->execute();
            } catch (mysqli_sql_exception $e) {
                $okDel = false;
            }
            $del->close();
            if (!$okDel) {
                echo json_encode(['ok' => false, 'error' => '删除失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->writeAuditLog($conn, 'dispatch', 'dispatch.delivery_customer.delete', 'dispatch_delivery_customer', $delId, []);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_delivery_customer_edit'])) {
            if (!$this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state')) {
                $error = '请先执行数据库迁移：024_dispatch_delivery_customer_state_routes.sql';
            } elseif ($dispatchBoundClientMissing) {
                $error = '当前账号绑定的委托客户不存在或已删除，无法修改。';
            } else {
                $editId = (int)($_POST['delivery_id'] ?? 0);
                $customerCode = trim((string)($_POST['customer_code'] ?? ''));
                $wechat = trim((string)($_POST['wechat_id'] ?? ''));
                $line = trim((string)($_POST['line_id'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $addrHouseNo = trim((string)($_POST['addr_house_no'] ?? ''));
                $addrRoadSoi = trim((string)($_POST['addr_road_soi'] ?? ''));
                $addrMooVillage = trim((string)($_POST['addr_moo_village'] ?? ''));
                $addrTambon = trim((string)($_POST['addr_tambon'] ?? ''));
                $addrAmphoe = trim((string)($_POST['addr_amphoe'] ?? ''));
                $addrProvince = trim((string)($_POST['addr_province'] ?? ''));
                $addrZipcode = trim((string)($_POST['addr_zipcode'] ?? ''));
                $hasThGeoMasterCols = $this->columnExists($conn, 'dispatch_delivery_customers', 'th_geo_subdistrict_id');
                $thGeoSubPost = $hasThGeoMasterCols ? (int)($_POST['th_geo_subdistrict_id'] ?? 0) : 0;
                $thGeoChainResolved = null;
                $thGeoSubdistrictInvalid = false;
                if ($hasThGeoMasterCols && $thGeoSubPost > 0) {
                    $thGeoChainResolved = $this->resolveThGeoChainFromSubdistrictId($conn, $thGeoSubPost);
                    if ($thGeoChainResolved === null) {
                        $thGeoSubdistrictInvalid = true;
                    } else {
                        $addrTambon = trim((string)($thGeoChainResolved['tambon_th']));
                        $addrAmphoe = trim((string)($thGeoChainResolved['amphoe_th']));
                        $addrProvince = trim((string)($thGeoChainResolved['province_th']));
                        $addrZipcode = trim((string)($thGeoChainResolved['zipcode']));
                    }
                }
                $geoStatusRaw = trim((string)($_POST['geo_status'] ?? ''));
                $geoRaw = (string)($_POST['geo_position'] ?? '');
                $rp = trim((string)($_POST['route_primary'] ?? ''));
                $rs = trim((string)($_POST['route_secondary'] ?? ''));
                $en = trim((string)($_POST['community_name_en'] ?? ''));
                $th = trim((string)($_POST['community_name_th'] ?? ''));
                $customerRequirement = trim((string)($_POST['customer_requirements'] ?? ''));
                $custState = $this->normalizeDeliveryCustomerState((string)($_POST['customer_state'] ?? '正常'));
                $geoParsed = $this->parseDeliveryGeoPosition($geoRaw);

                if ($editId <= 0) {
                    $error = '参数无效';
                } elseif ($thGeoSubdistrictInvalid) {
                    $error = '泰国行政区（镇/乡）选择无效，请重新选择或清空后手填';
                } elseif ($customerCode === '' || mb_strlen($customerCode) > 60) {
                    $error = '派送客户编号不能为空且不超过 60 字';
                } elseif (mb_strlen($wechat) > 120 || mb_strlen($line) > 120 || mb_strlen($phone) > 40) {
                    $error = '微信、Line 或电话字段过长';
                } elseif (
                    mb_strlen($addrHouseNo) > 120 || mb_strlen($addrRoadSoi) > 160 || mb_strlen($addrMooVillage) > 160
                    || mb_strlen($addrTambon) > 160 || mb_strlen($addrAmphoe) > 160 || mb_strlen($addrProvince) > 160
                    || mb_strlen($addrZipcode) > 20
                ) {
                    $error = '泰国地址结构字段过长';
                } elseif (mb_strlen($geoRaw) > 48) {
                    $error = '定位内容过长';
                } elseif (!$geoParsed['ok']) {
                    $error = $geoParsed['error'];
                } elseif (mb_strlen($rp) > 120 || mb_strlen($rs) > 120) {
                    $error = '路线字段过长';
                } elseif (mb_strlen($en) > 160 || mb_strlen($th) > 160) {
                    $error = '小区名称过长';
                } elseif (mb_strlen($customerRequirement) > 5000) {
                    $error = '客户要求字段过长（最多 5000 字）';
                } else {
                    $ownRow = null;
                    $ownerCcId = 0;
                    $oldH = '';
                    $oldR = '';
                    $oldM = '';
                    $oldT = '';
                    $oldA = '';
                    $oldP = '';
                    $oldZ = '';
                    $oldLat = null;
                    $oldLng = null;
                    $oldRoutePrimary = '';
                    $oldCustomerCodeForForward = '';
                    $oldWechat = '';
                    $oldLine = '';
                    $oldPhone = '';
                    $oldEn = '';
                    $oldTh = '';
                    $oldRecipient = '';
                    $fwdSync = false;
                    $own = $conn->prepare('SELECT id, customer_code, consigning_client_id, route_primary FROM dispatch_delivery_customers WHERE id = ? LIMIT 1');
                    if (!$own) {
                        $error = '查询失败';
                    } else {
                        $own->bind_param('i', $editId);
                        $own->execute();
                        $ownRow = $own->get_result()->fetch_assoc();
                        $own->close();
                        if (!$ownRow) {
                            $error = '记录不存在或无权修改';
                        } else {
                            $ownerCcId = (int)($ownRow['consigning_client_id'] ?? 0);
                            $oldRoutePrimary = trim((string)($ownRow['route_primary'] ?? ''));
                            $oldCustomerCodeForForward = trim((string)($ownRow['customer_code'] ?? ''));
                            $oldGeo = $conn->prepare('
                                SELECT addr_house_no, addr_road_soi, addr_moo_village, addr_tambon, addr_amphoe, addr_province, addr_zipcode,
                                    latitude, longitude,
                                    wechat_id, line_id, phone, recipient_name, community_name_en, community_name_th
                                FROM dispatch_delivery_customers WHERE id = ? AND consigning_client_id = ? LIMIT 1
                            ');
                            if ($oldGeo) {
                                $oldGeo->bind_param('ii', $editId, $ownerCcId);
                                try {
                                    $oldGeo->execute();
                                    $og = $oldGeo->get_result()->fetch_assoc();
                                    if (is_array($og)) {
                                        $oldH = trim((string)($og['addr_house_no'] ?? ''));
                                        $oldR = trim((string)($og['addr_road_soi'] ?? ''));
                                        $oldM = trim((string)($og['addr_moo_village'] ?? ''));
                                        $oldT = trim((string)($og['addr_tambon'] ?? ''));
                                        $oldA = trim((string)($og['addr_amphoe'] ?? ''));
                                        $oldP = trim((string)($og['addr_province'] ?? ''));
                                        $oldZ = trim((string)($og['addr_zipcode'] ?? ''));
                                        $oldLat = ($og['latitude'] ?? null) !== null && (string)$og['latitude'] !== '' ? (float)$og['latitude'] : null;
                                        $oldLng = ($og['longitude'] ?? null) !== null && (string)$og['longitude'] !== '' ? (float)$og['longitude'] : null;
                                        $oldWechat = trim((string)($og['wechat_id'] ?? ''));
                                        $oldLine = trim((string)($og['line_id'] ?? ''));
                                        $oldPhone = trim((string)($og['phone'] ?? ''));
                                        $oldRecipient = trim((string)($og['recipient_name'] ?? ''));
                                        $oldEn = trim((string)($og['community_name_en'] ?? ''));
                                        $oldTh = trim((string)($og['community_name_th'] ?? ''));
                                    }
                                } catch (mysqli_sql_exception $e) {
                                    // ignore
                                }
                                $oldGeo->close();
                            }
                            if ($hideConsigningSelectors && $ownerCcId !== $boundCcId) {
                                $error = '记录不存在或无权修改';
                            } elseif ((string)($ownRow['customer_code'] ?? '') !== $customerCode) {
                                $dupStmt = $conn->prepare('SELECT id FROM dispatch_delivery_customers WHERE consigning_client_id = ? AND customer_code = ? AND id <> ? LIMIT 1');
                                if ($dupStmt) {
                                    $dupStmt->bind_param('isi', $ownerCcId, $customerCode, $editId);
                                    $dupStmt->execute();
                                    $dupHit = $dupStmt->get_result()->fetch_assoc();
                                    $dupStmt->close();
                                    if ($dupHit) {
                                        $error = '该委托客户下派送客户编号已存在';
                                    }
                                }
                            }
                        }
                    }
                    if ($error === '' && is_array($ownRow) && $ownerCcId > 0) {
                        $routesCombined = $this->buildRoutesCombined($rp, $rs);
                        $lat = $geoParsed['lat'];
                        $lng = $geoParsed['lng'];
                        $hasCoords = $lat !== null && $lng !== null;
                        $u = null;
                        if ($hasCoords) {
                            $u = $conn->prepare('
                                UPDATE dispatch_delivery_customers SET
                                    customer_code = ?, wechat_id = ?, line_id = ?,
                                    latitude = ?, longitude = ?,
                                    route_primary = ?, route_secondary = ?, routes_combined = ?,
                                    community_name_en = ?, community_name_th = ?, customer_state = ?
                                WHERE id = ? AND consigning_client_id = ?
                            ');
                            if ($u) {
                                $u->bind_param(
                                    'sssddssssssii',
                                    $customerCode,
                                    $wechat,
                                    $line,
                                    $lat,
                                    $lng,
                                    $rp,
                                    $rs,
                                    $routesCombined,
                                    $en,
                                    $th,
                                    $custState,
                                    $editId,
                                    $ownerCcId
                                );
                            }
                        } else {
                            $u = $conn->prepare('
                                UPDATE dispatch_delivery_customers SET
                                    customer_code = ?, wechat_id = ?, line_id = ?,
                                    latitude = NULL, longitude = NULL,
                                    route_primary = ?, route_secondary = ?, routes_combined = ?,
                                    community_name_en = ?, community_name_th = ?, customer_state = ?
                                WHERE id = ? AND consigning_client_id = ?
                            ');
                            if ($u) {
                                $u->bind_param(
                                    'ssssssssii',
                                    $customerCode,
                                    $wechat,
                                    $line,
                                    $rp,
                                    $rs,
                                    $routesCombined,
                                    $en,
                                    $th,
                                    $custState,
                                    $editId,
                                    $ownerCcId
                                );
                            }
                        }
                        if (!$u) {
                            $error = '保存失败';
                        } else {
                            try {
                                if (!$u->execute()) {
                                    $error = '保存失败';
                                }
                            } catch (mysqli_sql_exception $e) {
                                $error = '保存失败';
                            }
                            if ($error === '' && $this->columnExists($conn, 'dispatch_delivery_customers', 'phone')) {
                                $upPhone = $conn->prepare('UPDATE dispatch_delivery_customers SET phone = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                                if ($upPhone) {
                                    $upPhone->bind_param('sii', $phone, $editId, $ownerCcId);
                                    if (!$upPhone->execute()) {
                                        $error = '保存失败';
                                    }
                                    $upPhone->close();
                                }
                            }
                            if ($error === '' && $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_requirements')) {
                                $upReq = $conn->prepare('UPDATE dispatch_delivery_customers SET customer_requirements = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                                if ($upReq) {
                                    $upReq->bind_param('sii', $customerRequirement, $editId, $ownerCcId);
                                    if (!$upReq->execute()) {
                                        $error = '保存失败';
                                    }
                                    $upReq->close();
                                }
                            }
                            if ($error === '' && $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_house_no')) {
                                $compAddr = $this->deliveryComposedFullAddresses($addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode);
                                $fullTh = $compAddr['th'];
                                $fullEn = $compAddr['en'];
                                $geoStatusNorm = $this->normalizeDeliveryGeoStatus($rp, $lat, $lng, $geoStatusRaw);
                                $hasThGeoFk = $this->columnExists($conn, 'dispatch_delivery_customers', 'th_geo_subdistrict_id');
                                if ($hasThGeoFk) {
                                    if ($thGeoSubPost > 0 && is_array($thGeoChainResolved)) {
                                        $gPid = (int)$thGeoChainResolved['province_id'];
                                        $gDid = (int)$thGeoChainResolved['district_id'];
                                        $gSid = (int)$thGeoChainResolved['subdistrict_id'];
                                        $upAddr = $conn->prepare('UPDATE dispatch_delivery_customers SET addr_house_no = ?, addr_road_soi = ?, addr_moo_village = ?, addr_tambon = ?, addr_amphoe = ?, addr_province = ?, addr_zipcode = ?, addr_th_full = ?, addr_en_full = ?, geo_status = ?, th_geo_province_id = ?, th_geo_district_id = ?, th_geo_subdistrict_id = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                                        if ($upAddr) {
                                            $upAddr->bind_param('ssssssssssiiiii', $addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode, $fullTh, $fullEn, $geoStatusNorm, $gPid, $gDid, $gSid, $editId, $ownerCcId);
                                            if (!$upAddr->execute()) {
                                                $error = '保存失败';
                                            }
                                            $upAddr->close();
                                        }
                                    } else {
                                        $upAddr = $conn->prepare('UPDATE dispatch_delivery_customers SET addr_house_no = ?, addr_road_soi = ?, addr_moo_village = ?, addr_tambon = ?, addr_amphoe = ?, addr_province = ?, addr_zipcode = ?, addr_th_full = ?, addr_en_full = ?, geo_status = ?, th_geo_province_id = NULL, th_geo_district_id = NULL, th_geo_subdistrict_id = NULL WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                                        if ($upAddr) {
                                            $upAddr->bind_param('ssssssssssii', $addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode, $fullTh, $fullEn, $geoStatusNorm, $editId, $ownerCcId);
                                            if (!$upAddr->execute()) {
                                                $error = '保存失败';
                                            }
                                            $upAddr->close();
                                        }
                                    }
                                } else {
                                    $upAddr = $conn->prepare('UPDATE dispatch_delivery_customers SET addr_house_no = ?, addr_road_soi = ?, addr_moo_village = ?, addr_tambon = ?, addr_amphoe = ?, addr_province = ?, addr_zipcode = ?, addr_th_full = ?, addr_en_full = ?, geo_status = ? WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                                    if ($upAddr) {
                                        $upAddr->bind_param('ssssssssssii', $addrHouseNo, $addrRoadSoi, $addrMooVillage, $addrTambon, $addrAmphoe, $addrProvince, $addrZipcode, $fullTh, $fullEn, $geoStatusNorm, $editId, $ownerCcId);
                                        if (!$upAddr->execute()) {
                                            $error = '保存失败';
                                        }
                                        $upAddr->close();
                                    }
                                }
                            }
                            $newLat = $lat;
                            $newLng = $lng;
                            $changedAddress = trim($addrHouseNo) !== $oldH || trim($addrRoadSoi) !== $oldR || trim($addrMooVillage) !== $oldM
                                || trim($addrTambon) !== $oldT || trim($addrAmphoe) !== $oldA || trim($addrProvince) !== $oldP || trim($addrZipcode) !== $oldZ
                                || $newLat !== $oldLat || $newLng !== $oldLng;
                            $fwdSync = $changedAddress
                                || trim($wechat) !== $oldWechat
                                || trim($line) !== $oldLine
                                || trim($en) !== $oldEn
                                || trim($th) !== $oldTh
                                || ($this->columnExists($conn, 'dispatch_delivery_customers', 'phone') && trim($phone) !== $oldPhone)
                                || ($this->columnExists($conn, 'dispatch_delivery_customers', 'recipient_name')
                                    && isset($_POST['recipient_name'])
                                    && trim((string)($_POST['recipient_name'] ?? '')) !== $oldRecipient);
                            if ($error === '' && $changedAddress && $this->columnExists($conn, 'dispatch_delivery_customers', 'address_geo_updated_at')) {
                                $upMark = $conn->prepare('UPDATE dispatch_delivery_customers SET address_geo_updated_at = NOW() WHERE id = ? AND consigning_client_id = ? LIMIT 1');
                                if ($upMark) {
                                    $upMark->bind_param('ii', $editId, $ownerCcId);
                                    $upMark->execute();
                                    $upMark->close();
                                }
                            }
                            $u->close();
                        }
                        if ($error === '') {
                            $this->removeForwardCustomerAfterDeliveryRouteLeavesOt($conn, $oldCustomerCodeForForward, $oldRoutePrimary, $rp);
                            if (!empty($fwdSync)) {
                                $this->notifyForwardCustomerSyncFromDelivery($conn, $customerCode);
                            }
                            $redirQs = ['msg' => 'saved'];
                            if (!$hideConsigningSelectors) {
                                $redirQs['consigning_client_id'] = (string)(int)($_POST['dc_list_consigning_client_id'] ?? $filterCcId);
                            }
                            foreach (['q_customer_code', 'q_wechat', 'q_route_primary', 'q_customer_state', 'q_geo_status', 'q_addr_th', 'q_amphoe'] as $qk) {
                                $qv = trim((string)($_POST[$qk] ?? ''));
                                if ($qv !== '') {
                                    $redirQs[$qk] = $qv;
                                }
                            }
                            $redirPage = (int)($_POST['dc_list_page'] ?? 0);
                            if ($redirPage > 0) {
                                $redirQs['page'] = (string)$redirPage;
                            }
                            $redirPer = (int)($_POST['dc_list_per_page'] ?? 0);
                            if (in_array($redirPer, [20, 50, 100], true)) {
                                $redirQs['per_page'] = (string)$redirPer;
                            }
                            header('Location: /dispatch/delivery-customers?' . http_build_query($redirQs));
                            exit;
                        }
                    }
                }
            }
        }

        if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_delivery_csv'])) {
            $ccCsv = (int)($_POST['csv_consigning_client_id'] ?? 0);
            if ($dispatchBoundClientMissing) {
                $error = '当前账号绑定的委托客户不存在或已删除，无法导入。请联系管理员检查用户绑定。';
            } elseif ($hideConsigningSelectors && $ccCsv !== $boundCcId) {
                $error = '导入目标委托客户与当前账号绑定不一致';
            } elseif (!isset($_FILES['delivery_csv']) || !is_uploaded_file((string)($_FILES['delivery_csv']['tmp_name'] ?? ''))) {
                $error = '请选择 CSV 文件';
            } else {
                $fh = fopen((string)$_FILES['delivery_csv']['tmp_name'], 'rb');
                if (!$fh) {
                    $error = '无法读取上传文件';
                } else {
                    $headerRow = fgetcsv($fh);
                    $deliveryCsvHasConsigningColumn = false;
                    if (is_array($headerRow)) {
                        foreach ($headerRow as $hKey) {
                            $hk = trim((string)$hKey);
                            if (str_starts_with($hk, "\xEF\xBB\xBF")) {
                                $hk = trim(substr($hk, 3));
                            }
                            if ($this->canonicalDeliveryImportCsvField($hk) === 'consigning_client_code') {
                                $deliveryCsvHasConsigningColumn = true;
                                break;
                            }
                        }
                    }
                    if (!$deliveryCsvHasConsigningColumn && $ccCsv <= 0) {
                        fclose($fh);
                        $error = 'CSV 未包含「委托客户编码」列时，请先在上方选择委托客户后再导入；若需一次导入多个委托客户，请使用带「委托客户编码」列的模板。';
                    } else {
                    $ok = 0;
                    $upd = 0;
                    $fail = 0;
                    $failureLog = [];
                    $failureLogMax = 200;
                    $csvRowIndex = 1;
                    while (($row = fgetcsv($fh)) !== false) {
                        $csvRowIndex++;
                        if ($row === [null] || (count($row) === 1 && trim((string)($row[0] ?? '')) === '')) {
                            continue;
                        }
                        $map = [];
                        if (is_array($headerRow)) {
                            foreach ($headerRow as $i => $key) {
                                $k = trim((string)$key);
                                if (str_starts_with($k, "\xEF\xBB\xBF")) {
                                    $k = trim(substr($k, 3));
                                }
                                if ($k === '') {
                                    continue;
                                }
                                $canon = $this->canonicalDeliveryImportCsvField($k);
                                if ($canon === '') {
                                    continue;
                                }
                                $map[$canon] = trim((string)($row[$i] ?? ''));
                            }
                        }
                        $rowTargetCcId = 0;
                        if ($deliveryCsvHasConsigningColumn) {
                            $rowCcCode = trim((string)($map['consigning_client_code'] ?? ''));
                            if ($rowCcCode === '') {
                                $fail++;
                                if (count($failureLog) < $failureLogMax) {
                                    $failureLog[] = [
                                        'line' => $csvRowIndex,
                                        'reason' => '缺少委托客户编码（表头含该列时，每行均须填写）',
                                    ];
                                }
                                continue;
                            }
                            $rowTargetCcId = $this->consigningClientIdByCode($conn, $rowCcCode);
                            if ($rowTargetCcId <= 0) {
                                $fail++;
                                if (count($failureLog) < $failureLogMax) {
                                    $failureLog[] = [
                                        'line' => $csvRowIndex,
                                        'reason' => '委托客户编码「' . $rowCcCode . '」不存在或未启用',
                                    ];
                                }
                                continue;
                            }
                            if ($hideConsigningSelectors && $rowTargetCcId !== $boundCcId) {
                                $boundCode = '';
                                $br = $this->consigningClientRowById($conn, $boundCcId);
                                if (is_array($br)) {
                                    $boundCode = trim((string)($br['client_code'] ?? ''));
                                }
                                $fail++;
                                if (count($failureLog) < $failureLogMax) {
                                    $failureLog[] = [
                                        'line' => $csvRowIndex,
                                        'reason' => $boundCode !== ''
                                            ? ('当前为委托客户绑定账号，仅可导入「' . $boundCode . '」，与行内「' . $rowCcCode . '」不符')
                                            : '当前为委托客户绑定账号，仅可导入已绑定的委托客户',
                                    ];
                                }
                                continue;
                            }
                        } else {
                            $rowTargetCcId = $ccCsv;
                        }
                        $customerCode = (string)($map['customer_code'] ?? '');
                        if ($customerCode === '' || mb_strlen($customerCode) > 60) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => $customerCode === ''
                                        ? '缺少派送客户编号（表头须能识别为「派送客户编号」或与模板一致的别名）'
                                        : '派送客户编号超过 60 字',
                                ];
                            }
                            continue;
                        }
                        $wechat = (string)($map['wechat_id'] ?? '');
                        $line = (string)($map['line_id'] ?? '');
                        $recipientName = (string)($map['recipient_name'] ?? '');
                        $phone = (string)($map['phone'] ?? '');
                        $addrHouseNo = (string)($map['addr_house_no'] ?? '');
                        $addrRoadSoi = (string)($map['addr_road_soi'] ?? '');
                        $addrMooVillage = (string)($map['addr_moo_village'] ?? '');
                        $addrTambon = (string)($map['addr_tambon'] ?? '');
                        $addrAmphoe = (string)($map['addr_amphoe'] ?? '');
                        $addrProvince = (string)($map['addr_province'] ?? '');
                        $addrZipcode = (string)($map['addr_zipcode'] ?? '');
                        $geoStatusRaw = (string)($map['geo_status'] ?? '');
                        $geoRaw = (string)($map['geo_position'] ?? '');
                        $rp = (string)($map['route_primary'] ?? '');
                        $rs = (string)($map['route_secondary'] ?? '');
                        $en = (string)($map['community_name_en'] ?? '');
                        $th = (string)($map['community_name_th'] ?? '');
                        $customerRequirement = (string)($map['customer_requirements'] ?? '');
                        if (mb_strlen($wechat) > 120 || mb_strlen($line) > 120 || mb_strlen($recipientName) > 120 || mb_strlen($phone) > 40) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = ['line' => $csvRowIndex, 'reason' => '微信号/Line/收件人/电话字段长度超限（微信/Line/收件人≤120，电话≤40）'];
                            }
                            continue;
                        }
                        if (
                            mb_strlen($addrHouseNo) > 120 || mb_strlen($addrRoadSoi) > 160 || mb_strlen($addrMooVillage) > 160
                            || mb_strlen($addrTambon) > 160 || mb_strlen($addrAmphoe) > 160 || mb_strlen($addrProvince) > 160
                            || mb_strlen($addrZipcode) > 20
                        ) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = ['line' => $csvRowIndex, 'reason' => '泰国地址结构字段长度超限（门牌≤120，其余地址段≤160，Zipcode≤20）'];
                            }
                            continue;
                        }
                        if (mb_strlen($geoRaw) > 48) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = ['line' => $csvRowIndex, 'reason' => '定位字段超过 48 字'];
                            }
                            continue;
                        }
                        if (mb_strlen($rp) > 120 || mb_strlen($rs) > 120) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = ['line' => $csvRowIndex, 'reason' => '主路线或副路线超过 120 字'];
                            }
                            continue;
                        }
                        if (mb_strlen($en) > 160 || mb_strlen($th) > 160) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = ['line' => $csvRowIndex, 'reason' => '小区英文名或泰文名超过 160 字'];
                            }
                            continue;
                        }
                        if (mb_strlen($customerRequirement) > 5000) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = ['line' => $csvRowIndex, 'reason' => '客户要求超过 5000 字'];
                            }
                            continue;
                        }
                        $geoParsed = $this->parseDeliveryGeoPosition($geoRaw);
                        if (!$geoParsed['ok']) {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => $geoParsed['error'] !== '' ? $geoParsed['error'] : '定位格式无效',
                                ];
                            }
                            continue;
                        }
                        $custStateRaw = (string)($map['customer_state'] ?? '');
                        $res = $this->upsertDeliveryCustomerFromImport(
                            $conn,
                            $rowTargetCcId,
                            $customerCode,
                            $wechat,
                            $line,
                            $recipientName,
                            $phone,
                            $addrHouseNo,
                            $addrRoadSoi,
                            $addrMooVillage,
                            $addrTambon,
                            $addrAmphoe,
                            $addrProvince,
                            $addrZipcode,
                            $geoStatusRaw,
                            $geoParsed['lat'],
                            $geoParsed['lng'],
                            $rp,
                            $rs,
                            $en,
                            $th,
                            $custStateRaw,
                            $customerRequirement
                        );
                        if ($res['ok']) {
                            if (($res['action'] ?? '') === 'update') {
                                $upd++;
                            } else {
                                $ok++;
                            }
                        } else {
                            $fail++;
                            if (count($failureLog) < $failureLogMax) {
                                $err = trim((string)($res['error'] ?? ''));
                                $failureLog[] = [
                                    'line' => $csvRowIndex,
                                    'reason' => $err !== '' ? ('保存失败：' . $err) : '保存失败（未知原因）',
                                ];
                            }
                        }
                    }
                    fclose($fh);
                    $importFailureDetails = $failureLog;
                    $message = "该次派送客户导入完成：新增 {$ok} 条，覆盖更新 {$upd} 条，失败 {$fail} 条";
                    if ($fail > $failureLogMax) {
                        $message .= '（下方仅列出前 ' . (string)$failureLogMax . ' 条失败原因）';
                    }
                    if (!$deliveryCsvHasConsigningColumn) {
                        $filterCcId = $ccCsv;
                    }
                    }
                }
            }
        }

        $rows = [];
        $page = $this->resolvePage();
        $perPage = $this->resolvePerPage();
        $total = 0;
        $totalPages = 1;
        if (!$dispatchBoundClientMissing && !empty($consigningOptions)) {
            $wParts = [];
            $types = '';
            $bindList = [];
            if ($hideConsigningSelectors) {
                $wParts[] = 'd.consigning_client_id = ?';
                $types .= 'i';
                $bindList[] = $filterCcId;
            } elseif ($filterCcId > 0) {
                $wParts[] = 'd.consigning_client_id = ?';
                $types .= 'i';
                $bindList[] = $filterCcId;
            }
            if ($dqCustomerCode !== '') {
                $wParts[] = 'd.customer_code LIKE ?';
                $types .= 's';
                $bindList[] = '%' . $dqCustomerCode . '%';
            }
            if ($dqWechat !== '') {
                $wParts[] = 'd.wechat_id LIKE ?';
                $types .= 's';
                $bindList[] = '%' . $dqWechat . '%';
            }
            if ($dqRoutePrimary !== '') {
                $wParts[] = 'd.route_primary LIKE ?';
                $types .= 's';
                $bindList[] = '%' . $dqRoutePrimary . '%';
            }
            if ($deliveryCustomerSchemaV2 && $dqCustomerState !== '') {
                $wParts[] = 'd.customer_state = ?';
                $types .= 's';
                $bindList[] = $this->normalizeDeliveryCustomerState($dqCustomerState);
            }
            if ($deliveryCustomerHasGeoProfile && $dqGeoStatus !== '') {
                $wParts[] = 'd.geo_status = ?';
                $types .= 's';
                $bindList[] = $dqGeoStatus;
            }
            if ($deliveryCustomerHasGeoProfile && $dqAddrTh !== '') {
                $wParts[] = 'd.addr_th_full LIKE ?';
                $types .= 's';
                $bindList[] = '%' . $dqAddrTh . '%';
            }
            if ($deliveryCustomerHasGeoProfile && $dqAmphoe !== '') {
                $wParts[] = 'd.addr_amphoe LIKE ?';
                $types .= 's';
                $bindList[] = '%' . $dqAmphoe . '%';
            }
            if ($wParts === []) {
                $wParts[] = '1=1';
            }
            $whereSql = implode(' AND ', $wParts);
            $fromJoin = '
                FROM dispatch_delivery_customers d
                INNER JOIN dispatch_consigning_clients c ON c.id = d.consigning_client_id
            ';
            $sqlCount = 'SELECT COUNT(*) AS cnt ' . $fromJoin . ' WHERE ' . $whereSql;
            $stmtC = $conn->prepare($sqlCount);
            if ($stmtC) {
                if ($types !== '') {
                    $stmtC->bind_param($types, ...$bindList);
                }
                $stmtC->execute();
                $rc = $stmtC->get_result()->fetch_assoc();
                $stmtC->close();
                $total = (int)($rc['cnt'] ?? 0);
            }
            $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
            if ($totalPages < 1) {
                $totalPages = 1;
            }
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $sqlList = "
                SELECT d.*, c.client_code, c.client_name
                {$fromJoin}
                WHERE {$whereSql}
                ORDER BY c.client_code ASC, d.customer_code ASC
                LIMIT ? OFFSET ?
            ";
            $typesList = $types . 'ii';
            $bindListList = array_merge($bindList, [$perPage, $offset]);
            $stmt = $conn->prepare($sqlList);
            if ($stmt) {
                if ($typesList !== 'ii') {
                    $stmt->bind_param($typesList, ...$bindListList);
                } else {
                    $stmt->bind_param('ii', $perPage, $offset);
                }
                $stmt->execute();
                $q = $stmt->get_result();
                while ($q && ($row = $q->fetch_assoc())) {
                    $rows[] = $row;
                }
                $stmt->close();
            }
        }

        if (isset($_GET['msg']) && (string)$_GET['msg'] === 'saved' && $message === '') {
            $message = '已保存修改';
        }

        $deliveryCustomerHasThGeoMaster = $this->columnExists($conn, 'dispatch_delivery_customers', 'th_geo_subdistrict_id');
        $deliveryCustomerThGeoDataReady = false;
        $deliveryCustomerThGeoHint = '';
        if ($deliveryCustomerHasThGeoMaster) {
            if ($this->tableExists($conn, 'th_geo_provinces')) {
                $cntRes = $conn->query('SELECT COUNT(*) AS c FROM th_geo_provinces');
                $c = 0;
                if ($cntRes instanceof mysqli_result) {
                    $cr = $cntRes->fetch_assoc();
                    $c = (int)($cr['c'] ?? 0);
                    $cntRes->free();
                }
                $deliveryCustomerThGeoDataReady = $c > 0;
                if (!$deliveryCustomerThGeoDataReady) {
                    $deliveryCustomerThGeoHint = '泰国行政区主数据表暂无数据：请将 JSON 放入 `database/seeds/th_geo/` 后运行 `php database/scripts/seed_thailand_geography.php`，编辑弹窗即可使用府/县/镇联动下拉。';
                }
            } else {
                $deliveryCustomerThGeoHint = '客户表已有行政区外键列，但缺少 `th_geo_provinces` 等主数据表，请执行 `database/migrations/049_thailand_geography_master.sql` 并灌入种子。';
            }
        }

        $title = '派送业务 / 派送客户';
        $contentView = __DIR__ . '/../Views/dispatch/delivery_customers.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    /**
     * 泰国府列表（JSON）。需 dispatch.delivery_customers.view 或 dispatch.manage。
     */
    public function dispatchThGeoProvinces(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.delivery_customers.view', 'dispatch.manage'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => '无权限'], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureDispatchSchema($conn);
        if (!$this->tableExists($conn, 'th_geo_provinces')) {
            echo json_encode(['ok' => false, 'error' => '请先执行数据库迁移 049_thailand_geography_master.sql'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $rows = [];
        $res = $conn->query('SELECT id, name_th, name_en FROM th_geo_provinces ORDER BY name_en ASC, id ASC');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name_th' => (string)($row['name_th'] ?? ''),
                    'name_en' => (string)($row['name_en'] ?? ''),
                ];
            }
            $res->free();
        }
        echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 泰国县/区列表（JSON）。GET：province_id
     */
    public function dispatchThGeoDistricts(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.delivery_customers.view', 'dispatch.manage'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => '无权限'], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureDispatchSchema($conn);
        if (!$this->tableExists($conn, 'th_geo_districts')) {
            echo json_encode(['ok' => false, 'error' => '请先执行数据库迁移 049'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $pid = (int)($_GET['province_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['ok' => false, 'error' => '缺少 province_id'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $rows = [];
        $stmt = $conn->prepare('SELECT id, name_th, name_en FROM th_geo_districts WHERE province_id = ? ORDER BY name_en ASC, id ASC');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $q = $stmt->get_result();
        while ($q && ($row = $q->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name_th' => (string)($row['name_th'] ?? ''),
                'name_en' => (string)($row['name_en'] ?? ''),
            ];
        }
        $stmt->close();
        echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 泰国镇/乡 + 邮编（JSON）。GET：district_id
     */
    public function dispatchThGeoSubdistricts(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['dispatch.delivery_customers.view', 'dispatch.manage'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => '无权限'], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureDispatchSchema($conn);
        if (!$this->tableExists($conn, 'th_geo_subdistricts')) {
            echo json_encode(['ok' => false, 'error' => '请先执行数据库迁移 049'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $did = (int)($_GET['district_id'] ?? 0);
        if ($did <= 0) {
            echo json_encode(['ok' => false, 'error' => '缺少 district_id'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $rows = [];
        $stmt = $conn->prepare('SELECT id, zipcode, name_th, name_en FROM th_geo_subdistricts WHERE district_id = ? ORDER BY name_en ASC, id ASC');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $stmt->bind_param('i', $did);
        $stmt->execute();
        $q = $stmt->get_result();
        while ($q && ($row = $q->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'zipcode' => (string)($row['zipcode'] ?? ''),
                'name_th' => (string)($row['name_th'] ?? ''),
                'name_en' => (string)($row['name_en'] ?? ''),
            ];
        }
        $stmt->close();
        echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{
     *   province_id:int,
     *   district_id:int,
     *   subdistrict_id:int,
     *   tambon_th:string,
     *   amphoe_th:string,
     *   province_th:string,
     *   zipcode:string
     * }|null
     */
    private function resolveThGeoChainFromSubdistrictId(mysqli $conn, int $subdistrictId): ?array
    {
        if ($subdistrictId <= 0 || !$this->tableExists($conn, 'th_geo_subdistricts')) {
            return null;
        }
        $sql = '
            SELECT sd.id AS sub_id, sd.name_th AS tambon_th, sd.zipcode AS zipcode,
                   dd.id AS district_id, dd.name_th AS amphoe_th,
                   pp.id AS province_id, pp.name_th AS province_th
            FROM th_geo_subdistricts sd
            INNER JOIN th_geo_districts dd ON dd.id = sd.district_id
            INNER JOIN th_geo_provinces pp ON pp.id = dd.province_id
            WHERE sd.id = ?
            LIMIT 1
        ';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $subdistrictId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)) {
            return null;
        }
        return [
            'province_id' => (int)($row['province_id'] ?? 0),
            'district_id' => (int)($row['district_id'] ?? 0),
            'subdistrict_id' => (int)($row['sub_id'] ?? 0),
            'tambon_th' => (string)($row['tambon_th'] ?? ''),
            'amphoe_th' => (string)($row['amphoe_th'] ?? ''),
            'province_th' => (string)($row['province_th'] ?? ''),
            'zipcode' => (string)($row['zipcode'] ?? ''),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function dispatchInboundCustomerRows(mysqli $conn): array
    {
        $rows = [];
        $hasCustomerState = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state');
        $stateFilter = $hasCustomerState ? "AND COALESCE(dc.customer_state, '正常') = '正常'" : '';
        $hasAddrTh = $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_th_full');
        $hasAddrEn = $this->columnExists($conn, 'dispatch_delivery_customers', 'addr_en_full');
        $selTh = $hasAddrTh ? 'dc.addr_th_full' : "'' AS addr_th_full";
        $selEn = $hasAddrEn ? 'dc.addr_en_full' : "'' AS addr_en_full";
        $grpExtra = array_values(array_filter([
            $hasAddrTh ? 'dc.addr_th_full' : '',
            $hasAddrEn ? 'dc.addr_en_full' : '',
        ]));
        $grpTail = $grpExtra !== [] ? ",\n                " . implode(",\n                ", $grpExtra) : '';
        $sql = "
            SELECT
                dc.id,
                dc.consigning_client_id,
                dc.customer_code,
                dc.wechat_id,
                dc.line_id,
                dc.community_name_th,
                {$selTh},
                {$selEn},
                dc.route_primary,
                dc.route_secondary,
                dc.latitude,
                dc.longitude,
                COUNT(w.id) AS inbound_count
            FROM dispatch_delivery_customers dc
            LEFT JOIN dispatch_waybills w
                ON w.consigning_client_id = dc.consigning_client_id
               AND COALESCE(w.order_status, '') = '已入库'
               AND (
                    w.delivery_customer_id = dc.id
                    OR (
                        w.delivery_customer_id IS NULL
                        AND TRIM(COALESCE(w.delivery_customer_code, '')) = TRIM(dc.customer_code)
                    )
               )
            WHERE dc.status = 1
              {$stateFilter}
            GROUP BY
                dc.id,
                dc.consigning_client_id,
                dc.customer_code,
                dc.wechat_id,
                dc.line_id,
                dc.community_name_th{$grpTail},
                dc.route_primary,
                dc.route_secondary,
                dc.latitude,
                dc.longitude
            HAVING COUNT(w.id) > 0
            ORDER BY dc.route_primary ASC, dc.route_secondary ASC, dc.customer_code ASC
        ";
        $res = $conn->query($sql);
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        return $rows;
    }

    public function opsDeliveryList(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问派送列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = true;
        $error = '';
        $rows = [];
        try {
            $this->ensureDispatchSchema($conn);
            $this->ensureDispatchOrderV2($conn);
            $rows = $this->dispatchInboundCustomerRows($conn);
        } catch (Throwable $e) {
            $schemaReady = false;
            $error = $e->getMessage();
        }

        $title = '派送业务 / 派送操作 / 派送列表';
        $contentView = __DIR__ . '/../Views/dispatch/ops_delivery_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function opsBindingList(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问绑带列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = true;
        $error = '';
        $message = '';
        $rows = [];
        try {
            $this->ensureDispatchSchema($conn);
            $this->ensureDispatchOrderV2($conn);

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'complete_binding') {
                $deliveryId = (int)($_POST['delivery_customer_id'] ?? 0);
                if ($deliveryId <= 0) {
                    $error = '参数无效';
                } else {
                    $st = $conn->prepare('
                        SELECT id, consigning_client_id, customer_code
                        FROM dispatch_delivery_customers
                        WHERE id = ? AND status = 1
                        LIMIT 1
                    ');
                    $customer = null;
                    if ($st) {
                        $st->bind_param('i', $deliveryId);
                        $st->execute();
                        $customer = $st->get_result()->fetch_assoc() ?: null;
                        $st->close();
                    }
                    if (!$customer) {
                        $error = '客户不存在或已停用';
                    } else {
                        $ccId = (int)($customer['consigning_client_id'] ?? 0);
                        $code = trim((string)($customer['customer_code'] ?? ''));
                        $toStatus = '已出库';
                        $fromStatus = '已入库';
                        $up = $conn->prepare("
                            UPDATE dispatch_waybills
                            SET order_status = ?, delivered_at = NOW()
                            WHERE consigning_client_id = ?
                              AND COALESCE(order_status, '') = ?
                              AND (
                                  delivery_customer_id = ?
                                  OR (delivery_customer_id IS NULL AND TRIM(COALESCE(delivery_customer_code, '')) = ?)
                              )
                        ");
                        if ($up) {
                            $up->bind_param('sisis', $toStatus, $ccId, $fromStatus, $deliveryId, $code);
                            $up->execute();
                            $affected = (int)$up->affected_rows;
                            $up->close();
                            $message = $affected > 0 ? ('已完成，更新 ' . $affected . ' 件') : '该客户当前无可完成的已入库货件';
                        } else {
                            $error = '操作失败';
                        }
                    }
                }
            }

            $rows = $this->dispatchInboundCustomerRows($conn);
        } catch (Throwable $e) {
            $schemaReady = false;
            $error = $e->getMessage();
        }

        $title = '派送业务 / 派送操作 / 绑带列表';
        $contentView = __DIR__ . '/../Views/dispatch/ops_binding_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function opsCreateDelivery(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问生成派送单');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = true;
        $error = '';
        $message = '';
        $rows = [];
        $selectedLine = trim((string)($_POST['dispatch_line'] ?? $_GET['dispatch_line'] ?? 'A'));
        $selectedDate = trim((string)($_POST['planned_delivery_date'] ?? $_GET['planned_delivery_date'] ?? date('Y-m-d')));
        $generatedDocNo = trim((string)($_POST['delivery_doc_no'] ?? $_GET['delivery_doc_no'] ?? ''));

        try {
            $this->ensureDispatchSchema($conn);
            $this->ensureDispatchOrderV2($conn);
            if (
                !$this->columnExists($conn, 'dispatch_waybills', 'delivery_doc_no')
                || !$this->columnExists($conn, 'dispatch_waybills', 'dispatch_line')
                || !$this->columnExists($conn, 'dispatch_waybills', 'planned_delivery_date')
            ) {
                throw new RuntimeException('派送单字段未建立，请先执行 migration：041_dispatch_waybill_delivery_doc_fields.sql');
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'create_delivery_doc') {
                $line = strtoupper(trim((string)($_POST['dispatch_line'] ?? '')));
                $dateRaw = trim((string)($_POST['planned_delivery_date'] ?? ''));
                $selectedIdsRaw = $_POST['delivery_customer_ids'] ?? [];
                if (!in_array($line, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $error = '派送线仅支持 A/B/C/D/E';
                } else {
                    $dt = DateTime::createFromFormat('Y-m-d', $dateRaw);
                    if (!$dt || $dt->format('Y-m-d') !== $dateRaw) {
                        $error = '预计派送日期格式无效';
                    } else {
                        $docNo = $dt->format('Ymd') . '-' . $line;
                        $selectedIds = [];
                        if (is_array($selectedIdsRaw)) {
                            foreach ($selectedIdsRaw as $v) {
                                $id = (int)$v;
                                if ($id > 0) {
                                    $selectedIds[$id] = $id;
                                }
                            }
                        }
                        $selectedIds = array_values($selectedIds);
                        if ($selectedIds === []) {
                            $error = '请先勾选要绑定派送单号的客户';
                        } else {
                            $totalAffected = 0;
                            $toStatus = '已出库';
                            $fromStatus = '已入库';
                            foreach ($selectedIds as $deliveryId) {
                                $st = $conn->prepare('
                                    SELECT id, consigning_client_id, customer_code
                                    FROM dispatch_delivery_customers
                                    WHERE id = ? AND status = 1
                                    LIMIT 1
                                ');
                                $customer = null;
                                if ($st) {
                                    $st->bind_param('i', $deliveryId);
                                    $st->execute();
                                    $customer = $st->get_result()->fetch_assoc() ?: null;
                                    $st->close();
                                }
                                if (!$customer) {
                                    continue;
                                }
                                $ccId = (int)($customer['consigning_client_id'] ?? 0);
                                $code = trim((string)($customer['customer_code'] ?? ''));
                                $up = $conn->prepare("
                                    UPDATE dispatch_waybills
                                    SET order_status = ?, delivery_doc_no = ?, dispatch_line = ?, planned_delivery_date = ?, delivered_at = NOW()
                                    WHERE consigning_client_id = ?
                                      AND COALESCE(order_status, '') = ?
                                      AND (
                                          delivery_customer_id = ?
                                          OR (delivery_customer_id IS NULL AND TRIM(COALESCE(delivery_customer_code, '')) = ?)
                                      )
                                ");
                                if ($up) {
                                    $up->bind_param('ssssisis', $toStatus, $docNo, $line, $dateRaw, $ccId, $fromStatus, $deliveryId, $code);
                                    $up->execute();
                                    $totalAffected += (int)$up->affected_rows;
                                    $up->close();
                                }
                            }
                            $generatedDocNo = $docNo;
                            $selectedLine = $line;
                            $selectedDate = $dateRaw;
                            $this->initializeDeliveryDocStops($conn, $docNo);
                            $message = '派送单已生成：' . $docNo . '；绑定货件 ' . $totalAffected . ' 件';
                        }
                    }
                }
            }

            $rows = $this->dispatchInboundCustomerRows($conn);
        } catch (Throwable $e) {
            $schemaReady = false;
            $error = $e->getMessage();
        }

        $title = '派送业务 / 派送操作 / 生成派送单';
        $contentView = __DIR__ . '/../Views/dispatch/ops_create_delivery.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function opsDeliveryDocs(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问派送单列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = true;
        $error = '';
        $message = '';
        $rows = [];
        $detailRows = [];
        $viewDocNo = trim((string)($_GET['delivery_doc_no'] ?? ''));
        $qDocNo = trim((string)($_GET['q_delivery_doc_no'] ?? ''));
        $qLine = strtoupper(trim((string)($_GET['q_dispatch_line'] ?? '')));
        $qDate = trim((string)($_GET['q_planned_delivery_date'] ?? ''));
        $driverRunTokensForView = [];
        $stopsFinalState = 0;
        $hasStopsTable = false;

        try {
            $this->ensureDispatchSchema($conn);
            $this->ensureDispatchOrderV2($conn);
            if (
                !$this->columnExists($conn, 'dispatch_waybills', 'delivery_doc_no')
                || !$this->columnExists($conn, 'dispatch_waybills', 'dispatch_line')
                || !$this->columnExists($conn, 'dispatch_waybills', 'planned_delivery_date')
            ) {
                throw new RuntimeException('派送单字段未建立，请先执行 migration：041_dispatch_waybill_delivery_doc_fields.sql');
            }
            $hasStopsTable = $this->tableExists($conn, 'dispatch_delivery_doc_stops');

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = trim((string)($_POST['action'] ?? ''));
                $docPost = trim((string)($_POST['delivery_doc_no'] ?? ''));
                if ($docPost !== '') {
                    if ($action === 'optimize_delivery_doc_stops') {
                        try {
                            $changed = $this->optimizeDeliveryDocStopsDraft($conn, $docPost);
                            $message = $changed > 0 ? ('优化完成，已更新排序（共 ' . $changed . ' 站）。') : '优化完成，当前顺序无需调整。';
                            $viewDocNo = $docPost;
                        } catch (Throwable $e) {
                            $error = $e->getMessage();
                            $viewDocNo = $docPost;
                        }
                    } elseif ($action === 'finalize_delivery_doc_stops') {
                        try {
                            $this->publishDeliveryDocStopsFinal($conn, $docPost);
                            $message = '已发布为最终派送单，司机端将使用当前优化顺序。';
                            $viewDocNo = $docPost;
                        } catch (Throwable $e) {
                            $error = $e->getMessage();
                            $viewDocNo = $docPost;
                        }
                    } elseif ($action === 'create_driver_run_tokens') {
                        try {
                            $this->driverRunCreateTokensForDoc($conn, $docPost);
                            $message = '已重新生成司机端链接（每段最多 ' . (string)self::DRIVER_SEGMENT_CUSTOMER_COUNT . ' 位客户，有效期 7 天，旧链接作废）。';
                            $viewDocNo = $docPost;
                        } catch (Throwable $e) {
                            $error = $e->getMessage();
                            $viewDocNo = $docPost;
                        }
                    }
                }
            }

            $where = ["TRIM(COALESCE(w.delivery_doc_no, '')) <> ''"];
            $types = '';
            $params = [];
            if ($qDocNo !== '') {
                $where[] = 'w.delivery_doc_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qDocNo . '%';
            }
            if (in_array($qLine, ['A', 'B', 'C', 'D', 'E'], true)) {
                $where[] = 'w.dispatch_line = ?';
                $types .= 's';
                $params[] = $qLine;
            }
            if ($qDate !== '') {
                $where[] = 'w.planned_delivery_date = ?';
                $types .= 's';
                $params[] = $qDate;
            }
            $whereSql = implode(' AND ', $where);
            $sql = "
                SELECT
                    w.delivery_doc_no,
                    COALESCE(NULLIF(w.dispatch_line, ''), SUBSTRING_INDEX(w.delivery_doc_no, '-', -1)) AS dispatch_line,
                    w.planned_delivery_date,
                    COUNT(*) AS piece_count,
                    COUNT(DISTINCT CONCAT(w.consigning_client_id, '#', COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)))) AS customer_count,
                    MIN(w.created_at) AS created_at
                FROM dispatch_waybills w
                LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
                WHERE {$whereSql}
                GROUP BY w.delivery_doc_no, COALESCE(NULLIF(w.dispatch_line, ''), SUBSTRING_INDEX(w.delivery_doc_no, '-', -1)), w.planned_delivery_date
                ORDER BY w.planned_delivery_date DESC, w.delivery_doc_no DESC
            ";
            $st = $conn->prepare($sql);
            if ($st) {
                if ($types !== '') {
                    $st->bind_param($types, ...$params);
                }
                $st->execute();
                $res = $st->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $rows[] = $r;
                }
                $st->close();
            }

            if ($viewDocNo !== '') {
                $stopsRows = $this->loadDeliveryDocStops($conn, $viewDocNo);
                if ($stopsRows !== []) {
                    $detailRows = $stopsRows;
                    $stopsFinalState = (int)($stopsRows[0]['is_final'] ?? 0);
                } else {
                    $dt = $conn->prepare("
                        SELECT
                            COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)) AS customer_code,
                            COALESCE(NULLIF(TRIM(dc.wechat_id), ''), NULLIF(TRIM(dc.line_id), ''), '') AS wx_or_line,
                            COALESCE(NULLIF(TRIM(dc.route_primary), ''), '') AS route_primary,
                            COALESCE(NULLIF(TRIM(dc.route_secondary), ''), '') AS route_secondary,
                            COUNT(*) AS piece_count,
                            0 AS stop_order,
                            0 AS segment_index,
                            0 AS is_final
                        FROM dispatch_waybills w
                        LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
                        WHERE w.delivery_doc_no = ?
                        GROUP BY
                            COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)),
                            COALESCE(NULLIF(TRIM(dc.wechat_id), ''), NULLIF(TRIM(dc.line_id), ''), ''),
                            COALESCE(NULLIF(TRIM(dc.route_primary), ''), ''),
                            COALESCE(NULLIF(TRIM(dc.route_secondary), ''), '')
                        ORDER BY route_primary ASC, route_secondary ASC, customer_code ASC
                    ");
                    if ($dt) {
                        $dt->bind_param('s', $viewDocNo);
                        $dt->execute();
                        $dr = $dt->get_result();
                        while ($dr && ($r = $dr->fetch_assoc())) {
                            $detailRows[] = $r;
                        }
                        $dt->close();
                    }
                }
                if ($detailRows === []) {
                    $error = '未找到该派送单号明细';
                    $viewDocNo = '';
                } elseif ($this->tableExists($conn, 'dispatch_driver_run_tokens')) {
                    $stT = $conn->prepare('SELECT token, segment_index, expires_at FROM dispatch_driver_run_tokens WHERE delivery_doc_no = ? ORDER BY segment_index ASC');
                    if ($stT) {
                        $stT->bind_param('s', $viewDocNo);
                        $stT->execute();
                        $rt = $stT->get_result();
                        while ($rt && ($tr = $rt->fetch_assoc())) {
                            $driverRunTokensForView[] = $tr;
                        }
                        $stT->close();
                    }
                }
            }
        } catch (Throwable $e) {
            $schemaReady = false;
            $error = $e->getMessage();
        }

        $title = '派送业务 / 派送操作 / 派送单列表';
        $contentView = __DIR__ . '/../Views/dispatch/ops_delivery_docs.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    private const DRIVER_SEGMENT_CUSTOMER_COUNT = 6;

    private function deliveryPodStorageDir(): string
    {
        return __DIR__ . '/../../storage/dispatch/delivery-pod';
    }

    private function ensureDeliveryPodStorageDir(): void
    {
        $dir = $this->deliveryPodStorageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function deliveryDocCustomersWithGeo(mysqli $conn, string $deliveryDocNo): array
    {
        $doc = trim($deliveryDocNo);
        if ($doc === '') {
            return [];
        }
        if ($this->tableExists($conn, 'dispatch_delivery_doc_stops')) {
            $stops = $this->loadDeliveryDocStops($conn, $doc);
            if ($stops !== []) {
                return $stops;
            }
        }
        $rows = [];
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)) AS customer_code,
                COALESCE(NULLIF(TRIM(dc.wechat_id), ''), NULLIF(TRIM(dc.line_id), ''), '') AS wx_or_line,
                COALESCE(NULLIF(TRIM(dc.route_primary), ''), '') AS route_primary,
                COALESCE(NULLIF(TRIM(dc.route_secondary), ''), '') AS route_secondary,
                MAX(COALESCE(NULLIF(TRIM(dc.community_name_th), ''), '')) AS community_name_th,
                MAX(COALESCE(NULLIF(TRIM(dc.addr_th_full), ''), '')) AS addr_th_full,
                MAX(COALESCE(NULLIF(TRIM(dc.addr_en_full), ''), '')) AS addr_en_full,
                MAX(dc.latitude) AS latitude,
                MAX(dc.longitude) AS longitude,
                COUNT(*) AS piece_count
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE w.delivery_doc_no = ?
            GROUP BY
                COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)),
                COALESCE(NULLIF(TRIM(dc.wechat_id), ''), NULLIF(TRIM(dc.line_id), ''), ''),
                COALESCE(NULLIF(TRIM(dc.route_primary), ''), ''),
                COALESCE(NULLIF(TRIM(dc.route_secondary), ''), '')
            ORDER BY route_primary ASC, route_secondary ASC, customer_code ASC
        ";
        $st = $conn->prepare($sql);
        if ($st) {
            $st->bind_param('s', $doc);
            $st->execute();
            $res = $st->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $rows[] = $r;
            }
            $st->close();
        }
        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadDeliveryDocStops(mysqli $conn, string $deliveryDocNo): array
    {
        if (!$this->tableExists($conn, 'dispatch_delivery_doc_stops')) {
            return [];
        }
        $doc = trim($deliveryDocNo);
        if ($doc === '') {
            return [];
        }
        $rows = [];
        $st = $conn->prepare("
            SELECT
                customer_code,
                wx_or_line,
                route_primary,
                route_secondary,
                community_name_th,
                addr_th_full,
                addr_en_full,
                latitude,
                longitude,
                piece_count,
                stop_order,
                segment_index,
                is_final
            FROM dispatch_delivery_doc_stops
            WHERE delivery_doc_no = ?
            ORDER BY stop_order ASC, customer_code ASC
        ");
        if ($st) {
            $st->bind_param('s', $doc);
            $st->execute();
            $res = $st->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $rows[] = $r;
            }
            $st->close();
        }
        return $rows;
    }

    private function initializeDeliveryDocStops(mysqli $conn, string $deliveryDocNo): void
    {
        if (!$this->tableExists($conn, 'dispatch_delivery_doc_stops')) {
            throw new RuntimeException('请先执行 migration：044_dispatch_delivery_doc_stops.sql');
        }
        $doc = trim($deliveryDocNo);
        if ($doc === '') {
            return;
        }
        $rows = $this->deliveryDocCustomersWithGeo($conn, $doc);
        if ($rows === []) {
            return;
        }
        $del = $conn->prepare('DELETE FROM dispatch_delivery_doc_stops WHERE delivery_doc_no = ?');
        if ($del) {
            $del->bind_param('s', $doc);
            $del->execute();
            $del->close();
        }
        $ins = $conn->prepare("
            INSERT INTO dispatch_delivery_doc_stops (
                delivery_doc_no, customer_code, wx_or_line, route_primary, route_secondary,
                community_name_th, addr_th_full, addr_en_full, latitude, longitude,
                piece_count, stop_order, segment_index, is_final
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        if (!$ins) {
            return;
        }
        foreach ($rows as $idx => $r) {
            $code = trim((string)($r['customer_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $wx = trim((string)($r['wx_or_line'] ?? ''));
            $rp = trim((string)($r['route_primary'] ?? ''));
            $rs = trim((string)($r['route_secondary'] ?? ''));
            $th = trim((string)($r['community_name_th'] ?? ''));
            $addrTh = trim((string)($r['addr_th_full'] ?? ''));
            $addrEn = trim((string)($r['addr_en_full'] ?? ''));
            $latRaw = $r['latitude'] ?? null;
            $lngRaw = $r['longitude'] ?? null;
            $lat = ($latRaw !== null && $latRaw !== '') ? (float)$latRaw : null;
            $lng = ($lngRaw !== null && $lngRaw !== '') ? (float)$lngRaw : null;
            $piece = (int)($r['piece_count'] ?? 0);
            $stopOrder = (int)$idx + 1;
            $segment = (int)floor($idx / self::DRIVER_SEGMENT_CUSTOMER_COUNT);
            $ins->bind_param(
                'ssssssssddiii',
                $doc,
                $code,
                $wx,
                $rp,
                $rs,
                $th,
                $addrTh,
                $addrEn,
                $lat,
                $lng,
                $piece,
                $stopOrder,
                $segment
            );
            $ins->execute();
        }
        $ins->close();
    }

    private function optimizeDeliveryDocStopsDraft(mysqli $conn, string $deliveryDocNo): int
    {
        $rows = $this->loadDeliveryDocStops($conn, $deliveryDocNo);
        if ($rows === []) {
            throw new RuntimeException('该派送单没有可优化的停靠点，请先生成派送单。');
        }
        $withGeo = [];
        $withoutGeo = [];
        foreach ($rows as $r) {
            $latRaw = $r['latitude'] ?? null;
            $lngRaw = $r['longitude'] ?? null;
            $lat = ($latRaw !== null && $latRaw !== '') ? (float)$latRaw : null;
            $lng = ($lngRaw !== null && $lngRaw !== '') ? (float)$lngRaw : null;
            if ($lat !== null && $lng !== null && abs($lat) <= 90.0 && abs($lng) <= 180.0) {
                $r['latitude'] = $lat;
                $r['longitude'] = $lng;
                $withGeo[] = $r;
            } else {
                $withoutGeo[] = $r;
            }
        }
        $ordered = [];
        if ($withGeo !== []) {
            usort($withGeo, static function (array $a, array $b): int {
                $a1 = (string)($a['route_primary'] ?? '');
                $b1 = (string)($b['route_primary'] ?? '');
                if ($a1 !== $b1) {
                    return strcmp($a1, $b1);
                }
                return strcmp((string)($a['customer_code'] ?? ''), (string)($b['customer_code'] ?? ''));
            });
            $current = array_shift($withGeo);
            if (is_array($current)) {
                $ordered[] = $current;
            }
            while ($withGeo !== [] && is_array($current)) {
                $bestIdx = 0;
                $bestDist = PHP_FLOAT_MAX;
                $cLat = (float)$current['latitude'];
                $cLng = (float)$current['longitude'];
                foreach ($withGeo as $idx => $cand) {
                    $dLat = ((float)$cand['latitude']) - $cLat;
                    $dLng = ((float)$cand['longitude']) - $cLng;
                    $dist = ($dLat * $dLat) + ($dLng * $dLng);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestIdx = (int)$idx;
                    }
                }
                $current = $withGeo[$bestIdx];
                array_splice($withGeo, $bestIdx, 1);
                $ordered[] = $current;
            }
        }
        foreach ($withoutGeo as $r) {
            $ordered[] = $r;
        }
        $up = $conn->prepare('UPDATE dispatch_delivery_doc_stops SET stop_order = ?, segment_index = ?, is_final = 0 WHERE delivery_doc_no = ? AND customer_code = ?');
        if (!$up) {
            return 0;
        }
        $changed = 0;
        foreach ($ordered as $idx => $r) {
            $orderNo = (int)$idx + 1;
            $segment = (int)floor($idx / self::DRIVER_SEGMENT_CUSTOMER_COUNT);
            $doc = trim((string)$deliveryDocNo);
            $code = trim((string)($r['customer_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $up->bind_param('iiss', $orderNo, $segment, $doc, $code);
            $up->execute();
            $changed++;
        }
        $up->close();
        return $changed;
    }

    private function publishDeliveryDocStopsFinal(mysqli $conn, string $deliveryDocNo): void
    {
        if (!$this->tableExists($conn, 'dispatch_delivery_doc_stops')) {
            throw new RuntimeException('请先执行 migration：044_dispatch_delivery_doc_stops.sql');
        }
        $doc = trim($deliveryDocNo);
        if ($doc === '') {
            throw new RuntimeException('派送单号无效');
        }
        $st = $conn->prepare('UPDATE dispatch_delivery_doc_stops SET is_final = 1 WHERE delivery_doc_no = ?');
        if ($st) {
            $st->bind_param('s', $doc);
            $st->execute();
            $st->close();
        }
    }

    /**
     * @param list<array<string,mixed>> $segmentRows
     */
    private function buildGoogleMapsDirUrlForSegment(array $segmentRows): ?string
    {
        $encParts = [];
        foreach ($segmentRows as $r) {
            $latRaw = $r['latitude'] ?? null;
            $lngRaw = $r['longitude'] ?? null;
            $lat = ($latRaw !== null && $latRaw !== '') ? (float)$latRaw : null;
            $lng = ($lngRaw !== null && $lngRaw !== '') ? (float)$lngRaw : null;
            if ($lat !== null && $lng !== null && abs($lat) <= 90.0 && abs($lng) <= 180.0) {
                $encParts[] = rawurlencode(rtrim(rtrim(sprintf('%.7f', $lat), '0'), '.') . ',' . rtrim(rtrim(sprintf('%.7f', $lng), '0'), '.'));
                continue;
            }
            $addr = trim(implode(' ', array_filter([
                (string)($r['community_name_th'] ?? ''),
                trim((string)($r['addr_en_full'] ?? '')) !== ''
                    ? (string)($r['addr_en_full'] ?? '')
                    : (string)($r['addr_th_full'] ?? ''),
                'Thailand',
            ], static fn ($v) => $v !== '')));
            if ($addr !== '') {
                $encParts[] = rawurlencode($addr);
            }
        }
        if ($encParts === []) {
            return null;
        }
        $n = count($encParts);
        if ($n === 1) {
            return 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' . $encParts[0];
        }
        $wps = implode('|', array_slice($encParts, 0, $n - 1));
        $dest = $encParts[$n - 1];
        return 'https://www.google.com/maps/dir/?api=1&travelmode=driving&waypoints=' . $wps . '&destination=' . $dest;
    }

    /** @return array<string,mixed>|null */
    private function driverRunResolveToken(mysqli $conn, string $token): ?array
    {
        $t = trim($token);
        if ($t === '' || !$this->tableExists($conn, 'dispatch_driver_run_tokens')) {
            return null;
        }
        $row = null;
        $st = $conn->prepare('SELECT id, token, delivery_doc_no, segment_index, expires_at FROM dispatch_driver_run_tokens WHERE token = ? LIMIT 1');
        if ($st) {
            $st->bind_param('s', $t);
            $st->execute();
            $row = $st->get_result()->fetch_assoc() ?: null;
            $st->close();
        }
        if (!is_array($row)) {
            return null;
        }
        $exp = $row['expires_at'] ?? null;
        if ($exp !== null && (string)$exp !== '') {
            $ts = strtotime((string)$exp);
            if ($ts !== false && $ts < time()) {
                return null;
            }
        }
        return $row;
    }

    private function driverRunCreateTokensForDoc(mysqli $conn, string $deliveryDocNo): void
    {
        if (!$this->tableExists($conn, 'dispatch_driver_run_tokens')) {
            throw new RuntimeException('请先执行 migration：043_dispatch_driver_pod_and_tokens.sql');
        }
        $doc = trim($deliveryDocNo);
        if ($doc === '') {
            throw new RuntimeException('派送单号无效');
        }
        if ($this->tableExists($conn, 'dispatch_delivery_doc_stops')) {
            $finalFlag = 0;
            $ff = $conn->prepare('SELECT MAX(is_final) AS f FROM dispatch_delivery_doc_stops WHERE delivery_doc_no = ?');
            if ($ff) {
                $ff->bind_param('s', $doc);
                $ff->execute();
                $row = $ff->get_result()->fetch_assoc() ?: null;
                $finalFlag = (int)($row['f'] ?? 0);
                $ff->close();
            }
            if ($finalFlag !== 1) {
                throw new RuntimeException('请先点击“发布为最终派送单”，再生成司机端链接。');
            }
        }
        $customers = $this->deliveryDocCustomersWithGeo($conn, $doc);
        if ($customers === []) {
            throw new RuntimeException('该派送单下没有客户明细，无法生成链接');
        }
        $del = $conn->prepare('DELETE FROM dispatch_driver_run_tokens WHERE delivery_doc_no = ?');
        if ($del) {
            $del->bind_param('s', $doc);
            $del->execute();
            $del->close();
        }
        $chunks = array_chunk($customers, self::DRIVER_SEGMENT_CUSTOMER_COUNT);
        $expAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $ins = $conn->prepare('INSERT INTO dispatch_driver_run_tokens (token, delivery_doc_no, segment_index, expires_at) VALUES (?, ?, ?, ?)');
        if (!$ins) {
            throw new RuntimeException('无法写入司机链接表');
        }
        foreach ($chunks as $idx => $_chunk) {
            $token = bin2hex(random_bytes(32));
            $seg = (int)$idx;
            $ins->bind_param('ssis', $token, $doc, $seg, $expAt);
            $ins->execute();
        }
        $ins->close();
    }

    /**
     * @return array{ok:bool,path:?string,error:string}
     */
    private function deliveryPodSaveUploadedImage(?array $file, string $filenameBase): array
    {
        if ($file === null || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'path' => null, 'error' => 'missing file'];
        }
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => null, 'error' => 'upload err'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'path' => null, 'error' => 'bad tmp'];
        }
        if ((int)($file['size'] ?? 0) > 6 * 1024 * 1024) {
            return ['ok' => false, 'path' => null, 'error' => 'too large'];
        }
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)($finfo->file($tmp) ?: '');
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmp);
        }
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $ext = $extMap[$mime] ?? '';
        if ($ext === '') {
            return ['ok' => false, 'path' => null, 'error' => 'bad mime'];
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase) ?: 'pod';
        $name = $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $this->ensureDeliveryPodStorageDir();
        $dest = $this->deliveryPodStorageDir() . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'path' => null, 'error' => 'move fail'];
        }
        if ($ext === 'jpg' && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($dest);
            if ($img !== false) {
                $w = imagesx($img);
                $h = imagesy($img);
                $maxSide = 1600;
                if ($w > 0 && $h > 0 && ($w > $maxSide || $h > $maxSide)) {
                    $scale = min($maxSide / $w, $maxSide / $h);
                    $nw = max(1, (int)round($w * $scale));
                    $nh = max(1, (int)round($h * $scale));
                    $dst = imagecreatetruecolor($nw, $nh);
                    if ($dst !== false) {
                        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        imagejpeg($dst, $dest, 82);
                        imagedestroy($dst);
                    }
                }
                imagedestroy($img);
            }
        }
        return ['ok' => true, 'path' => $name, 'error' => ''];
    }

    /**
     * 司机端停靠清单补全：电话、门牌号、路（巷）（来自当前派送单下运单关联的派送客户）。
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function enrichDriverRunStopsWithDeliveryContact(mysqli $conn, string $docNo, array $rows): array
    {
        if ($rows === [] || trim($docNo) === '' || !$this->tableExists($conn, 'dispatch_waybills')) {
            return $rows;
        }
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code)) AS cust_code,
                MAX(COALESCE(NULLIF(TRIM(dc.phone), ''), '')) AS phone,
                MAX(COALESCE(NULLIF(TRIM(dc.addr_house_no), ''), '')) AS addr_house_no,
                MAX(COALESCE(NULLIF(TRIM(dc.addr_road_soi), ''), '')) AS addr_road_soi
            FROM dispatch_waybills w
            LEFT JOIN dispatch_delivery_customers dc ON dc.id = w.delivery_customer_id
            WHERE w.delivery_doc_no = ?
              AND (
                  TRIM(COALESCE(dc.customer_code, '')) <> ''
                  OR TRIM(COALESCE(w.delivery_customer_code, '')) <> ''
              )
            GROUP BY COALESCE(NULLIF(TRIM(dc.customer_code), ''), TRIM(w.delivery_customer_code))
        ";
        $st = $conn->prepare($sql);
        if (!$st) {
            return $rows;
        }
        $st->bind_param('s', $docNo);
        $st->execute();
        $res = $st->get_result();
        $map = [];
        while ($res && ($m = $res->fetch_assoc())) {
            $k = trim((string)($m['cust_code'] ?? ''));
            if ($k !== '') {
                $map[$k] = $m;
            }
        }
        $st->close();
        foreach ($rows as $i => $r) {
            $k = trim((string)($r['customer_code'] ?? ''));
            if ($k === '' || !isset($map[$k])) {
                continue;
            }
            $rows[$i]['phone'] = (string)($map[$k]['phone'] ?? '');
            $rows[$i]['addr_house_no'] = (string)($map[$k]['addr_house_no'] ?? '');
            $rows[$i]['addr_road_soi'] = (string)($map[$k]['addr_road_soi'] ?? '');
        }
        return $rows;
    }

    /** 司机端（免登录 token，界面文案当前为中文），单页：清单 + Google 地图 + 每客户两张签收照 */
    public function opsDriverRun(): void
    {
        $conn = require __DIR__ . '/../../config/database.php';
        $token = trim((string)($_GET['t'] ?? ''));
        $flash = trim((string)($_GET['msg'] ?? ''));
        $driverError = '';
        $tokenRow = null;
        $segmentCustomers = [];
        $mapsUrl = null;
        $docMeta = null;
        $segmentTotal = 0;
        $segmentIndex = 0;
        $podDoneCodes = [];
        try {
            $this->ensureDispatchSchema($conn);
            if (!$this->columnExists($conn, 'dispatch_waybills', 'delivery_doc_no')) {
                throw new RuntimeException('派送单字段未建立');
            }
            $tokenRow = $this->driverRunResolveToken($conn, $token);
            if ($tokenRow === null) {
                $driverError = 'invalid';
            } else {
                $docNo = (string)$tokenRow['delivery_doc_no'];
                $segmentIndex = (int)($tokenRow['segment_index'] ?? 0);
                $all = $this->deliveryDocCustomersWithGeo($conn, $docNo);
                $chunks = array_chunk($all, self::DRIVER_SEGMENT_CUSTOMER_COUNT);
                $segmentTotal = count($chunks);
                $segmentCustomers = $chunks[$segmentIndex] ?? [];
                $segmentCustomers = $this->enrichDriverRunStopsWithDeliveryContact($conn, $docNo, $segmentCustomers);
                if ($segmentCustomers === []) {
                    $driverError = 'empty';
                } else {
                    $mapsUrl = $this->buildGoogleMapsDirUrlForSegment($segmentCustomers);
                }
                $dm = $conn->prepare('SELECT dispatch_line, planned_delivery_date FROM dispatch_waybills WHERE delivery_doc_no = ? LIMIT 1');
                if ($dm) {
                    $dm->bind_param('s', $docNo);
                    $dm->execute();
                    $docMeta = $dm->get_result()->fetch_assoc() ?: null;
                    $dm->close();
                }
                if ($this->tableExists($conn, 'dispatch_delivery_pod')) {
                    $codes = array_map(static fn ($r) => (string)($r['customer_code'] ?? ''), $segmentCustomers);
                    $codes = array_values(array_filter($codes, static fn ($c) => $c !== ''));
                    if ($codes !== []) {
                        $placeholders = implode(',', array_fill(0, count($codes), '?'));
                        $types = str_repeat('s', count($codes) + 1);
                        $bind = array_merge([$docNo], $codes);
                        $qs = "SELECT customer_code FROM dispatch_delivery_pod WHERE delivery_doc_no = ? AND customer_code IN ({$placeholders})";
                        $pq = $conn->prepare($qs);
                        if ($pq) {
                            $pq->bind_param($types, ...$bind);
                            $pq->execute();
                            $gr = $pq->get_result();
                            while ($gr && ($pr = $gr->fetch_assoc())) {
                                $podDoneCodes[(string)($pr['customer_code'] ?? '')] = true;
                            }
                            $pq->close();
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $driverError = 'sys:' . $e->getMessage();
        }
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../Views/dispatch/driver_run_standalone.php';
    }

    public function opsDriverPodUpload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /dispatch/driver/run', true, 302);
            exit;
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $token = trim((string)($_POST['t'] ?? ''));
        $customerCode = trim((string)($_POST['customer_code'] ?? ''));
        $file1 = isset($_FILES['photo_1']) && is_array($_FILES['photo_1']) ? $_FILES['photo_1'] : null;
        $file2 = isset($_FILES['photo_2']) && is_array($_FILES['photo_2']) ? $_FILES['photo_2'] : null;
        if ($token === '' || $customerCode === '' || !$this->tableExists($conn, 'dispatch_delivery_pod')) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }
        $tokenRow = $this->driverRunResolveToken($conn, $token);
        if ($tokenRow === null) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        $docNo = (string)$tokenRow['delivery_doc_no'];
        $segmentIndex = (int)($tokenRow['segment_index'] ?? 0);
        $all = $this->deliveryDocCustomersWithGeo($conn, $docNo);
        $chunks = array_chunk($all, self::DRIVER_SEGMENT_CUSTOMER_COUNT);
        $segmentCustomers = $chunks[$segmentIndex] ?? [];
        $allowed = false;
        foreach ($segmentCustomers as $r) {
            if (trim((string)($r['customer_code'] ?? '')) === $customerCode) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        $base = 'pod_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $docNo) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $customerCode);
        $s1 = $this->deliveryPodSaveUploadedImage($file1, $base . '_1');
        $s2 = $this->deliveryPodSaveUploadedImage($file2, $base . '_2');
        if (!$s1['ok'] || !$s2['ok'] || $s1['path'] === null || $s2['path'] === null) {
            http_response_code(400);
            echo 'Photos invalid or missing';
            exit;
        }
        $p1 = (string)$s1['path'];
        $p2 = (string)$s2['path'];
        $sql = 'INSERT INTO dispatch_delivery_pod (delivery_doc_no, customer_code, photo_1, photo_2) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE photo_1 = VALUES(photo_1), photo_2 = VALUES(photo_2), created_at = CURRENT_TIMESTAMP';
        $st = $conn->prepare($sql);
        if (!$st) {
            http_response_code(500);
            echo 'DB error';
            exit;
        }
        $st->bind_param('ssss', $docNo, $customerCode, $p1, $p2);
        $st->execute();
        $st->close();

        // 上传签收后：该客户在本派送单下所有“已出库”订单同步改为“已派送”
        $toStatus = '已派送';
        $fromStatus = '已出库';
        $up = $conn->prepare("
            UPDATE dispatch_waybills
            SET order_status = ?, delivered_at = NOW()
            WHERE delivery_doc_no = ?
              AND COALESCE(order_status, '') = ?
              AND (
                    TRIM(COALESCE(delivery_customer_code, '')) = ?
                    OR EXISTS (
                        SELECT 1
                        FROM dispatch_delivery_customers dc
                        WHERE dc.id = dispatch_waybills.delivery_customer_id
                          AND TRIM(COALESCE(dc.customer_code, '')) = ?
                    )
              )
        ");
        if ($up) {
            $up->bind_param('sssss', $toStatus, $docNo, $fromStatus, $customerCode, $customerCode);
            $up->execute();
            $up->close();
        }

        $redir = '/dispatch/driver/run?t=' . rawurlencode($token) . '&msg=saved';
        header('Location: ' . $redir, true, 302);
        exit;
    }

    /** 旧「面单列表」路径，已合并至派送首页 */
    public function waybills(): void
    {
        $q = (string)($_SERVER['QUERY_STRING'] ?? '');
        $target = '/dispatch' . ($q !== '' ? '?' . $q : '');
        header('Location: ' . $target, true, 302);
        exit;
    }
}
