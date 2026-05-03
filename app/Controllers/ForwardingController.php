<?php
require_once __DIR__ . '/Concerns/AuditLogTrait.php';

class ForwardingController
{
    use AuditLogTrait;

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
        require_once __DIR__ . '/../Config/MenuPermissionCatalog.php';
        $navKeys = array_merge(
            MenuPermissionCatalog::dispatchHubMenuNavKeys(),
            MenuPermissionCatalog::udaMenuNavKeys(),
            ['menu.nav.warehouse.root']
        );
        if (!$this->hasAnyPermission(array_merge(['menu.dispatch', 'menu.dashboard'], $navKeys))) {
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

    private function ensureForwardingSchema(mysqli $conn): bool
    {
        $required = [
            'dispatch_forward_customers',
            'dispatch_forward_packages',
            'dispatch_forward_package_items',
            'dispatch_waybills',
        ];
        foreach ($required as $t) {
            if (!$this->tableExists($conn, $t)) {
                return false;
            }
        }
        return true;
    }

    /** 与 OT 自动同步抑制表、手动删除逻辑共用（TRIM + 大写） */
    private function normalizeForwardCustomerCodeKey(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * 派送客户表中是否存在主路线为 OT、且客户编码与给定键一致（已 normalize）的记录。
     */
    private function forwardCustomerCodeHasActiveOtRoute(mysqli $conn, string $normalizedCode): bool
    {
        if ($normalizedCode === '' || !$this->tableExists($conn, 'dispatch_delivery_customers')) {
            return false;
        }
        $st = $conn->prepare("
            SELECT 1
            FROM dispatch_delivery_customers
            WHERE UPPER(TRIM(COALESCE(route_primary, ''))) = 'OT'
              AND UPPER(TRIM(COALESCE(customer_code, ''))) = ?
            LIMIT 1
        ");
        if (!$st) {
            return false;
        }
        $st->bind_param('s', $normalizedCode);
        $st->execute();
        $ok = (bool)$st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    private function rememberForwardOtAutoSyncSuppress(mysqli $conn, string $normalizedCode): void
    {
        if ($normalizedCode === '' || !$this->tableExists($conn, 'dispatch_forward_customer_ot_sync_suppress')) {
            return;
        }
        $ins = $conn->prepare('INSERT IGNORE INTO dispatch_forward_customer_ot_sync_suppress (customer_code) VALUES (?)');
        if ($ins) {
            $ins->bind_param('s', $normalizedCode);
            $ins->execute();
            $ins->close();
        }
    }

    private function clearForwardOtAutoSyncSuppress(mysqli $conn, string $normalizedCode): void
    {
        if ($normalizedCode === '' || !$this->tableExists($conn, 'dispatch_forward_customer_ot_sync_suppress')) {
            return;
        }
        $del = $conn->prepare('DELETE FROM dispatch_forward_customer_ot_sync_suppress WHERE customer_code = ?');
        if ($del) {
            $del->bind_param('s', $normalizedCode);
            $del->execute();
            $del->close();
        }
    }

    private function ensureForwardingCustomerSyncSchema(mysqli $conn): bool
    {
        if (!$this->tableExists($conn, 'dispatch_delivery_customers') || !$this->tableExists($conn, 'dispatch_forward_customers')) {
            return false;
        }
        $required = [
            ['dispatch_delivery_customers', 'recipient_name'],
            ['dispatch_forward_customers', 'wechat_line'],
            ['dispatch_forward_customers', 'recipient_name'],
            ['dispatch_forward_customers', 'addr_th_full'],
            ['dispatch_forward_customers', 'sync_mark'],
            ['dispatch_forward_customers', 'source_signature'],
            ['dispatch_forward_customers', 'source_updated_at'],
            ['dispatch_forward_customers', 'auto_pushed_once'],
            ['dispatch_forward_customers', 'manual_pushed_at'],
        ];
        foreach ($required as [$table, $column]) {
            if (!$this->columnExists($conn, $table, $column)) {
                return false;
            }
        }
        return true;
    }

    private function writeAudit(mysqli $conn, string $actionKey, ?string $targetType = null, ?int $targetId = null, array $detail = []): void
    {
        $this->writeStandardAuditLog($conn, 'dispatch', $actionKey, $targetType, $targetId, $detail);
    }

    private function forwardVoucherStorageDir(): string
    {
        return __DIR__ . '/../../storage/dispatch/forwarding-vouchers';
    }

    private function ensureForwardVoucherStorageDir(): void
    {
        $dir = $this->forwardVoucherStorageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{ok:bool,path:?string,error:string}
     */
    private function forwardVoucherSaveFromUpload(?array $file, string $filenameBase): array
    {
        if ($file === null || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'path' => null, 'error' => '请上传凭证图片'];
        }
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => null, 'error' => '凭证上传失败'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'path' => null, 'error' => '凭证上传失败'];
        }
        if ((int)($file['size'] ?? 0) <= 0) {
            return ['ok' => false, 'path' => null, 'error' => '凭证文件为空'];
        }
        if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'path' => null, 'error' => '凭证图片不能超过 5MB'];
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
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $extMap[$mime] ?? '';
        if ($ext === '') {
            return ['ok' => false, 'path' => null, 'error' => '凭证仅支持 JPG、PNG、GIF、WEBP'];
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase) ?: 'fwd';
        $name = $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $this->ensureForwardVoucherStorageDir();
        $dest = $this->forwardVoucherStorageDir() . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'path' => null, 'error' => '凭证保存失败'];
        }
        return ['ok' => true, 'path' => $name, 'error' => ''];
    }

    /** @return array{full:string,mime:string}|null */
    private function forwardVoucherResolveStoredFile(?string $storedName): ?array
    {
        $base = basename((string)$storedName);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $base)) {
            return null;
        }
        $full = $this->forwardVoucherStorageDir() . DIRECTORY_SEPARATOR . $base;
        if (!is_file($full)) {
            return null;
        }
        $ext = strtolower((string)pathinfo($base, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        return ['full' => $full, 'mime' => ($mimeMap[$ext] ?? 'application/octet-stream')];
    }

    /** @return array<string, mixed>|null */
    private function sourceDeliveryCustomerByCode(mysqli $conn, string $customerCode): ?array
    {
        $code = trim($customerCode);
        if ($code === '') {
            return null;
        }
        $hasRecipient = $this->columnExists($conn, 'dispatch_delivery_customers', 'recipient_name');
        $hasPhone = $this->columnExists($conn, 'dispatch_delivery_customers', 'phone');
        $recipientExpr = $hasRecipient ? 'COALESCE(NULLIF(TRIM(recipient_name), \'\'), \'\') AS recipient_name' : "'' AS recipient_name";
        $phoneExpr = $hasPhone ? 'COALESCE(NULLIF(TRIM(phone), \'\'), \'\') AS phone' : "'' AS phone";
        $sql = "
            SELECT
                customer_code,
                COALESCE(NULLIF(TRIM(wechat_id), ''), '') AS wechat_id,
                COALESCE(NULLIF(TRIM(line_id), ''), '') AS line_id,
                {$recipientExpr},
                {$phoneExpr},
                COALESCE(NULLIF(TRIM(addr_th_full), ''), '') AS addr_th_full,
                COALESCE(NULLIF(TRIM(addr_en_full), ''), '') AS addr_en_full,
                COALESCE(NULLIF(TRIM(community_name_th), ''), '') AS community_name_th,
                COALESCE(NULLIF(TRIM(route_primary), ''), '') AS route_primary,
                updated_at
            FROM dispatch_delivery_customers
            WHERE customer_code = ?
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? $row : null;
    }

    /**
     * 按派送客户主键读取一行（含委托客户名称），用于转发合包下拉与提交校验。
     *
     * @return array<string, mixed>|null
     */
    private function sourceDeliveryCustomerRowById(mysqli $conn, int $deliveryCustomerId): ?array
    {
        if ($deliveryCustomerId <= 0) {
            return null;
        }
        if (!$this->tableExists($conn, 'dispatch_delivery_customers') || !$this->tableExists($conn, 'dispatch_consigning_clients')) {
            return null;
        }
        $hasRecipient = $this->columnExists($conn, 'dispatch_delivery_customers', 'recipient_name');
        $hasPhone = $this->columnExists($conn, 'dispatch_delivery_customers', 'phone');
        $recipientExpr = $hasRecipient ? 'COALESCE(NULLIF(TRIM(dc.recipient_name), \'\'), \'\') AS recipient_name' : "'' AS recipient_name";
        $phoneExpr = $hasPhone ? 'COALESCE(NULLIF(TRIM(dc.phone), \'\'), \'\') AS phone' : "'' AS phone";
        $sql = "
            SELECT
                dc.id AS delivery_customer_id,
                COALESCE(NULLIF(TRIM(cc.client_name), ''), '') AS consigning_client_name,
                dc.customer_code,
                COALESCE(NULLIF(TRIM(dc.wechat_id), ''), '') AS wechat_id,
                COALESCE(NULLIF(TRIM(dc.line_id), ''), '') AS line_id,
                {$recipientExpr},
                {$phoneExpr},
                COALESCE(NULLIF(TRIM(dc.addr_th_full), ''), '') AS addr_th_full,
                COALESCE(NULLIF(TRIM(dc.addr_en_full), ''), '') AS addr_en_full,
                COALESCE(NULLIF(TRIM(dc.community_name_th), ''), '') AS community_name_th,
                COALESCE(NULLIF(TRIM(dc.route_primary), ''), '') AS route_primary,
                dc.updated_at
            FROM dispatch_delivery_customers dc
            INNER JOIN dispatch_consigning_clients cc ON cc.id = dc.consigning_client_id
            WHERE dc.id = ? AND dc.status = 1 AND cc.status = 1
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $deliveryCustomerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    private function forwardPackageDeliveryCustomerSelectOptions(mysqli $conn): array
    {
        if (!$this->tableExists($conn, 'dispatch_delivery_customers') || !$this->tableExists($conn, 'dispatch_consigning_clients')) {
            return [];
        }
        $hasRecipient = $this->columnExists($conn, 'dispatch_delivery_customers', 'recipient_name');
        $hasPhone = $this->columnExists($conn, 'dispatch_delivery_customers', 'phone');
        $recipientExpr = $hasRecipient ? 'COALESCE(NULLIF(TRIM(dc.recipient_name), \'\'), \'\') AS recipient_name' : "'' AS recipient_name";
        $phoneExpr = $hasPhone ? 'COALESCE(NULLIF(TRIM(dc.phone), \'\'), \'\') AS phone' : "'' AS phone";
        $sql = "
            SELECT
                dc.id AS delivery_customer_id,
                COALESCE(NULLIF(TRIM(cc.client_name), ''), '') AS consigning_client_name,
                dc.customer_code,
                COALESCE(NULLIF(TRIM(dc.wechat_id), ''), '') AS wechat_id,
                COALESCE(NULLIF(TRIM(dc.line_id), ''), '') AS line_id,
                {$recipientExpr},
                {$phoneExpr},
                COALESCE(NULLIF(TRIM(dc.addr_th_full), ''), '') AS addr_th_full,
                COALESCE(NULLIF(TRIM(dc.addr_en_full), ''), '') AS addr_en_full,
                COALESCE(NULLIF(TRIM(dc.community_name_th), ''), '') AS community_name_th
            FROM dispatch_delivery_customers dc
            INNER JOIN dispatch_consigning_clients cc ON cc.id = dc.consigning_client_id
            WHERE dc.status = 1 AND cc.status = 1
            ORDER BY cc.client_name ASC, dc.customer_code ASC
            LIMIT 3000
        ";
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            return [];
        }
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $payload = $this->buildForwardPayloadFromSource($row);
            $row['opt_recipient'] = $payload['recipient_name'];
            $row['phone'] = $payload['phone'];
            $row['addr_th_full'] = $payload['addr_th_full'];
            $row['wechat_line'] = $payload['wechat_line'];
            $out[] = $row;
        }
        $res->free();
        return $out;
    }

    /** @return array<string, string> */
    private function buildForwardPayloadFromSource(array $src): array
    {
        $wechat = trim((string)($src['wechat_id'] ?? ''));
        $line = trim((string)($src['line_id'] ?? ''));
        $recipient = trim((string)($src['recipient_name'] ?? ''));
        if ($recipient === '') {
            $recipient = $wechat !== '' ? $wechat : $line;
        }
        $addrThFull = trim((string)($src['addr_th_full'] ?? ''));
        return [
            'customer_code' => trim((string)($src['customer_code'] ?? '')),
            'wechat_line' => trim($wechat . ($wechat !== '' && $line !== '' ? ' / ' : '') . $line),
            'recipient_name' => $recipient,
            'phone' => trim((string)($src['phone'] ?? '')),
            'addr_th_full' => $addrThFull,
        ];
    }

    private function payloadSignature(array $payload): string
    {
        return hash(
            'sha256',
            trim((string)($payload['customer_code'] ?? '')) . '|' .
            trim((string)($payload['wechat_line'] ?? '')) . '|' .
            trim((string)($payload['recipient_name'] ?? '')) . '|' .
            trim((string)($payload['phone'] ?? '')) . '|' .
            trim((string)($payload['addr_th_full'] ?? ''))
        );
    }

    /**
     * 派送端保存/导入后：若转发库已有同客户编码记录，则覆盖同步微信/Line、收件人、电话、完整泰文地址（不新增转发行）。
     */
    public function syncForwardCustomerFromDeliveryIfExists(mysqli $conn, string $customerCode): void
    {
        if (!$this->tableExists($conn, 'dispatch_forward_customers')) {
            return;
        }
        if (!$this->columnExists($conn, 'dispatch_forward_customers', 'addr_th_full')) {
            return;
        }
        $code = trim($customerCode);
        if ($code === '') {
            return;
        }
        $st = $conn->prepare('SELECT id FROM dispatch_forward_customers WHERE customer_code = ? LIMIT 1');
        if (!$st) {
            return;
        }
        $st->bind_param('s', $code);
        $st->execute();
        $hit = $st->get_result()->fetch_assoc();
        $st->close();
        if (!is_array($hit) || (int)($hit['id'] ?? 0) <= 0) {
            return;
        }
        $id = (int)$hit['id'];
        $src = $this->sourceDeliveryCustomerByCode($conn, $code);
        if (!is_array($src)) {
            return;
        }
        $payload = $this->buildForwardPayloadFromSource($src);
        $signature = $this->payloadSignature($payload);
        $updatedAt = trim((string)($src['updated_at'] ?? ''));
        $sourceUpdatedAt = $updatedAt !== '' ? $updatedAt : null;
        $mark = 'modified';
        if ($sourceUpdatedAt === null) {
            $up = $conn->prepare('
                UPDATE dispatch_forward_customers
                SET wechat_line = ?, recipient_name = ?, phone = ?, addr_th_full = ?,
                    source_signature = ?, source_updated_at = NULL, sync_mark = ?
                WHERE id = ?
                LIMIT 1
            ');
            if (!$up) {
                return;
            }
            $up->bind_param(
                'ssssssi',
                $payload['wechat_line'],
                $payload['recipient_name'],
                $payload['phone'],
                $payload['addr_th_full'],
                $signature,
                $mark,
                $id
            );
        } else {
            $up = $conn->prepare('
                UPDATE dispatch_forward_customers
                SET wechat_line = ?, recipient_name = ?, phone = ?, addr_th_full = ?,
                    source_signature = ?, source_updated_at = ?, sync_mark = ?
                WHERE id = ?
                LIMIT 1
            ');
            if (!$up) {
                return;
            }
            $up->bind_param(
                'sssssssi',
                $payload['wechat_line'],
                $payload['recipient_name'],
                $payload['phone'],
                $payload['addr_th_full'],
                $signature,
                $sourceUpdatedAt,
                $mark,
                $id
            );
        }
        try {
            $up->execute();
        } catch (Throwable $e) {
            // ignore
        }
        $up->close();
    }

    /**
     * @return array{action:string,id:int}
     */
    private function pushForwardCustomerByCode(mysqli $conn, string $customerCode, bool $isManual): array
    {
        if ($isManual) {
            $normManual = $this->normalizeForwardCustomerCodeKey($customerCode);
            if ($normManual !== '') {
                $this->clearForwardOtAutoSyncSuppress($conn, $normManual);
            }
        }

        $src = $this->sourceDeliveryCustomerByCode($conn, $customerCode);
        if (!$src) {
            return ['action' => 'not_found', 'id' => 0];
        }
        $payload = $this->buildForwardPayloadFromSource($src);
        $signature = $this->payloadSignature($payload);
        $updatedAt = trim((string)($src['updated_at'] ?? ''));
        $sourceUpdatedAt = $updatedAt !== '' ? $updatedAt : null;
        $manualPushedAt = $isManual ? date('Y-m-d H:i:s') : null;
        $isOt = mb_strtoupper(trim((string)($src['route_primary'] ?? '')), 'UTF-8') === 'OT';

        $stmt = $conn->prepare('SELECT id, source_signature, auto_pushed_once FROM dispatch_forward_customers WHERE customer_code = ? LIMIT 1');
        if (!$stmt) {
            return ['action' => 'error', 'id' => 0];
        }
        $stmt->bind_param('s', $payload['customer_code']);
        $stmt->execute();
        $exist = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exist) {
            $newMark = ($isOt || $isManual) ? 'new' : '';
            $autoOnce = $isOt ? 1 : 0;
            $customerNameFallback = trim((string)($payload['recipient_name'] ?? ''));
            if ($customerNameFallback === '') {
                $customerNameFallback = trim((string)($payload['customer_code'] ?? ''));
            }
            $ins = null;
            if ($sourceUpdatedAt !== null && $manualPushedAt !== null) {
                $ins = $conn->prepare('
                    INSERT INTO dispatch_forward_customers (
                        customer_code, customer_name, wechat_line, recipient_name, phone, addr_th_full, status,
                        sync_mark, source_signature, source_updated_at, auto_pushed_once, manual_pushed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
                ');
                if ($ins) {
                    $ins->bind_param(
                        'sssssssssis',
                        $payload['customer_code'],
                        $customerNameFallback,
                        $payload['wechat_line'],
                        $payload['recipient_name'],
                        $payload['phone'],
                        $payload['addr_th_full'],
                        $newMark,
                        $signature,
                        $sourceUpdatedAt,
                        $autoOnce,
                        $manualPushedAt
                    );
                }
            } elseif ($sourceUpdatedAt !== null) {
                $ins = $conn->prepare('
                    INSERT INTO dispatch_forward_customers (
                        customer_code, customer_name, wechat_line, recipient_name, phone, addr_th_full, status,
                        sync_mark, source_signature, source_updated_at, auto_pushed_once, manual_pushed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NULL)
                ');
                if ($ins) {
                    $ins->bind_param(
                        'sssssssssi',
                        $payload['customer_code'],
                        $customerNameFallback,
                        $payload['wechat_line'],
                        $payload['recipient_name'],
                        $payload['phone'],
                        $payload['addr_th_full'],
                        $newMark,
                        $signature,
                        $sourceUpdatedAt,
                        $autoOnce
                    );
                }
            } elseif ($manualPushedAt !== null) {
                $ins = $conn->prepare('
                    INSERT INTO dispatch_forward_customers (
                        customer_code, customer_name, wechat_line, recipient_name, phone, addr_th_full, status,
                        sync_mark, source_signature, source_updated_at, auto_pushed_once, manual_pushed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NULL, ?, ?)
                ');
                if ($ins) {
                    $ins->bind_param(
                        'ssssssssis',
                        $payload['customer_code'],
                        $customerNameFallback,
                        $payload['wechat_line'],
                        $payload['recipient_name'],
                        $payload['phone'],
                        $payload['addr_th_full'],
                        $newMark,
                        $signature,
                        $autoOnce,
                        $manualPushedAt
                    );
                }
            } else {
                $ins = $conn->prepare('
                    INSERT INTO dispatch_forward_customers (
                        customer_code, customer_name, wechat_line, recipient_name, phone, addr_th_full, status,
                        sync_mark, source_signature, source_updated_at, auto_pushed_once, manual_pushed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NULL, ?, NULL)
                ');
                if ($ins) {
                    $ins->bind_param(
                        'sssssssssi',
                        $payload['customer_code'],
                        $customerNameFallback,
                        $payload['wechat_line'],
                        $payload['recipient_name'],
                        $payload['phone'],
                        $payload['addr_th_full'],
                        $newMark,
                        $signature,
                        $autoOnce
                    );
                }
            }
            if (!$ins) {
                return ['action' => 'error', 'id' => 0];
            }
            try {
                $ins->execute();
            } catch (Throwable $e) {
                $ins->close();
                return ['action' => 'error', 'id' => 0];
            }
            $newId = (int)$ins->insert_id;
            $ins->close();
            return ['action' => 'inserted', 'id' => $newId];
        }

        $id = (int)($exist['id'] ?? 0);
        $oldSignature = trim((string)($exist['source_signature'] ?? ''));
        $autoOnce = (int)($exist['auto_pushed_once'] ?? 0);

        if ($oldSignature === $signature && !$isManual) {
            if ($isOt && $autoOnce === 0) {
                $upOnce = $conn->prepare('UPDATE dispatch_forward_customers SET auto_pushed_once = 1 WHERE id = ?');
                if ($upOnce) {
                    $upOnce->bind_param('i', $id);
                    $upOnce->execute();
                    $upOnce->close();
                }
            }
            return ['action' => 'unchanged', 'id' => $id];
        }

        $newMark = $isManual ? '' : 'modified';
        $newAutoOnce = ($isOt || $autoOnce === 1) ? 1 : 0;
        $up = null;
        if ($sourceUpdatedAt !== null && $manualPushedAt !== null) {
            $up = $conn->prepare('
                UPDATE dispatch_forward_customers
                SET wechat_line = ?, recipient_name = ?, phone = ?, addr_th_full = ?,
                    sync_mark = ?, source_signature = ?, source_updated_at = ?, auto_pushed_once = ?, manual_pushed_at = ?
                WHERE id = ?
            ');
            if ($up) {
                $up->bind_param(
                    'sssssssisi',
                    $payload['wechat_line'],
                    $payload['recipient_name'],
                    $payload['phone'],
                    $payload['addr_th_full'],
                    $newMark,
                    $signature,
                    $sourceUpdatedAt,
                    $newAutoOnce,
                    $manualPushedAt,
                    $id
                );
            }
        } elseif ($sourceUpdatedAt !== null) {
            $up = $conn->prepare('
                UPDATE dispatch_forward_customers
                SET wechat_line = ?, recipient_name = ?, phone = ?, addr_th_full = ?,
                    sync_mark = ?, source_signature = ?, source_updated_at = ?, auto_pushed_once = ?, manual_pushed_at = NULL
                WHERE id = ?
            ');
            if ($up) {
                $up->bind_param(
                    'sssssssii',
                    $payload['wechat_line'],
                    $payload['recipient_name'],
                    $payload['phone'],
                    $payload['addr_th_full'],
                    $newMark,
                    $signature,
                    $sourceUpdatedAt,
                    $newAutoOnce,
                    $id
                );
            }
        } elseif ($manualPushedAt !== null) {
            $up = $conn->prepare('
                UPDATE dispatch_forward_customers
                SET wechat_line = ?, recipient_name = ?, phone = ?, addr_th_full = ?,
                    sync_mark = ?, source_signature = ?, source_updated_at = NULL, auto_pushed_once = ?, manual_pushed_at = ?
                WHERE id = ?
            ');
            if ($up) {
                $up->bind_param(
                    'ssssssisi',
                    $payload['wechat_line'],
                    $payload['recipient_name'],
                    $payload['phone'],
                    $payload['addr_th_full'],
                    $newMark,
                    $signature,
                    $newAutoOnce,
                    $manualPushedAt,
                    $id
                );
            }
        } else {
            $up = $conn->prepare('
                UPDATE dispatch_forward_customers
                SET wechat_line = ?, recipient_name = ?, phone = ?, addr_th_full = ?,
                    sync_mark = ?, source_signature = ?, source_updated_at = NULL, auto_pushed_once = ?, manual_pushed_at = NULL
                WHERE id = ?
            ');
            if ($up) {
                $up->bind_param(
                    'sssssssii',
                    $payload['wechat_line'],
                    $payload['recipient_name'],
                    $payload['phone'],
                    $payload['addr_th_full'],
                    $newMark,
                    $signature,
                    $newAutoOnce,
                    $id
                );
            }
        }
        if (!$up) {
            return ['action' => 'error', 'id' => $id];
        }
        try {
            $ok = $up->execute();
        } catch (Throwable $e) {
            $up->close();
            return ['action' => 'error', 'id' => $id];
        }
        $up->close();
        if (!$ok) {
            return ['action' => 'error', 'id' => $id];
        }
        return ['action' => 'updated', 'id' => $id];
    }

    private function autoSyncOtCustomers(mysqli $conn): void
    {
        $res = $conn->query("
            SELECT DISTINCT customer_code
            FROM dispatch_delivery_customers
            WHERE TRIM(COALESCE(customer_code, '')) <> ''
              AND UPPER(TRIM(COALESCE(route_primary, ''))) = 'OT'
        ");
        if (!($res instanceof mysqli_result)) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            $code = trim((string)($row['customer_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $norm = $this->normalizeForwardCustomerCodeKey($code);
            if ($norm !== '' && $this->tableExists($conn, 'dispatch_forward_customer_ot_sync_suppress')) {
                $skip = $conn->prepare('SELECT 1 FROM dispatch_forward_customer_ot_sync_suppress WHERE customer_code = ? LIMIT 1');
                if ($skip) {
                    $skip->bind_param('s', $norm);
                    $skip->execute();
                    $blocked = (bool)$skip->get_result()->fetch_row();
                    $skip->close();
                    if ($blocked) {
                        continue;
                    }
                }
            }
            $this->pushForwardCustomerByCode($conn, $code, false);
        }
        $res->free();
    }

    public function packages(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['menu.nav.dispatch.forwarding.packages', 'dispatch.forwarding.view', 'dispatch.forwarding.package.create', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限访问转发合包');
        }
        $canCreate = $this->hasAnyPermission(['dispatch.forwarding.package.create', 'dispatch.manage']);
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->ensureForwardingSchema($conn);
        $message = '';
        $error = '';

        $packageFeeColumnReady = $schemaReady && $this->columnExists($conn, 'dispatch_forward_packages', 'forward_fee');
        $packageVoucherColumnReady = $schemaReady && $this->columnExists($conn, 'dispatch_forward_packages', 'voucher_path');
        $waybillOptOutReady = $schemaReady && $this->columnExists($conn, 'dispatch_waybills', 'auto_forward_opt_out');

        if ($schemaReady && $canCreate && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_revert_to_inbound'])) {
            $waybillId = (int)($_POST['waybill_id'] ?? 0);
            if ($waybillId <= 0) {
                $error = '参数无效';
            } else {
                $targetStatus = '已入库';
                $fromStatus = '待转发';
                $up = $conn->prepare('UPDATE dispatch_waybills SET order_status = ?, delivered_at = NOW() WHERE id = ? AND COALESCE(order_status, \'\') = ?');
                if ($up) {
                    $up->bind_param('sis', $targetStatus, $waybillId, $fromStatus);
                    $up->execute();
                    $aff = (int)$up->affected_rows;
                    $up->close();
                    if ($aff > 0 && $waybillOptOutReady) {
                        $flag = $conn->prepare('UPDATE dispatch_waybills SET auto_forward_opt_out = 1 WHERE id = ? LIMIT 1');
                        if ($flag) {
                            $flag->bind_param('i', $waybillId);
                            $flag->execute();
                            $flag->close();
                        }
                    }
                    if ($aff > 0) {
                        header('Location: /dispatch/forwarding/packages?msg=reverted');
                        exit;
                    }
                    $error = '仅待转发状态可移回已入库';
                } else {
                    $error = '操作失败';
                }
            }
        }

        if ($schemaReady && $canCreate && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_create_package'])) {
            if (!$packageFeeColumnReady) {
                $error = '转发费用字段未就绪，请先执行数据库脚本：database/migrations/029_dispatch_forward_package_forward_fee.sql';
            }
            if ($error === '' && !$packageVoucherColumnReady) {
                $error = '凭证字段未就绪，请先执行数据库脚本：database/migrations/030_dispatch_forward_package_voucher_path.sql';
            }
            $packageNo = trim((string)($_POST['package_no'] ?? ''));
            $sendAt = trim((string)($_POST['send_at'] ?? ''));
            $forwardFeeRaw = trim((string)($_POST['forward_fee'] ?? ''));
            $forwardDeliveryCustomerId = (int)($_POST['forward_delivery_customer_id'] ?? 0);
            $forwardCustomerCode = '';
            $receiverName = trim((string)($_POST['receiver_name'] ?? ''));
            $receiverPhone = trim((string)($_POST['receiver_phone'] ?? ''));
            $receiverAddress = trim((string)($_POST['receiver_address'] ?? ''));
            $remark = trim((string)($_POST['remark'] ?? ''));
            $rawSource = trim((string)($_POST['source_tracking_nos'] ?? ''));
            $actorId = (int)($_SESSION['auth_user_id'] ?? 0);
            $voucherFile = isset($_FILES['voucher_image']) && is_array($_FILES['voucher_image']) ? $_FILES['voucher_image'] : null;

            $forwardFee = null;
            if ($error === '' && $forwardFeeRaw === '') {
                $error = '请填写转发费用';
            } elseif ($error === '') {
                if (!is_numeric($forwardFeeRaw)) {
                    $error = '转发费用须为数字';
                } else {
                    $forwardFee = round((float)$forwardFeeRaw, 2);
                    if ($forwardFee < 0) {
                        $error = '转发费用不能为负数';
                    }
                }
            }

            $sourceNos = preg_split('/[\s,]+/', strtoupper($rawSource)) ?: [];
            $sourceNos = array_map(static fn($s) => trim((string)$s), $sourceNos);
            $sourceNos = array_values(array_unique(array_filter($sourceNos, static fn($s) => $s !== '')));

            if ($error === '' && ($packageNo === '' || $sendAt === '')) {
                $error = '转发单号与发出时间必填';
            } elseif ($error === '' && empty($sourceNos)) {
                $error = '请至少加入一个原始单号';
            } elseif ($error === '') {
                $dupStmt = $conn->prepare('SELECT id FROM dispatch_forward_packages WHERE package_no = ? LIMIT 1');
                if ($dupStmt) {
                    $dupStmt->bind_param('s', $packageNo);
                    $dupStmt->execute();
                    $dup = $dupStmt->get_result()->fetch_assoc();
                    $dupStmt->close();
                    if ($dup) {
                        $error = '转发单号已存在，请勿重复';
                    }
                }
            }

            $resolvedForwardCustomerId = null;
            if ($error === '' && $forwardDeliveryCustomerId > 0) {
                $dRow = $this->sourceDeliveryCustomerRowById($conn, $forwardDeliveryCustomerId);
                if (!$dRow) {
                    $error = '所选派送客户不存在或已停用';
                } else {
                    $forwardCustomerCode = trim((string)($dRow['customer_code'] ?? ''));
                    $payload = $this->buildForwardPayloadFromSource($dRow);
                    if ($receiverName === '') {
                        $receiverName = $payload['recipient_name'];
                    }
                    if ($receiverPhone === '') {
                        $receiverPhone = $payload['phone'];
                    }
                    if ($receiverAddress === '') {
                        $receiverAddress = $payload['addr_th_full'];
                    }
                    $fcStmt = $conn->prepare('SELECT id FROM dispatch_forward_customers WHERE customer_code = ? AND status = 1 LIMIT 1');
                    if ($fcStmt && $forwardCustomerCode !== '') {
                        $fcStmt->bind_param('s', $forwardCustomerCode);
                        $fcStmt->execute();
                        $fHit = $fcStmt->get_result()->fetch_assoc();
                        $fcStmt->close();
                        if ($fHit) {
                            $resolvedForwardCustomerId = (int)($fHit['id'] ?? 0);
                        }
                    } elseif ($fcStmt) {
                        $fcStmt->close();
                    }
                }
            }

            if ($error === '' && $receiverName === '') {
                $error = '收件人不能为空';
            }
            if ($error === '' && $receiverPhone === '') {
                $error = '收件电话不能为空';
            }
            if ($error === '' && $receiverAddress === '') {
                $error = '收件地址不能为空';
            }
            $savedVoucherPath = null;
            if ($error === '') {
                $saveVoucher = $this->forwardVoucherSaveFromUpload($voucherFile, 'fwdpkg');
                if (!$saveVoucher['ok']) {
                    $error = $saveVoucher['error'];
                } else {
                    $savedVoucherPath = (string)($saveVoucher['path'] ?? '');
                }
            }

            $waybillRows = [];
            if ($error === '') {
                $placeholders = implode(',', array_fill(0, count($sourceNos), '?'));
                $types = str_repeat('s', count($sourceNos));
                $stmt = $conn->prepare("
                    SELECT id, original_tracking_no, order_status
                    FROM dispatch_waybills
                    WHERE original_tracking_no IN ($placeholders)
                ");
                if (!$stmt) {
                    $error = '查询订单失败';
                } else {
                    $stmt->bind_param($types, ...$sourceNos);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($res && ($row = $res->fetch_assoc())) {
                        $waybillRows[strtoupper((string)$row['original_tracking_no'])] = $row;
                    }
                    $stmt->close();
                }
            }

            $missing = [];
            $waybillIds = [];
            if ($error === '') {
                foreach ($sourceNos as $s) {
                    if (!isset($waybillRows[$s])) {
                        $missing[] = $s;
                        continue;
                    }
                    $waybillIds[] = (int)$waybillRows[$s]['id'];
                }
                if (!empty($missing)) {
                    $error = '以下原始单号不存在订单：' . implode('，', array_slice($missing, 0, 8));
                }
            }

            if ($error === '' && !empty($waybillIds)) {
                $in = implode(',', array_fill(0, count($waybillIds), '?'));
                $types = str_repeat('i', count($waybillIds));
                $usedStmt = $conn->prepare("SELECT waybill_id FROM dispatch_forward_package_items WHERE waybill_id IN ($in)");
                if ($usedStmt) {
                    $usedStmt->bind_param($types, ...$waybillIds);
                    $usedStmt->execute();
                    $res = $usedStmt->get_result();
                    $used = [];
                    while ($res && ($row = $res->fetch_assoc())) {
                        $used[] = (int)$row['waybill_id'];
                    }
                    $usedStmt->close();
                    if (!empty($used)) {
                        $error = '部分订单已被其他转发合包使用，不能重复绑定';
                    }
                }
            }

            if ($error === '' && $forwardFee === null) {
                $error = '转发费用无效';
            }

            if ($error === '') {
                $conn->begin_transaction();
                try {
                    $ins = null;
                    if ($resolvedForwardCustomerId !== null && $resolvedForwardCustomerId > 0) {
                        $ins = $conn->prepare('
                            INSERT INTO dispatch_forward_packages (
                                package_no, send_at, forward_fee,
                                forward_customer_id, forward_customer_code,
                                receiver_name, receiver_phone, voucher_path, receiver_address, remark, created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ');
                    } else {
                        $ins = $conn->prepare('
                            INSERT INTO dispatch_forward_packages (
                                package_no, send_at, forward_fee,
                                forward_customer_id, forward_customer_code,
                                receiver_name, receiver_phone, voucher_path, receiver_address, remark, created_by
                            ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)
                        ');
                    }
                    if (!$ins) {
                        throw new RuntimeException('保存失败');
                    }
                    if ($resolvedForwardCustomerId !== null && $resolvedForwardCustomerId > 0) {
                        $ins->bind_param(
                            'ssdi' . str_repeat('s', 6) . 'i',
                            $packageNo,
                            $sendAt,
                            $forwardFee,
                            $resolvedForwardCustomerId,
                            $forwardCustomerCode,
                            $receiverName,
                            $receiverPhone,
                            $savedVoucherPath,
                            $receiverAddress,
                            $remark,
                            $actorId
                        );
                    } else {
                        $ins->bind_param(
                            'ssd' . str_repeat('s', 6) . 'i',
                            $packageNo,
                            $sendAt,
                            $forwardFee,
                            $forwardCustomerCode,
                            $receiverName,
                            $receiverPhone,
                            $savedVoucherPath,
                            $receiverAddress,
                            $remark,
                            $actorId
                        );
                    }
                    $ins->execute();
                    $packageId = (int)$ins->insert_id;
                    $ins->close();

                    $insItem = $conn->prepare('INSERT INTO dispatch_forward_package_items (forward_package_id, waybill_id, original_tracking_no) VALUES (?, ?, ?)');
                    $upStatus = $conn->prepare('UPDATE dispatch_waybills SET order_status = ?, delivered_at = NOW() WHERE id = ?');
                    if (!$insItem || !$upStatus) {
                        throw new RuntimeException('保存失败');
                    }
                    foreach ($sourceNos as $no) {
                        $wid = (int)$waybillRows[$no]['id'];
                        $insItem->bind_param('iis', $packageId, $wid, $no);
                        $insItem->execute();
                        $newStatus = '已转发';
                        $upStatus->bind_param('si', $newStatus, $wid);
                        $upStatus->execute();
                    }
                    $insItem->close();
                    $upStatus->close();
                    $conn->commit();

                    $this->writeAudit($conn, 'dispatch.forwarding.package.create', 'dispatch_forward_package', $packageId, [
                        'package_no' => $packageNo,
                        'forward_fee' => $forwardFee,
                        'source_tracking_nos' => $sourceNos,
                        'forward_delivery_customer_id' => $forwardDeliveryCustomerId,
                        'forward_customer_code' => $forwardCustomerCode,
                        'voucher_path' => $savedVoucherPath,
                    ]);
                    header('Location: /dispatch/forwarding/packages?msg=created');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    if (is_string($savedVoucherPath) && $savedVoucherPath !== '') {
                        $full = $this->forwardVoucherStorageDir() . DIRECTORY_SEPARATOR . $savedVoucherPath;
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    }
                    $error = '保存转发合包失败，请稍后重试';
                }
            }
        }

        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '转发合包创建成功，相关订单已更新为已转发';
        }
        if (isset($_GET['msg']) && $_GET['msg'] === 'reverted') {
            $message = '已从待转发移回已入库';
        }

        $customerOptions = [];
        if ($schemaReady && $this->tableExists($conn, 'dispatch_delivery_customers')) {
            $customerOptions = $this->forwardPackageDeliveryCustomerSelectOptions($conn);
        }

        $candidateRows = [];
        if ($schemaReady && $this->tableExists($conn, 'dispatch_delivery_customers')) {
            $qCustomerCode = trim((string)($_GET['q_customer_code'] ?? ''));
            $hasCustomerState = $this->columnExists($conn, 'dispatch_delivery_customers', 'customer_state');
            $where = [];
            $types = '';
            $params = [];
            $where[] = 'i.id IS NULL';
            $where[] = "COALESCE(w.order_status, '') IN ('待转发', '已入库')";
            if ($hasCustomerState) {
                $where[] = "(
                    UPPER(TRIM(COALESCE(dc.route_primary, dcc.route_primary, ''))) = 'OT'
                    OR COALESCE(NULLIF(TRIM(dc.customer_state), ''), NULLIF(TRIM(dcc.customer_state), ''), '') = '转发'
                )";
            } else {
                $where[] = "UPPER(TRIM(COALESCE(dc.route_primary, dcc.route_primary, ''))) = 'OT'";
            }
            if ($qCustomerCode !== '') {
                $where[] = "COALESCE(NULLIF(TRIM(dc.customer_code), ''), NULLIF(TRIM(dcc.customer_code), ''), TRIM(w.delivery_customer_code)) LIKE ?";
                $types .= 's';
                $params[] = '%' . $qCustomerCode . '%';
            }
            if ($waybillOptOutReady) {
                $where[] = "(COALESCE(w.order_status, '') = '待转发' OR (COALESCE(w.order_status, '') = '已入库' AND COALESCE(w.auto_forward_opt_out, 0) = 0))";
            }
            $whereSql = implode(' AND ', $where);
            $sql = "
                SELECT
                    w.id AS waybill_id,
                    w.original_tracking_no,
                    w.order_status,
                    w.scanned_at,
                    w.delivery_customer_code,
                    COALESCE(NULLIF(TRIM(dc.customer_code), ''), NULLIF(TRIM(dcc.customer_code), ''), TRIM(w.delivery_customer_code)) AS matched_customer_code,
                    COALESCE(NULLIF(TRIM(dc.wechat_id), ''), NULLIF(TRIM(dcc.wechat_id), ''), '') AS wechat_id,
                    COALESCE(NULLIF(TRIM(dc.line_id), ''), NULLIF(TRIM(dcc.line_id), ''), '') AS line_id
                FROM dispatch_waybills w
                LEFT JOIN dispatch_delivery_customers dc
                    ON dc.id = w.delivery_customer_id
                LEFT JOIN dispatch_delivery_customers dcc
                    ON dcc.consigning_client_id = w.consigning_client_id
                   AND TRIM(COALESCE(dcc.customer_code, '')) = TRIM(COALESCE(w.delivery_customer_code, ''))
                   AND dcc.status = 1
                LEFT JOIN dispatch_forward_package_items i ON i.waybill_id = w.id
                WHERE {$whereSql}
                ORDER BY w.id DESC
                LIMIT 1000
            ";
            $st = $conn->prepare($sql);
            if ($st) {
                if ($types !== '') {
                    $st->bind_param($types, ...$params);
                }
                $st->execute();
                $r = $st->get_result();
                while ($r && ($row = $r->fetch_assoc())) {
                    $candidateRows[] = $row;
                }
                $st->close();
            }
        }

        $activeTab = 'packages';
        $title = '派送业务 / 转发操作 / 转发合包';
        $contentView = __DIR__ . '/../Views/dispatch/forwarding/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function customers(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['menu.nav.dispatch.forwarding.customers', 'dispatch.forwarding.view', 'dispatch.forwarding.customer.manage', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限访问转发客户维护');
        }
        $canManage = $this->hasAnyPermission(['dispatch.forwarding.customer.manage', 'dispatch.manage']);
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->ensureForwardingSchema($conn);
        $syncSchemaReady = $schemaReady ? $this->ensureForwardingCustomerSyncSchema($conn) : false;
        $message = '';
        $error = '';

        if ($schemaReady && !$syncSchemaReady) {
            $error = '客户维护同步字段未就绪，请先执行 database/migrations/028_dispatch_forwarding_customer_sync_fields.sql 与 052_dispatch_forward_customers_addr_th_full.sql';
        }

        if ($schemaReady && $syncSchemaReady) {
            $this->autoSyncOtCustomers($conn);
        }

        if ($schemaReady && $syncSchemaReady && $canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['push_forward_customer_by_code'])) {
            $customerCode = strtoupper(trim((string)($_POST['customer_code'] ?? '')));
            if ($customerCode === '') {
                $error = '请先输入客户编码';
            } else {
                $ret = $this->pushForwardCustomerByCode($conn, $customerCode, true);
                if ($ret['action'] === 'not_found') {
                    $error = '派送客户数据库中不存在该客户编码';
                } elseif ($ret['action'] === 'error') {
                    $error = '推送失败，请稍后重试';
                } else {
                    $this->writeAudit($conn, 'dispatch.forwarding.customer.push', 'dispatch_forward_customer', (int)$ret['id'], [
                        'customer_code' => $customerCode,
                        'result' => $ret['action'],
                    ]);
                    header('Location: /dispatch/forwarding/customers?msg=pushed');
                    exit;
                }
            }
        }

        if ($schemaReady && $syncSchemaReady && $canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_forward_customer_edit'])) {
            $id = (int)($_POST['customer_id'] ?? 0);
            $recipientName = trim((string)($_POST['recipient_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $addrThFull = trim((string)($_POST['addr_th_full'] ?? ''));
            $status = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;
            if ($id > 0) {
                $up = $conn->prepare("
                    UPDATE dispatch_forward_customers
                    SET recipient_name = ?, phone = ?, addr_th_full = ?, status = ?, sync_mark = ''
                    WHERE id = ?
                ");
                if ($up) {
                    $up->bind_param('sssii', $recipientName, $phone, $addrThFull, $status, $id);
                    $up->execute();
                    $up->close();
                    $this->writeAudit($conn, 'dispatch.forwarding.customer.update', 'dispatch_forward_customer', $id, [
                        'recipient_name' => $recipientName,
                        'phone' => $phone,
                        'addr_th_full' => $addrThFull,
                        'status' => $status,
                        'sync_mark' => '',
                    ]);
                    header('Location: /dispatch/forwarding/customers?msg=updated');
                    exit;
                }
            }
        }

        if ($schemaReady && $syncSchemaReady && $canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_forward_customer'])) {
            $id = (int)($_POST['customer_id'] ?? 0);
            if ($id <= 0) {
                $error = '参数无效';
            } else {
                $normCode = '';
                $selCode = $conn->prepare('SELECT customer_code FROM dispatch_forward_customers WHERE id = ? LIMIT 1');
                if ($selCode) {
                    $selCode->bind_param('i', $id);
                    $selCode->execute();
                    $cr = $selCode->get_result()->fetch_assoc();
                    $selCode->close();
                    if (is_array($cr)) {
                        $normCode = $this->normalizeForwardCustomerCodeKey((string)($cr['customer_code'] ?? ''));
                    }
                }
                $del = $conn->prepare('DELETE FROM dispatch_forward_customers WHERE id = ? LIMIT 1');
                if ($del) {
                    $del->bind_param('i', $id);
                    try {
                        if ($del->execute() && $del->affected_rows > 0) {
                            $suppressOtResync = $normCode !== '' && $this->forwardCustomerCodeHasActiveOtRoute($conn, $normCode);
                            if ($suppressOtResync) {
                                $this->rememberForwardOtAutoSyncSuppress($conn, $normCode);
                            }
                            $this->writeAudit($conn, 'dispatch.forwarding.customer.delete', 'dispatch_forward_customer', $id, [
                                'customer_code' => $normCode,
                                'ot_auto_resync_suppressed' => $suppressOtResync,
                            ]);
                            $del->close();
                            header('Location: /dispatch/forwarding/customers?msg=deleted_forward_customer');
                            exit;
                        }
                        $error = '删除失败或记录不存在';
                    } catch (mysqli_sql_exception $e) {
                        $error = '删除失败';
                    }
                    $del->close();
                } else {
                    $error = '删除失败';
                }
            }
        }

        if (isset($_GET['msg'])) {
            $msg = (string)$_GET['msg'];
            if ($msg === 'pushed') {
                $message = '已按客户编码完成推送';
            }
            if ($msg === 'updated') {
                $message = '转发客户更新成功';
            }
            if ($msg === 'deleted_forward_customer') {
                $message = '已删除转发客户';
            }
        }

        $editRow = null;
        if ($schemaReady && isset($_GET['edit_id'])) {
            $editId = (int)$_GET['edit_id'];
            if ($editId > 0) {
                $s = $conn->prepare('SELECT * FROM dispatch_forward_customers WHERE id = ? LIMIT 1');
                if ($s) {
                    $s->bind_param('i', $editId);
                    $s->execute();
                    $editRow = $s->get_result()->fetch_assoc();
                    $s->close();
                }
            }
        }

        $rows = [];
        if ($schemaReady) {
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q !== '') {
                $s = $conn->prepare('
                    SELECT *
                    FROM dispatch_forward_customers
                    WHERE customer_code LIKE ? OR wechat_line LIKE ?
                    ORDER BY customer_code ASC, id DESC
                    LIMIT 500
                ');
                if ($s) {
                    $kw = '%' . $q . '%';
                    $s->bind_param('ss', $kw, $kw);
                    $s->execute();
                    $res = $s->get_result();
                    while ($res && ($r = $res->fetch_assoc())) {
                        $rows[] = $r;
                    }
                    $s->close();
                }
            } else {
                $res = $conn->query('SELECT * FROM dispatch_forward_customers ORDER BY customer_code ASC, id DESC LIMIT 500');
                if ($res instanceof mysqli_result) {
                    while ($r = $res->fetch_assoc()) {
                        $rows[] = $r;
                    }
                    $res->free();
                }
            }
        }

        $activeTab = 'customers';
        $title = '派送业务 / 转发操作 / 客户维护';
        $contentView = __DIR__ . '/../Views/dispatch/forwarding/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function records(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['menu.nav.dispatch.forwarding.records', 'dispatch.forwarding.view', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限访问转发查询记录');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->ensureForwardingSchema($conn);
        $message = '';
        $error = '';

        $rows = [];
        if ($schemaReady) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_forward_package'])) {
                $pkgId = (int)($_POST['package_id'] ?? 0);
                if ($pkgId <= 0) {
                    $error = '参数无效';
                } else {
                    $conn->begin_transaction();
                    try {
                        $waybillIds = [];
                        $ws = $conn->prepare('SELECT waybill_id FROM dispatch_forward_package_items WHERE forward_package_id = ?');
                        if ($ws) {
                            $ws->bind_param('i', $pkgId);
                            $ws->execute();
                            $wr = $ws->get_result();
                            while ($wr && ($r = $wr->fetch_assoc())) {
                                $wid = (int)($r['waybill_id'] ?? 0);
                                if ($wid > 0) $waybillIds[] = $wid;
                            }
                            $ws->close();
                        }
                        if ($waybillIds !== []) {
                            $in = implode(',', array_fill(0, count($waybillIds), '?'));
                            $types = 's' . str_repeat('i', count($waybillIds));
                            $params = array_merge(['待转发'], $waybillIds);
                            $up = $conn->prepare("UPDATE dispatch_waybills SET order_status = ?, delivered_at = NOW() WHERE id IN ($in)");
                            if ($up) {
                                $up->bind_param($types, ...$params);
                                $up->execute();
                                $up->close();
                            }
                        }
                        $del = $conn->prepare('DELETE FROM dispatch_forward_packages WHERE id = ? LIMIT 1');
                        if (!$del) {
                            throw new RuntimeException('删除失败');
                        }
                        $del->bind_param('i', $pkgId);
                        $del->execute();
                        $aff = (int)$del->affected_rows;
                        $del->close();
                        if ($aff <= 0) {
                            throw new RuntimeException('未找到可删除记录');
                        }
                        $conn->commit();
                        header('Location: /dispatch/forwarding/records?msg=deleted');
                        exit;
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $error = '删除失败，请稍后重试';
                    }
                }
            }

            $qPackageNo = trim((string)($_GET['q_package_no'] ?? ''));
            $qCustomerCode = trim((string)($_GET['q_customer_code'] ?? ''));
            $qSourceNo = trim((string)($_GET['q_source_no'] ?? ''));
            $qInboundBatch = trim((string)($_GET['q_inbound_batch'] ?? ''));

            $where = [];
            $types = '';
            $params = [];
            if ($qPackageNo !== '') {
                $where[] = 'p.package_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qPackageNo . '%';
            }
            if ($qCustomerCode !== '') {
                $where[] = 'p.forward_customer_code LIKE ?';
                $types .= 's';
                $params[] = '%' . $qCustomerCode . '%';
            }
            if ($qSourceNo !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM dispatch_forward_package_items i2 WHERE i2.forward_package_id = p.id AND i2.original_tracking_no LIKE ?)';
                $types .= 's';
                $params[] = '%' . $qSourceNo . '%';
            }
            if ($qInboundBatch !== '') {
                $where[] = "EXISTS (
                    SELECT 1
                    FROM dispatch_forward_package_items i3
                    INNER JOIN dispatch_waybills w3 ON w3.id = i3.waybill_id
                    WHERE i3.forward_package_id = p.id
                      AND COALESCE(w3.inbound_batch, '') LIKE ?
                )";
                $types .= 's';
                $params[] = '%' . $qInboundBatch . '%';
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $hasPkgFee = $this->columnExists($conn, 'dispatch_forward_packages', 'forward_fee');
            $feeSelect = $hasPkgFee ? 'p.forward_fee,' : '0 AS forward_fee,';
            $hasVoucher = $this->columnExists($conn, 'dispatch_forward_packages', 'voucher_path');
            $voucherSelect = $hasVoucher ? 'p.voucher_path,' : "'' AS voucher_path,";
            $sql = "
                SELECT
                    p.id,
                    p.package_no,
                    p.send_at,
                    {$feeSelect}
                    {$voucherSelect}
                    p.forward_customer_code,
                    COALESCE((
                        SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(wb.inbound_batch), '') ORDER BY wb.inbound_batch ASC SEPARATOR ', ')
                        FROM dispatch_forward_package_items fi
                        INNER JOIN dispatch_waybills wb ON wb.id = fi.waybill_id
                        WHERE fi.forward_package_id = p.id
                    ), '') AS inbound_batches,
                    p.receiver_name,
                    p.receiver_phone,
                    p.receiver_address,
                    p.remark,
                    p.created_at,
                    u.full_name AS created_by_name,
                    (
                        SELECT COUNT(*)
                        FROM dispatch_forward_package_items i
                        WHERE i.forward_package_id = p.id
                    ) AS item_count,
                    (
                        SELECT GROUP_CONCAT(i.original_tracking_no ORDER BY i.id ASC SEPARATOR ', ')
                        FROM dispatch_forward_package_items i
                        WHERE i.forward_package_id = p.id
                    ) AS source_tracking_nos
                FROM dispatch_forward_packages p
                LEFT JOIN users u ON u.id = p.created_by
                {$whereSql}
                ORDER BY p.id DESC
                LIMIT 500
            ";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($types !== '') {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $rows[] = $r;
                }
                $stmt->close();
            }
        }
        if (isset($_GET['msg']) && (string)$_GET['msg'] === 'deleted' && $message === '') {
            $message = '已删除该转发合包，内件状态已回滚为待转发';
        }

        $activeTab = 'records';
        $title = '派送业务 / 转发操作 / 查询记录';
        $contentView = __DIR__ . '/../Views/dispatch/forwarding/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function forwardVoucherView(): void
    {
        $this->requireDispatchMenu();
        if (!$this->hasAnyPermission(['menu.nav.dispatch.forwarding.packages', 'dispatch.forwarding.view', 'dispatch.manage'])) {
            $this->denyNoPermission('无权限查看转发凭证');
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo '404';
            exit;
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->columnExists($conn, 'dispatch_forward_packages', 'voucher_path')) {
            http_response_code(404);
            echo '404';
            exit;
        }
        $stmt = $conn->prepare('SELECT voucher_path FROM dispatch_forward_packages WHERE id = ? LIMIT 1');
        if (!$stmt) {
            http_response_code(500);
            exit;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stored = trim((string)($row['voucher_path'] ?? ''));
        $resolved = $this->forwardVoucherResolveStoredFile($stored);
        if ($resolved === null) {
            http_response_code(404);
            echo '404';
            exit;
        }
        $filename = basename((string)$stored);
        header('Content-Type: ' . $resolved['mime']);
        header('Content-Length: ' . (string)filesize($resolved['full']));
        header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
        readfile($resolved['full']);
        exit;
    }
}
