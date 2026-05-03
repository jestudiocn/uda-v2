<?php
require_once __DIR__ . '/Concerns/AuditLogTrait.php';

class FinanceController
{
    use AuditLogTrait;
    private array $tableExistsCache = [];
    private array $columnExistsCache = [];

    private function denyNoPermission(string $message = '无权限执行此操作'): void
    {
        http_response_code(403);
        echo $message;
        exit;
    }

    private function resolvePerPage(array $allowed = [20, 50, 100], int $default = 20): int
    {
        $perPage = (int)($_GET['per_page'] ?? $default);
        if (!in_array($perPage, $allowed, true)) {
            $perPage = $default;
        }
        return $perPage;
    }

    private function resolvePage(): int
    {
        $page = (int)($_GET['page'] ?? 1);
        return $page > 0 ? $page : 1;
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

    private function tableExists(mysqli $conn, string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }
        $safeTable = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $this->tableExistsCache[$table] = $exists;
        return $exists;
    }

    private function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }
        if (!$this->tableExists($conn, $table)) {
            $this->columnExistsCache[$key] = false;
            return false;
        }
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $this->columnExistsCache[$key] = $exists;
        return $exists;
    }

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

    private function sendFinanceNotification(
        mysqli $conn,
        string $eventKey,
        int $bizId,
        int $actorUserId,
        string $title,
        string $content
    ): void {
        if (!$this->tableExists($conn, 'notification_rules') || !$this->tableExists($conn, 'notifications_inbox')) {
            return;
        }
        $ruleStmt = $conn->prepare('SELECT enabled, recipients_mode, custom_user_ids FROM notification_rules WHERE event_key = ? LIMIT 1');
        if (!$ruleStmt) {
            return;
        }
        $ruleStmt->bind_param('s', $eventKey);
        $ruleStmt->execute();
        $rule = $ruleStmt->get_result()->fetch_assoc();
        $ruleStmt->close();
        if (!$rule || (int)($rule['enabled'] ?? 0) !== 1) {
            return;
        }
        $recipientIds = [];
        $mode = (string)($rule['recipients_mode'] ?? 'creator');
        if ($mode === 'all_active_users') {
            $res = $conn->query('SELECT id FROM users WHERE status = 1');
            while ($res && ($row = $res->fetch_assoc())) {
                $rid = (int)($row['id'] ?? 0);
                if ($rid > 0) {
                    $recipientIds[] = $rid;
                }
            }
        } elseif ($mode === 'custom_users') {
            $csv = trim((string)($rule['custom_user_ids'] ?? ''));
            if ($csv !== '') {
                foreach (explode(',', $csv) as $part) {
                    $rid = (int)trim($part);
                    if ($rid > 0) {
                        $recipientIds[] = $rid;
                    }
                }
            }
        } else {
            $recipientIds[] = $actorUserId;
        }
        $recipientIds = array_values(array_unique(array_filter($recipientIds, static fn ($id) => (int)$id > 0)));
        if (empty($recipientIds)) {
            return;
        }
        $stmt = $conn->prepare('
            INSERT INTO notifications_inbox (user_id, title, content, biz_type, biz_id, created_by, is_read)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ');
        if (!$stmt) {
            return;
        }
        $bizType = 'finance';
        foreach ($recipientIds as $recipientId) {
            $stmt->bind_param('isssii', $recipientId, $title, $content, $bizType, $bizId, $actorUserId);
            $stmt->execute();
        }
        $stmt->close();
    }

    private function requireFinanceMenu(): void
    {
        $financeNavKeys = [
            'menu.nav.finance.transactions.create',
            'menu.nav.finance.transactions.list',
            'menu.nav.finance.payables.create',
            'menu.nav.finance.payables.list',
            'menu.nav.finance.receivables.create',
            'menu.nav.finance.receivables.list',
            'menu.nav.finance.reports',
            'menu.nav.finance.ar.customers',
            'menu.nav.finance.ar.billing_schemes',
            'menu.nav.finance.ar.charges.create',
            'menu.nav.finance.ar.charges.list',
            'menu.nav.finance.ar.invoices',
            'menu.nav.finance.ar.ledger',
            'menu.nav.finance.accounts',
            'menu.nav.finance.categories',
            'menu.nav.finance.parties',
        ];
        if (!$this->hasAnyPermission(array_merge(['menu.finance', 'menu.dashboard'], $financeNavKeys))) {
            $this->denyNoPermission('无权限访问财务模块');
        }
    }

    private function ensureFinanceSchema(mysqli $conn): void
    {
        $needTables = ['accounts', 'transaction_categories', 'transactions', 'payables', 'receivables'];
        foreach ($needTables as $table) {
            if (!$this->tableExists($conn, $table)) {
                throw new RuntimeException('财务表未建立，请先执行 migration：013_create_finance_core_tables.sql');
            }
        }
        $this->ensureFinanceVoucherColumns($conn);
    }

    private function ensureFinanceVoucherColumns(mysqli $conn): void
    {
        foreach (['transactions', 'payables', 'receivables'] as $table) {
            if ($this->tableExists($conn, $table) && !$this->columnExists($conn, $table, 'voucher_path')) {
                $safe = $conn->real_escape_string($table);
                $conn->query("ALTER TABLE `{$safe}` ADD COLUMN voucher_path VARCHAR(512) NULL DEFAULT NULL");
                $this->columnExistsCache[$table . '.voucher_path'] = true;
            }
        }
    }

    private function financeVoucherStorageDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'finance_vouchers';
    }

    private function ensureFinanceVoucherStorageDir(): void
    {
        $dir = $this->financeVoucherStorageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /** @param array<string, mixed>|null $file $_FILES 单项 */
    private function financeVoucherUploadPreflight(?array $file): string
    {
        if ($file === null || !isset($file['error'])) {
            return '';
        }
        $err = (int)$file['error'];
        if ($err === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($err !== UPLOAD_ERR_OK) {
            return '凭证图档上传失败';
        }
        $size = (int)($file['size'] ?? 0);
        if ($size > 5 * 1024 * 1024) {
            return '凭证图档不能超过 5MB';
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return '凭证图档无效';
        }
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
        } else {
            $mime = (string)mime_content_type($tmp);
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            return '凭证仅支持 JPG、PNG、GIF、WEBP';
        }
        return '';
    }

    /**
     * 将已预检的上传凭证保存为随机文件名（仅存 basename 写入库）。
     *
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, path: ?string, error: string}
     */
    private function financeVoucherSaveFromUpload(?array $file, string $filenameBase): array
    {
        if ($file === null || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null, 'error' => ''];
        }
        $errPre = $this->financeVoucherUploadPreflight($file);
        if ($errPre !== '') {
            return ['ok' => false, 'path' => null, 'error' => $errPre];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
        } else {
            $mime = (string)mime_content_type($tmp);
        }
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $extMap[$mime] ?? 'bin';
        if ($ext === 'bin') {
            return ['ok' => false, 'path' => null, 'error' => '凭证格式不支持'];
        }
        $this->ensureFinanceVoucherStorageDir();
        $dir = $this->financeVoucherStorageDir();
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase) ?: 'v';
        $name = $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'path' => null, 'error' => '凭证保存失败'];
        }
        return ['ok' => true, 'path' => $name, 'error' => ''];
    }

    /** @return array{full: string, mime: string}|null */
    private function financeVoucherResolveStoredFile(?string $storedName): ?array
    {
        $base = basename((string)$storedName);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $base)) {
            return null;
        }
        $full = $this->financeVoucherStorageDir() . DIRECTORY_SEPARATOR . $base;
        if (!is_file($full)) {
            return null;
        }
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        return ['full' => $full, 'mime' => $mime];
    }

    public function financeVoucherView(): void
    {
        $this->requireFinanceMenu();
        $kind = trim((string)($_GET['kind'] ?? ''));
        $id = (int)($_GET['id'] ?? 0);
        if (!in_array($kind, ['transaction', 'payable', 'receivable'], true) || $id <= 0) {
            http_response_code(404);
            echo '404';
            exit;
        }
        $permKeys = match ($kind) {
            'transaction' => ['menu.nav.finance.transactions.list', 'finance.transactions.view', 'finance.manage'],
            'payable' => ['menu.nav.finance.payables.list', 'finance.payables.view', 'finance.manage'],
            default => ['menu.nav.finance.receivables.list', 'finance.receivables.view', 'finance.manage'],
        };
        if (!$this->hasAnyPermission($permKeys)) {
            $this->denyNoPermission('无权限查看凭证');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $sql = match ($kind) {
            'transaction' => 'SELECT voucher_path FROM transactions WHERE id = ? LIMIT 1',
            'payable' => 'SELECT voucher_path FROM payables WHERE id = ? LIMIT 1',
            default => 'SELECT voucher_path FROM receivables WHERE id = ? LIMIT 1',
        };
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            exit;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stored = trim((string)($row['voucher_path'] ?? ''));
        $resolved = $stored !== '' ? $this->financeVoucherResolveStoredFile($stored) : null;
        if ($resolved === null) {
            http_response_code(404);
            echo '404';
            exit;
        }
        header('Content-Type: ' . $resolved['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($resolved['full']);
        exit;
    }

    private function financeParties(mysqli $conn, string $kind = 'both'): array
    {
        if (!$this->tableExists($conn, 'finance_parties')) {
            return [];
        }
        $rows = [];
        $sql = 'SELECT id, party_name, party_kind, status FROM finance_parties WHERE status = 1';
        if ($kind === 'pay') {
            $sql .= " AND party_kind IN ('pay', 'both')";
        } elseif ($kind === 'receive') {
            $sql .= " AND party_kind IN ('receive', 'both')";
        }
        $sql .= ' ORDER BY party_name ASC, id ASC';
        $res = $conn->query($sql);
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array{id:int,party_name:string}|null */
    private function financePartyRowById(mysqli $conn, int $partyId): ?array
    {
        if ($partyId <= 0 || !$this->tableExists($conn, 'finance_parties')) {
            return null;
        }
        $stmt = $conn->prepare('SELECT id, party_name FROM finance_parties WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $partyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)($row['id'] ?? 0),
            'party_name' => trim((string)($row['party_name'] ?? '')),
        ];
    }

    private function activeAccounts(mysqli $conn): array
    {
        $rows = [];
        $res = $conn->query('SELECT id, account_name, account_type, status FROM accounts ORDER BY id DESC');
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function activeCategories(mysqli $conn): array
    {
        $rows = [];
        $res = $conn->query('SELECT id, name, type, status FROM transaction_categories ORDER BY id DESC');
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function ensureArSchema(mysqli $conn): void
    {
        $needTables = [
            'ar_customer_profiles',
            'ar_charge_items',
            'ar_invoices',
            'ar_invoice_lines',
            'ar_receivable_ledger',
            'ar_charge_dropdown_options',
            'ar_party_billing_schemes',
        ];
        foreach ($needTables as $table) {
            if (!$this->tableExists($conn, $table)) {
                throw new RuntimeException('应收账单表未建立，请先执行 migration：017_create_finance_ar_tables.sql（及后续 018、019、020）');
            }
        }
    }

    /** @return list<array{id:int,name:string,sort_order:int}> */
    private function arChargeDropdownOptions(mysqli $conn, string $group): array
    {
        if (!in_array($group, ['category', 'unit'], true)) {
            return [];
        }
        $rows = [];
        $stmt = $conn->prepare('SELECT id, name, sort_order FROM ar_charge_dropdown_options WHERE option_group = ? AND status = 1 ORDER BY sort_order ASC, id ASC');
        if ($stmt) {
            $stmt->bind_param('s', $group);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => (string)($row['name'] ?? ''),
                    'sort_order' => (int)($row['sort_order'] ?? 0),
                ];
            }
            $stmt->close();
        }
        return $rows;
    }

    private function arChargeOptionNameExists(mysqli $conn, string $group, string $name): bool
    {
        $stmt = $conn->prepare('SELECT id FROM ar_charge_dropdown_options WHERE option_group = ? AND name = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $group, $name);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc() !== null;
        $stmt->close();
        return $exists;
    }

    private function arNextInvoiceNo(mysqli $conn): string
    {
        $datePart = date('Ymd');
        $prefix = 'AR' . $datePart . '-';
        $safePrefix = $conn->real_escape_string($prefix);
        $res = $conn->query("SELECT invoice_no FROM ar_invoices WHERE invoice_no LIKE '{$safePrefix}%' ORDER BY id DESC LIMIT 1");
        $seq = 1;
        if ($res && ($row = $res->fetch_assoc())) {
            $invoiceNo = (string)($row['invoice_no'] ?? '');
            if (preg_match('/-(\d{4})$/', $invoiceNo, $m)) {
                $seq = ((int)$m[1]) + 1;
            }
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function arPartyName(mysqli $conn, int $partyId): string
    {
        $stmt = $conn->prepare('SELECT party_name FROM finance_parties WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $partyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return trim((string)($row['party_name'] ?? ''));
    }

    /**
     * 某客户下「未收款」已发布账单的费用明细行（与 CSV 导出同源）。
     *
     * @return array{party_name: string, rows: list<array<string, mixed>>, sum: float}
     */
    private function arUnpaidIssuedLineDataset(mysqli $conn, int $partyId): array
    {
        $partyName = $this->arPartyName($conn, $partyId);
        $rows = [];
        $exportHasProject = $this->columnExists($conn, 'ar_charge_items', 'project_name');
        $projectCol = $exportHasProject ? 'c.project_name' : "'' AS project_name";
        $stmt = $conn->prepare("
            SELECT i.invoice_no, c.billing_date, c.category_name, {$projectCol} AS project_name,
                   c.quantity, c.unit_name, c.unit_price, l.line_amount
            FROM ar_invoices i
            INNER JOIN ar_invoice_lines l ON l.invoice_id = i.id
            INNER JOIN ar_charge_items c ON c.id = l.charge_item_id
            WHERE i.party_id = ? AND i.status IN ('issued')
            ORDER BY i.id DESC, l.id ASC
        ");
        if ($stmt) {
            $stmt->bind_param('i', $partyId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float)($r['line_amount'] ?? 0);
        }
        return ['party_name' => $partyName, 'rows' => $rows, 'sum' => $sum];
    }

    /** @return array<string, string> */
    private function arPricingModeCatalogue(): array
    {
        return [
            'line_only' => '按量计价（单价 × 数量）',
            'base_plus_line' => '固定费用 + 按量（基础费 + 单价×数量）',
            'weight_first_continue' => '首续重（KG，计费重可日后由系统带入）',
        ];
    }

    /** @return array<string, string> */
    private function arSchemeAlgorithmCatalogue(): array
    {
        return [
            'qty_unit_price' => '数量（单位）× 单价',
            'base_plus_line' => '固定费用 + 按量（基础费 + 单价×数量）',
            'weight_first_continue' => '重量（KG）首续重',
        ];
    }

    /**
     * @param array<string, mixed>|null|false $cfg
     * @return array{pricing_modes: list<string>, default_billing_scheme_id: int}
     */
    private function arNormalizeProfileConfig($cfg): array
    {
        $catalogue = $this->arPricingModeCatalogue();
        $validKeys = array_keys($catalogue);
        $defaultSchemeId = 0;
        if (is_array($cfg) && isset($cfg['default_billing_scheme_id'])) {
            $defaultSchemeId = max(0, (int)$cfg['default_billing_scheme_id']);
        }
        if (!is_array($cfg)) {
            return ['pricing_modes' => ['line_only'], 'default_billing_scheme_id' => $defaultSchemeId];
        }
        if (!empty($cfg['expression']) && is_string($cfg['expression'])) {
            return ['pricing_modes' => ['line_only'], 'default_billing_scheme_id' => $defaultSchemeId];
        }
        $modes = $cfg['pricing_modes'] ?? null;
        if (!is_array($modes)) {
            return ['pricing_modes' => ['line_only'], 'default_billing_scheme_id' => $defaultSchemeId];
        }
        $out = [];
        foreach ($modes as $m) {
            $key = (string)$m;
            if (in_array($key, $validKeys, true) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        if ($out === []) {
            $out = ['line_only'];
        }
        return ['pricing_modes' => $out, 'default_billing_scheme_id' => $defaultSchemeId];
    }

    private function arComputeChargeAmount(string $mode, float $unitPrice, float $quantity, float $baseFee): float
    {
        if ($mode === 'base_plus_line') {
            return round($baseFee + $unitPrice * $quantity, 2);
        }
        if ($mode === 'weight_first_continue') {
            return round($unitPrice * $quantity, 2);
        }
        return round($unitPrice * $quantity, 2);
    }

    /** @param array<string, mixed> $wc */
    private function arComputeWeightFirstContinueCharge(float $weightKg, array $wc): float
    {
        $tiers = $wc['first_tiers'] ?? null;
        if (!is_array($tiers) || $tiers === [] || $weightKg <= 0) {
            return 0.0;
        }
        $step = (float)($wc['continue_step_kg'] ?? 0);
        $perStep = (float)($wc['continue_fee_per_step'] ?? 0);
        if ($step <= 0) {
            return 0.0;
        }
        $best = INF;
        foreach ($tiers as $t) {
            if (!is_array($t)) {
                continue;
            }
            $fk = (float)($t['first_kg'] ?? 0);
            $fee = (float)($t['fee'] ?? 0);
            if ($fk <= 0) {
                continue;
            }
            if ($weightKg <= $fk) {
                $candidate = $fee;
            } else {
                $rem = $weightKg - $fk;
                $steps = (int)ceil($rem / $step - 1e-9);
                if ($steps < 0) {
                    $steps = 0;
                }
                $candidate = $fee + $steps * $perStep;
            }
            if ($candidate < $best) {
                $best = $candidate;
            }
        }
        if (!is_finite($best)) {
            return 0.0;
        }
        return round($best, 2);
    }

    private function arMapAlgorithmToPricingMode(string $algorithm): string
    {
        if ($algorithm === 'base_plus_line') {
            return 'base_plus_line';
        }
        if ($algorithm === 'weight_first_continue') {
            return 'weight_first_continue';
        }
        return 'line_only';
    }

    /**
     * @param array<string, mixed> $schemeRow
     */
    private function arComputeSchemeAmount(array $schemeRow, float $quantity, float $postedUnitPrice, float $postedBaseFee): float
    {
        $algo = (string)($schemeRow['algorithm'] ?? 'qty_unit_price');
        $unitPrice = (float)($schemeRow['unit_price'] ?? 0);
        $baseFee = (float)($schemeRow['base_fee'] ?? 0);
        if ($algo === 'weight_first_continue') {
            $raw = $schemeRow['weight_config_json'] ?? null;
            if (is_array($raw)) {
                $wc = $raw;
            } elseif (is_string($raw) && $raw !== '') {
                $wc = json_decode($raw, true);
            } else {
                $wc = [];
            }
            if (!is_array($wc)) {
                $wc = [];
            }
            return $this->arComputeWeightFirstContinueCharge($quantity, $wc);
        }
        if ($algo === 'base_plus_line') {
            return round($postedBaseFee + $postedUnitPrice * $quantity, 2);
        }
        return round($postedUnitPrice * $quantity, 2);
    }

    /** @return array<string, mixed>|null */
    private function arFetchBillingScheme(mysqli $conn, int $schemeId, int $partyId): ?array
    {
        $stmt = $conn->prepare('SELECT * FROM ar_party_billing_schemes WHERE id = ? AND party_id = ? AND status = 1 LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $schemeId, $partyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    private function arPartyBillingSchemesForParty(mysqli $conn, int $partyId, bool $activeOnly = true): array
    {
        $rows = [];
        $sql = $activeOnly
            ? 'SELECT * FROM ar_party_billing_schemes WHERE party_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC'
            : 'SELECT * FROM ar_party_billing_schemes WHERE party_id = ? ORDER BY sort_order ASC, id ASC';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $partyId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $rows[] = $r;
            }
            $stmt->close();
        }
        return $rows;
    }

    private function arPartyHasBillingSchemes(mysqli $conn, int $partyId): bool
    {
        $stmt = $conn->prepare('SELECT id FROM ar_party_billing_schemes WHERE party_id = ? AND status = 1 LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $partyId);
        $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc() !== null;
        $stmt->close();
        return $ok;
    }

    private function arFloatsNearEqual(float $a, float $b, float $eps = 0.005): bool
    {
        return abs($a - $b) < $eps;
    }

    private function arCurrentBalance(mysqli $conn, int $partyId): float
    {
        $stmt = $conn->prepare('SELECT COALESCE(balance_after, 0) AS b FROM ar_receivable_ledger WHERE party_id = ? ORDER BY id DESC LIMIT 1');
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('i', $partyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float)($row['b'] ?? 0);
    }

    /** Excel（Windows）打开 UTF-8 CSV 时易误判编码，写入 BOM 可避免中文/泰文乱码 */
    private function csvWriteUtf8Bom($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
    }

    private function arWriteLedger(
        mysqli $conn,
        int $partyId,
        ?int $invoiceId,
        ?int $receivableId,
        ?int $transactionId,
        string $entryType,
        float $debitAmount,
        float $creditAmount,
        string $note,
        int $userId
    ): void {
        $before = $this->arCurrentBalance($conn, $partyId);
        $after = round($before + $debitAmount - $creditAmount, 2);
        $stmt = $conn->prepare('
            INSERT INTO ar_receivable_ledger
            (party_id, invoice_id, receivable_id, transaction_id, entry_type, debit_amount, credit_amount, balance_after, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmt) {
            $stmt->bind_param(
                'iiiisddssi',
                $partyId,
                $invoiceId,
                $receivableId,
                $transactionId,
                $entryType,
                $debitAmount,
                $creditAmount,
                $after,
                $note,
                $userId
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    public function transactionsCreate(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.transactions.create', 'finance.transactions.create', 'finance.manage'])) {
            $this->denyNoPermission('无权限新增财务记录');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $accounts = array_values(array_filter($this->activeAccounts($conn), static fn ($r) => (int)($r['status'] ?? 0) === 1));
        $categories = array_values(array_filter($this->activeCategories($conn), static fn ($r) => (int)($r['status'] ?? 0) === 1));
        $parties = $this->financeParties($conn, 'both');
        $error = '';
        $formData = [
            'type' => 'expense',
            'amount' => '',
            'client' => '',
            'party_id' => '',
            'category_id' => '',
            'account_id' => '',
            'description' => '',
        ];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_transaction'])) {
            $voucherFile = isset($_FILES['voucher']) && is_array($_FILES['voucher']) ? $_FILES['voucher'] : null;
            $voucherErr = $this->financeVoucherUploadPreflight($voucherFile);
            $type = trim((string)($_POST['type'] ?? 'expense'));
            $amount = (float)($_POST['amount'] ?? 0);
            $client = trim((string)($_POST['client'] ?? ''));
            $partyIdRaw = (int)($_POST['party_id'] ?? 0);
            $partyPick = $this->financePartyRowById($conn, $partyIdRaw);
            $partyIdForInsert = $partyPick ? $partyPick['id'] : null;
            if ($partyPick !== null) {
                $client = $partyPick['party_name'];
            }
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $accountId = (int)($_POST['account_id'] ?? 0);
            $description = trim((string)($_POST['description'] ?? ''));
            $formData = [
                'type' => $type,
                'amount' => $amount > 0 ? (string)$amount : '',
                'client' => $client,
                'party_id' => (string)$partyIdRaw,
                'category_id' => (string)$categoryId,
                'account_id' => (string)$accountId,
                'description' => $description,
            ];
            if ($voucherErr !== '') {
                $error = $voucherErr;
            } elseif (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $categoryId <= 0 || $accountId <= 0) {
                $error = '请填写正确的类型、金额、类目和账户';
            } else {
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $hasPartyRef = $this->columnExists($conn, 'transactions', 'party_id');
                if ($hasPartyRef) {
                    $insertSql = $partyIdForInsert === null
                        ? 'INSERT INTO transactions (type, amount, client, party_id, category_id, account_id, description, created_by) VALUES (?, ?, ?, NULL, ?, ?, ?, ?)'
                        : 'INSERT INTO transactions (type, amount, client, party_id, category_id, account_id, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
                } else {
                    $insertSql = 'INSERT INTO transactions (type, amount, client, category_id, account_id, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)';
                }
                $stmt = $conn->prepare($insertSql);
                if ($stmt) {
                    if ($hasPartyRef) {
                        if ($partyIdForInsert === null) {
                            $stmt->bind_param('sdsiisi', $type, $amount, $client, $categoryId, $accountId, $description, $userId);
                        } else {
                            $stmt->bind_param('sdsiiisi', $type, $amount, $client, $partyIdForInsert, $categoryId, $accountId, $description, $userId);
                        }
                    } else {
                        $stmt->bind_param('sdsiisi', $type, $amount, $client, $categoryId, $accountId, $description, $userId);
                    }
                    $stmt->execute();
                    $newId = (int)$stmt->insert_id;
                    $stmt->close();
                    if ($newId > 0 && $this->columnExists($conn, 'transactions', 'voucher_path')) {
                        $saved = $this->financeVoucherSaveFromUpload($voucherFile, 'trx_' . $newId);
                        if ($saved['ok'] && $saved['path'] !== null && $saved['path'] !== '') {
                            $vu = $conn->prepare('UPDATE transactions SET voucher_path = ? WHERE id = ? LIMIT 1');
                            if ($vu) {
                                $vp = $saved['path'];
                                $vu->bind_param('si', $vp, $newId);
                                $vu->execute();
                                $vu->close();
                            }
                        }
                    }
                    $this->writeAuditLog($conn, 'finance', 'finance.transactions.create', 'transaction', $newId, [
                        'type' => $type,
                        'amount' => $amount,
                    ]);
                    header('Location: /finance/transactions/list?msg=created');
                    exit;
                }
                $error = '保存失败，请稍后重试';
            }
        }
        $title = '财务管理 / 新增财务记录';
        $contentView = __DIR__ . '/../Views/finance/transactions/create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function transactionsList(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.transactions.list', 'finance.transactions.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看财务记录');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '财务记录新增成功';
        } elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
            $message = '财务记录已更新';
        }
        $typeFilter = trim((string)($_GET['type'] ?? 'all'));
        if (!in_array($typeFilter, ['all', 'income', 'expense'], true)) {
            $typeFilter = 'all';
        }
        $where = '1=1';
        $bindTypes = '';
        $bindValues = [];
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        if ($typeFilter !== 'all') {
            $where .= ' AND t.type = ?';
            $bindTypes .= 's';
            $bindValues[] = $typeFilter;
        }
        $total = 0;
        $countSql = 'SELECT COUNT(*) AS c FROM transactions t WHERE ' . $where;
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            if ($bindTypes !== '') {
                $bindParams = [];
                $bindParams[] = &$bindTypes;
                foreach ($bindValues as $idx => $val) {
                    $bindParams[] = &$bindValues[$idx];
                }
                call_user_func_array([$countStmt, 'bind_param'], $bindParams);
            }
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $hasPartyRef = $this->columnExists($conn, 'transactions', 'party_id') && $this->tableExists($conn, 'finance_parties');
        $partyJoin = $hasPartyRef ? 'LEFT JOIN finance_parties fp ON fp.id = t.party_id' : '';
        $clientExpr = $hasPartyRef ? "COALESCE(NULLIF(fp.party_name, ''), t.client)" : 't.client';
        $sql = '
            SELECT
                t.id, t.type, t.amount, ' . $clientExpr . ' AS client_display, t.description, t.created_at,
                t.voucher_path,
                c.name AS category_name, a.account_name,
                COALESCE(NULLIF(u.full_name, \'\'), u.username) AS creator
            FROM transactions t
            LEFT JOIN transaction_categories c ON c.id = t.category_id
            LEFT JOIN accounts a ON a.id = t.account_id
            ' . $partyJoin . '
            LEFT JOIN users u ON u.id = t.created_by
            WHERE ' . $where . '
            ORDER BY t.id DESC
            LIMIT ' . $offset . ', ' . $perPage . '
        ';
        $rows = [];
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($bindTypes !== '') {
                $bindParams = [];
                $bindParams[] = &$bindTypes;
                foreach ($bindValues as $idx => $val) {
                    $bindParams[] = &$bindValues[$idx];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        $title = '财务管理 / 财务记录列表';
        $contentView = __DIR__ . '/../Views/finance/transactions/list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function transactionsEdit(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.transactions.list', 'finance.transactions.edit', 'finance.manage'])) {
            $this->denyNoPermission('无权限编辑财务记录');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $transactionId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($transactionId <= 0) {
            header('Location: /finance/transactions/list');
            exit;
        }
        $accounts = array_values(array_filter($this->activeAccounts($conn), static fn ($r) => (int)($r['status'] ?? 0) === 1));
        $categories = array_values(array_filter($this->activeCategories($conn), static fn ($r) => (int)($r['status'] ?? 0) === 1));
        $row = null;
        $stmt = $conn->prepare('SELECT * FROM transactions WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $transactionId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$row) {
            header('Location: /finance/transactions/list');
            exit;
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction'])) {
            $type = trim((string)($_POST['type'] ?? 'expense'));
            $amount = (float)($_POST['amount'] ?? 0);
            $client = trim((string)($_POST['client'] ?? ''));
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $accountId = (int)($_POST['account_id'] ?? 0);
            $description = trim((string)($_POST['description'] ?? ''));
            if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $categoryId <= 0 || $accountId <= 0) {
                $error = '请填写正确的类型、金额、类目和账户';
            } else {
                $stmt = $conn->prepare('
                    UPDATE transactions
                    SET type = ?, amount = ?, client = ?, category_id = ?, account_id = ?, description = ?
                    WHERE id = ?
                    LIMIT 1
                ');
                if ($stmt) {
                    $stmt->bind_param('sdsiisi', $type, $amount, $client, $categoryId, $accountId, $description, $transactionId);
                    $stmt->execute();
                    $stmt->close();
                    $this->writeAuditLog($conn, 'finance', 'finance.transactions.edit', 'transaction', $transactionId);
                    header('Location: /finance/transactions/list?msg=updated');
                    exit;
                }
                $error = '保存失败';
            }
        }
        $title = '财务管理 / 编辑财务记录';
        $contentView = __DIR__ . '/../Views/finance/transactions/edit.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function payablesCreate(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.payables.create', 'finance.payables.create', 'finance.manage'])) {
            $this->denyNoPermission('无权限新增待付款');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        $error = '';
        $parties = $this->financeParties($conn, 'pay');
        $formData = [
            'vendor_name' => '',
            'party_id' => '',
            'amount' => '',
            'expected_pay_date' => date('Y-m-d'),
            'remark' => '',
        ];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payable'])) {
            $voucherFile = isset($_FILES['voucher']) && is_array($_FILES['voucher']) ? $_FILES['voucher'] : null;
            $voucherErr = $this->financeVoucherUploadPreflight($voucherFile);
            $vendorName = trim((string)($_POST['vendor_name'] ?? ''));
            $partyIdRaw = (int)($_POST['party_id'] ?? 0);
            $partyPick = $this->financePartyRowById($conn, $partyIdRaw);
            $partyIdForInsert = $partyPick ? $partyPick['id'] : null;
            if ($partyPick !== null) {
                $vendorName = $partyPick['party_name'];
            }
            $amount = (float)($_POST['amount'] ?? 0);
            $expectedPayDate = trim((string)($_POST['expected_pay_date'] ?? ''));
            $remark = trim((string)($_POST['remark'] ?? ''));
            $formData = [
                'vendor_name' => $vendorName,
                'party_id' => (string)$partyIdRaw,
                'amount' => $amount > 0 ? (string)$amount : '',
                'expected_pay_date' => $expectedPayDate,
                'remark' => $remark,
            ];
            if ($voucherErr !== '') {
                $error = $voucherErr;
            } elseif ($vendorName === '' || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedPayDate)) {
                $error = '请填写正确的厂商、金额与预计付款日';
            } else {
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $hasPartyRef = $this->columnExists($conn, 'payables', 'party_id');
                if ($hasPartyRef) {
                    $insertSql = $partyIdForInsert === null
                        ? 'INSERT INTO payables (vendor_name, party_id, amount, expected_pay_date, remark, status, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?)'
                        : 'INSERT INTO payables (vendor_name, party_id, amount, expected_pay_date, remark, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)';
                } else {
                    $insertSql = 'INSERT INTO payables (vendor_name, amount, expected_pay_date, remark, status, created_by) VALUES (?, ?, ?, ?, ?, ?)';
                }
                $stmt = $conn->prepare($insertSql);
                if ($stmt) {
                    $status = 'pending';
                    if ($hasPartyRef) {
                        if ($partyIdForInsert === null) {
                            $stmt->bind_param('sdsssi', $vendorName, $amount, $expectedPayDate, $remark, $status, $userId);
                        } else {
                            $stmt->bind_param('sidsssi', $vendorName, $partyIdForInsert, $amount, $expectedPayDate, $remark, $status, $userId);
                        }
                    } else {
                        $stmt->bind_param('sdsssi', $vendorName, $amount, $expectedPayDate, $remark, $status, $userId);
                    }
                    $stmt->execute();
                    $payableId = (int)$stmt->insert_id;
                    $stmt->close();
                    if ($payableId > 0 && $this->columnExists($conn, 'payables', 'voucher_path')) {
                        $saved = $this->financeVoucherSaveFromUpload($voucherFile, 'payable_' . $payableId);
                        if ($saved['ok'] && $saved['path'] !== null && $saved['path'] !== '') {
                            $vu = $conn->prepare('UPDATE payables SET voucher_path = ? WHERE id = ? LIMIT 1');
                            if ($vu) {
                                $vp = $saved['path'];
                                $vu->bind_param('si', $vp, $payableId);
                                $vu->execute();
                                $vu->close();
                            }
                        }
                    }
                    $this->writeAuditLog($conn, 'finance', 'finance.payables.create', 'payable', $payableId, [
                        'vendor_name' => $vendorName,
                        'amount' => $amount,
                    ]);
                    $this->sendFinanceNotification(
                        $conn,
                        'finance.payables.created',
                        $payableId,
                        $userId,
                        '待付款新增：' . $vendorName,
                        sprintf('金额 %.2f，预计付款日 %s', $amount, $expectedPayDate)
                    );
                    header('Location: /finance/payables/list?msg=created');
                    exit;
                }
                $error = '保存失败，请稍后重试';
            }
        }
        $title = '财务管理 / 新增待付款';
        $contentView = __DIR__ . '/../Views/finance/payables/create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function payablesList(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.payables.list', 'finance.payables.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看待付款');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '待付款新增成功';
        } elseif (isset($_GET['msg']) && $_GET['msg'] === 'settled') {
            $message = '待付款已确认付款';
        }
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));
        if (!in_array($statusFilter, ['all', 'pending', 'paid'], true)) {
            $statusFilter = 'all';
        }
        $dueFilter = trim((string)($_GET['due_filter'] ?? 'all'));
        if (!in_array($dueFilter, ['all', 'overdue'], true)) {
            $dueFilter = 'all';
        }
        $where = '1=1';
        $bindTypes = '';
        $bindValues = [];
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        if ($statusFilter !== 'all') {
            $where .= ' AND p.status = ?';
            $bindTypes .= 's';
            $bindValues[] = $statusFilter;
        }
        if ($dueFilter === 'overdue') {
            $where .= " AND p.status = 'pending' AND p.expected_pay_date < CURDATE()";
        }
        $total = 0;
        $countSql = 'SELECT COUNT(*) AS c FROM payables p WHERE ' . $where;
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            if ($bindTypes !== '') {
                $bindParams = [];
                $bindParams[] = &$bindTypes;
                foreach ($bindValues as $idx => $val) {
                    $bindParams[] = &$bindValues[$idx];
                }
                call_user_func_array([$countStmt, 'bind_param'], $bindParams);
            }
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $hasPartyRef = $this->columnExists($conn, 'payables', 'party_id') && $this->tableExists($conn, 'finance_parties');
        $partyJoin = $hasPartyRef ? 'LEFT JOIN finance_parties fp ON fp.id = p.party_id' : '';
        $vendorExpr = $hasPartyRef ? "COALESCE(NULLIF(fp.party_name, ''), p.vendor_name)" : 'p.vendor_name';
        $sql = '
            SELECT
                p.id, ' . $vendorExpr . ' AS vendor_display, p.amount, p.expected_pay_date, p.remark, p.status, p.paid_at, p.created_at,
                p.voucher_path,
                COALESCE(NULLIF(u.full_name, \'\'), u.username) AS creator
            FROM payables p
            ' . $partyJoin . '
            LEFT JOIN users u ON u.id = p.created_by
            WHERE ' . $where . '
            ORDER BY p.id DESC
            LIMIT ' . $offset . ', ' . $perPage . '
        ';
        $rows = [];
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($bindTypes !== '') {
                $bindParams = [];
                $bindParams[] = &$bindTypes;
                foreach ($bindValues as $idx => $val) {
                    $bindParams[] = &$bindValues[$idx];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $dueDate = (string)($row['expected_pay_date'] ?? '');
                $today = date('Y-m-d');
                $row['due_level'] = 'normal';
                $row['due_label'] = '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                    if ($dueDate < $today && (string)($row['status'] ?? '') === 'pending') {
                        $row['due_level'] = 'overdue';
                        $row['due_label'] = '已逾期';
                    } elseif ($dueDate === $today && (string)($row['status'] ?? '') === 'pending') {
                        $row['due_level'] = 'due_today';
                        $row['due_label'] = '今天到期';
                    }
                }
                $rows[] = $row;
            }
            $stmt->close();
        }
        $title = '财务管理 / 待付款列表';
        $contentView = __DIR__ . '/../Views/finance/payables/list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function payablesSettle(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.payables.list', 'finance.payables.settle', 'finance.manage'])) {
            $this->denyNoPermission('无权限确认付款');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $payableId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($payableId <= 0) {
            header('Location: /finance/payables/list');
            exit;
        }
        $payable = null;
        $stmt = $conn->prepare('SELECT * FROM payables WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $payableId);
            $stmt->execute();
            $payable = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$payable) {
            header('Location: /finance/payables/list');
            exit;
        }
        if ((string)($payable['status'] ?? '') === 'paid') {
            header('Location: /finance/payables/list?msg=settled');
            exit;
        }
        $accounts = [];
        $categories = [];
        $resAccount = $conn->query('SELECT id, account_name FROM accounts WHERE status = 1 ORDER BY id DESC');
        while ($resAccount && ($row = $resAccount->fetch_assoc())) {
            $accounts[] = $row;
        }
        $resCat = $conn->query("SELECT id, name FROM transaction_categories WHERE status = 1 AND type = 'expense' ORDER BY id DESC");
        while ($resCat && ($row = $resCat->fetch_assoc())) {
            $categories[] = $row;
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_payable'])) {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $settleNote = trim((string)($_POST['settle_note'] ?? ''));
            $existingPayableVoucher = trim((string)($payable['voucher_path'] ?? ''));
            $voucherFile = isset($_FILES['voucher']) && is_array($_FILES['voucher']) ? $_FILES['voucher'] : null;
            $voucherErr = $existingPayableVoucher === '' ? $this->financeVoucherUploadPreflight($voucherFile) : '';
            if ($voucherErr !== '') {
                $error = $voucherErr;
            } elseif ($accountId <= 0 || $categoryId <= 0) {
                $error = '请选择付款账户和支出类目';
            } else {
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $hasVoucherColTrx = $this->columnExists($conn, 'transactions', 'voucher_path');
                $hasVoucherColPay = $this->columnExists($conn, 'payables', 'voucher_path');
                $conn->begin_transaction();
                try {
                    $type = 'expense';
                    $amount = (float)($payable['amount'] ?? 0);
                    $client = (string)($payable['vendor_name'] ?? '');
                    $description = '待付款转支出 #' . $payableId . ' ' . (string)($payable['remark'] ?? '');
                    if ($settleNote !== '') {
                        $description .= '；确认说明：' . $settleNote;
                    }
                    $insertStmt = $conn->prepare('
                        INSERT INTO transactions (type, amount, client, category_id, account_id, description, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ');
                    if (!$insertStmt) {
                        throw new RuntimeException('insert transaction failed');
                    }
                    $insertStmt->bind_param('sdsiisi', $type, $amount, $client, $categoryId, $accountId, $description, $userId);
                    $insertStmt->execute();
                    $transactionId = (int)$insertStmt->insert_id;
                    $insertStmt->close();
                    $pathForTrx = null;
                    if ($hasVoucherColTrx) {
                        if ($existingPayableVoucher !== '') {
                            $pathForTrx = $existingPayableVoucher;
                        } else {
                            $saved = $this->financeVoucherSaveFromUpload($voucherFile, 'trx_' . $transactionId);
                            if (!$saved['ok']) {
                                throw new RuntimeException('voucher');
                            }
                            $pathForTrx = $saved['path'];
                        }
                        if ($pathForTrx !== null && $pathForTrx !== '') {
                            $vu = $conn->prepare('UPDATE transactions SET voucher_path = ? WHERE id = ? LIMIT 1');
                            if (!$vu) {
                                throw new RuntimeException('voucher');
                            }
                            $vu->bind_param('si', $pathForTrx, $transactionId);
                            $vu->execute();
                            $vu->close();
                        }
                    }
                    if ($hasVoucherColPay && $existingPayableVoucher === '' && $pathForTrx !== null && $pathForTrx !== '') {
                        $vp2 = $conn->prepare('UPDATE payables SET voucher_path = ? WHERE id = ? LIMIT 1');
                        if ($vp2) {
                            $vp2->bind_param('si', $pathForTrx, $payableId);
                            $vp2->execute();
                            $vp2->close();
                        }
                    }
                    $status = 'paid';
                    $updateStmt = $conn->prepare('
                        UPDATE payables SET status = ?, paid_at = CURRENT_TIMESTAMP, paid_transaction_id = ?
                        WHERE id = ? LIMIT 1
                    ');
                    if (!$updateStmt) {
                        throw new RuntimeException('update payable failed');
                    }
                    $updateStmt->bind_param('sii', $status, $transactionId, $payableId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $conn->commit();
                    $this->writeAuditLog($conn, 'finance', 'finance.payables.settle', 'payable', $payableId, [
                        'transaction_id' => $transactionId,
                    ]);
                    $this->sendFinanceNotification(
                        $conn,
                        'finance.payables.settled',
                        $payableId,
                        $userId,
                        '待付款已付款：' . (string)$payable['vendor_name'],
                        sprintf('金额 %.2f，已转支出单 #%d', (float)$payable['amount'], $transactionId)
                    );
                    header('Location: /finance/payables/list?msg=settled');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = '确认付款失败，请稍后重试';
                }
            }
        }
        $title = '财务管理 / 确认付款';
        $contentView = __DIR__ . '/../Views/finance/payables/settle.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function receivablesCreate(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.receivables.create', 'finance.receivables.create', 'finance.manage'])) {
            $this->denyNoPermission('无权限新增待收款');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        $error = '';
        $parties = $this->financeParties($conn, 'receive');
        $formData = [
            'client_name' => '',
            'party_id' => '',
            'amount' => '',
            'expected_receive_date' => date('Y-m-d'),
            'remark' => '',
        ];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_receivable'])) {
            $voucherFile = isset($_FILES['voucher']) && is_array($_FILES['voucher']) ? $_FILES['voucher'] : null;
            $voucherErr = $this->financeVoucherUploadPreflight($voucherFile);
            $clientName = trim((string)($_POST['client_name'] ?? ''));
            $partyIdRaw = (int)($_POST['party_id'] ?? 0);
            $partyPick = $this->financePartyRowById($conn, $partyIdRaw);
            $partyIdForInsert = $partyPick ? $partyPick['id'] : null;
            if ($partyPick !== null) {
                $clientName = $partyPick['party_name'];
            }
            $amount = (float)($_POST['amount'] ?? 0);
            $expectedReceiveDate = trim((string)($_POST['expected_receive_date'] ?? ''));
            $remark = trim((string)($_POST['remark'] ?? ''));
            $formData = [
                'client_name' => $clientName,
                'party_id' => (string)$partyIdRaw,
                'amount' => $amount > 0 ? (string)$amount : '',
                'expected_receive_date' => $expectedReceiveDate,
                'remark' => $remark,
            ];
            if ($voucherErr !== '') {
                $error = $voucherErr;
            } elseif ($clientName === '' || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedReceiveDate)) {
                $error = '请填写正确的客户、金额与预计收款日';
            } else {
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $hasPartyRef = $this->columnExists($conn, 'receivables', 'party_id');
                if ($hasPartyRef) {
                    $insertSql = $partyIdForInsert === null
                        ? 'INSERT INTO receivables (client_name, party_id, amount, expected_receive_date, remark, status, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?)'
                        : 'INSERT INTO receivables (client_name, party_id, amount, expected_receive_date, remark, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)';
                } else {
                    $insertSql = 'INSERT INTO receivables (client_name, amount, expected_receive_date, remark, status, created_by) VALUES (?, ?, ?, ?, ?, ?)';
                }
                $stmt = $conn->prepare($insertSql);
                if ($stmt) {
                    $status = 'pending';
                    if ($hasPartyRef) {
                        if ($partyIdForInsert === null) {
                            $stmt->bind_param('sdsssi', $clientName, $amount, $expectedReceiveDate, $remark, $status, $userId);
                        } else {
                            $stmt->bind_param('sidsssi', $clientName, $partyIdForInsert, $amount, $expectedReceiveDate, $remark, $status, $userId);
                        }
                    } else {
                        $stmt->bind_param('sdsssi', $clientName, $amount, $expectedReceiveDate, $remark, $status, $userId);
                    }
                    $stmt->execute();
                    $receivableId = (int)$stmt->insert_id;
                    $stmt->close();
                    if ($receivableId > 0 && $this->columnExists($conn, 'receivables', 'voucher_path')) {
                        $saved = $this->financeVoucherSaveFromUpload($voucherFile, 'recv_' . $receivableId);
                        if ($saved['ok'] && $saved['path'] !== null && $saved['path'] !== '') {
                            $vu = $conn->prepare('UPDATE receivables SET voucher_path = ? WHERE id = ? LIMIT 1');
                            if ($vu) {
                                $vp = $saved['path'];
                                $vu->bind_param('si', $vp, $receivableId);
                                $vu->execute();
                                $vu->close();
                            }
                        }
                    }
                    $this->writeAuditLog($conn, 'finance', 'finance.receivables.create', 'receivable', $receivableId, [
                        'client_name' => $clientName,
                        'amount' => $amount,
                    ]);
                    $this->sendFinanceNotification(
                        $conn,
                        'finance.receivables.created',
                        $receivableId,
                        $userId,
                        '待收款新增：' . $clientName,
                        sprintf('金额 %.2f，预计收款日 %s', $amount, $expectedReceiveDate)
                    );
                    header('Location: /finance/receivables/list?msg=created');
                    exit;
                }
                $error = '保存失败，请稍后重试';
            }
        }
        $title = '财务管理 / 新增待收款';
        $contentView = __DIR__ . '/../Views/finance/receivables/create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function receivablesList(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.receivables.list', 'finance.receivables.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看待收款');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '待收款新增成功';
        } elseif (isset($_GET['msg']) && $_GET['msg'] === 'settled') {
            $message = '待收款已确认收款';
        }
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));
        if (!in_array($statusFilter, ['all', 'pending', 'received'], true)) {
            $statusFilter = 'all';
        }
        $dueFilter = trim((string)($_GET['due_filter'] ?? 'all'));
        if (!in_array($dueFilter, ['all', 'overdue'], true)) {
            $dueFilter = 'all';
        }
        $where = '1=1';
        $bindTypes = '';
        $bindValues = [];
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        if ($statusFilter !== 'all') {
            $where .= ' AND r.status = ?';
            $bindTypes .= 's';
            $bindValues[] = $statusFilter;
        }
        if ($dueFilter === 'overdue') {
            $where .= " AND r.status = 'pending' AND r.expected_receive_date < CURDATE()";
        }
        $total = 0;
        $countSql = 'SELECT COUNT(*) AS c FROM receivables r WHERE ' . $where;
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            if ($bindTypes !== '') {
                $bindParams = [];
                $bindParams[] = &$bindTypes;
                foreach ($bindValues as $idx => $val) {
                    $bindParams[] = &$bindValues[$idx];
                }
                call_user_func_array([$countStmt, 'bind_param'], $bindParams);
            }
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $hasPartyRef = $this->columnExists($conn, 'receivables', 'party_id') && $this->tableExists($conn, 'finance_parties');
        $partyJoin = $hasPartyRef ? 'LEFT JOIN finance_parties fp ON fp.id = r.party_id' : '';
        $clientExpr = $hasPartyRef ? "COALESCE(NULLIF(fp.party_name, ''), r.client_name)" : 'r.client_name';
        $sql = '
            SELECT
                r.id, ' . $clientExpr . ' AS client_display, r.amount, r.expected_receive_date, r.remark, r.status, r.received_at, r.created_at,
                r.voucher_path,
                COALESCE(NULLIF(u.full_name, \'\'), u.username) AS creator
            FROM receivables r
            ' . $partyJoin . '
            LEFT JOIN users u ON u.id = r.created_by
            WHERE ' . $where . '
            ORDER BY r.id DESC
            LIMIT ' . $offset . ', ' . $perPage . '
        ';
        $rows = [];
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($bindTypes !== '') {
                $bindParams = [];
                $bindParams[] = &$bindTypes;
                foreach ($bindValues as $idx => $val) {
                    $bindParams[] = &$bindValues[$idx];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $dueDate = (string)($row['expected_receive_date'] ?? '');
                $today = date('Y-m-d');
                $row['due_level'] = 'normal';
                $row['due_label'] = '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                    if ($dueDate < $today && (string)($row['status'] ?? '') === 'pending') {
                        $row['due_level'] = 'overdue';
                        $row['due_label'] = '已逾期';
                    } elseif ($dueDate === $today && (string)($row['status'] ?? '') === 'pending') {
                        $row['due_level'] = 'due_today';
                        $row['due_label'] = '今天到期';
                    }
                }
                $rows[] = $row;
            }
            $stmt->close();
        }
        $title = '财务管理 / 待收款列表';
        $contentView = __DIR__ . '/../Views/finance/receivables/list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function receivablesSettle(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.receivables.list', 'finance.receivables.settle', 'finance.manage'])) {
            $this->denyNoPermission('无权限确认收款');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $receivableId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($receivableId <= 0) {
            header('Location: /finance/receivables/list');
            exit;
        }
        $receivable = null;
        $stmt = $conn->prepare('SELECT * FROM receivables WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $receivableId);
            $stmt->execute();
            $receivable = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$receivable) {
            header('Location: /finance/receivables/list');
            exit;
        }
        if ((string)($receivable['status'] ?? '') === 'received') {
            header('Location: /finance/receivables/list?msg=settled');
            exit;
        }
        $accounts = [];
        $categories = [];
        $resAccount = $conn->query('SELECT id, account_name FROM accounts WHERE status = 1 ORDER BY id DESC');
        while ($resAccount && ($row = $resAccount->fetch_assoc())) {
            $accounts[] = $row;
        }
        $resCat = $conn->query("SELECT id, name FROM transaction_categories WHERE status = 1 AND type = 'income' ORDER BY id DESC");
        while ($resCat && ($row = $resCat->fetch_assoc())) {
            $categories[] = $row;
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_receivable'])) {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $settleNote = trim((string)($_POST['settle_note'] ?? ''));
            $existingRecvVoucher = trim((string)($receivable['voucher_path'] ?? ''));
            $voucherFile = isset($_FILES['voucher']) && is_array($_FILES['voucher']) ? $_FILES['voucher'] : null;
            $voucherErr = $existingRecvVoucher === '' ? $this->financeVoucherUploadPreflight($voucherFile) : '';
            if ($voucherErr !== '') {
                $error = $voucherErr;
            } elseif ($accountId <= 0 || $categoryId <= 0) {
                $error = '请选择收款账户和收入类目';
            } else {
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $hasVoucherColTrx = $this->columnExists($conn, 'transactions', 'voucher_path');
                $hasVoucherColRecv = $this->columnExists($conn, 'receivables', 'voucher_path');
                $conn->begin_transaction();
                try {
                    $type = 'income';
                    $amount = (float)($receivable['amount'] ?? 0);
                    $client = (string)($receivable['client_name'] ?? '');
                    $description = '待收款转收入 #' . $receivableId . ' ' . (string)($receivable['remark'] ?? '');
                    if ($settleNote !== '') {
                        $description .= '；确认说明：' . $settleNote;
                    }
                    $insertStmt = $conn->prepare('
                        INSERT INTO transactions (type, amount, client, category_id, account_id, description, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ');
                    if (!$insertStmt) {
                        throw new RuntimeException('insert transaction failed');
                    }
                    $insertStmt->bind_param('sdsiisi', $type, $amount, $client, $categoryId, $accountId, $description, $userId);
                    $insertStmt->execute();
                    $transactionId = (int)$insertStmt->insert_id;
                    $insertStmt->close();
                    $pathForTrx = null;
                    if ($hasVoucherColTrx) {
                        if ($existingRecvVoucher !== '') {
                            $pathForTrx = $existingRecvVoucher;
                        } else {
                            $saved = $this->financeVoucherSaveFromUpload($voucherFile, 'trx_' . $transactionId);
                            if (!$saved['ok']) {
                                throw new RuntimeException('voucher');
                            }
                            $pathForTrx = $saved['path'];
                        }
                        if ($pathForTrx !== null && $pathForTrx !== '') {
                            $vu = $conn->prepare('UPDATE transactions SET voucher_path = ? WHERE id = ? LIMIT 1');
                            if (!$vu) {
                                throw new RuntimeException('voucher');
                            }
                            $vu->bind_param('si', $pathForTrx, $transactionId);
                            $vu->execute();
                            $vu->close();
                        }
                    }
                    if ($hasVoucherColRecv && $existingRecvVoucher === '' && $pathForTrx !== null && $pathForTrx !== '') {
                        $vr2 = $conn->prepare('UPDATE receivables SET voucher_path = ? WHERE id = ? LIMIT 1');
                        if ($vr2) {
                            $vr2->bind_param('si', $pathForTrx, $receivableId);
                            $vr2->execute();
                            $vr2->close();
                        }
                    }
                    $status = 'received';
                    $updateStmt = $conn->prepare('
                        UPDATE receivables SET status = ?, received_at = CURRENT_TIMESTAMP, received_transaction_id = ?
                        WHERE id = ? LIMIT 1
                    ');
                    if (!$updateStmt) {
                        throw new RuntimeException('update receivable failed');
                    }
                    $updateStmt->bind_param('sii', $status, $transactionId, $receivableId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $arInvoiceId = (int)($receivable['ar_invoice_id'] ?? 0);
                    $partyId = (int)($receivable['party_id'] ?? 0);
                    if ($arInvoiceId > 0 && $partyId > 0 && $this->tableExists($conn, 'ar_receivable_ledger')) {
                        $this->arWriteLedger(
                            $conn,
                            $partyId,
                            $arInvoiceId,
                            $receivableId,
                            $transactionId,
                            'credit',
                            0.0,
                            (float)$amount,
                            '收款冲销应收',
                            $userId
                        );
                        if ($this->tableExists($conn, 'ar_invoices')) {
                            $invoiceStatus = 'paid';
                            $updateInvoiceStmt = $conn->prepare('UPDATE ar_invoices SET status = ? WHERE id = ? LIMIT 1');
                            if ($updateInvoiceStmt) {
                                $updateInvoiceStmt->bind_param('si', $invoiceStatus, $arInvoiceId);
                                $updateInvoiceStmt->execute();
                                $updateInvoiceStmt->close();
                            }
                        }
                        $this->sendFinanceNotification(
                            $conn,
                            'finance.ar.invoice.settled',
                            $arInvoiceId,
                            $userId,
                            '应收账单已冲销',
                            sprintf('待收款 #%d 已收款，金额 %.2f', $receivableId, (float)$amount)
                        );
                    }
                    $conn->commit();
                    $this->writeAuditLog($conn, 'finance', 'finance.receivables.settle', 'receivable', $receivableId, [
                        'transaction_id' => $transactionId,
                    ]);
                    $this->sendFinanceNotification(
                        $conn,
                        'finance.receivables.settled',
                        $receivableId,
                        $userId,
                        '待收款已收款：' . (string)$receivable['client_name'],
                        sprintf('金额 %.2f，已转收入单 #%d', (float)$receivable['amount'], $transactionId)
                    );
                    header('Location: /finance/receivables/list?msg=settled');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = '确认收款失败，请稍后重试';
                }
            }
        }
        $title = '财务管理 / 确认收款';
        $contentView = __DIR__ . '/../Views/finance/receivables/settle.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arBillingSchemes(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.customers', 'menu.nav.finance.ar.billing_schemes', 'finance.ar.customers', 'finance.manage'])) {
            $this->denyNoPermission('无权限维护计费方式');
        }
        $canSchemeCreate = $this->hasAnyPermission(['menu.nav.finance.ar.billing_schemes', 'finance.ar.billing_scheme.create', 'finance.ar.customers', 'finance.manage']);
        $canSchemeToggle = $this->hasAnyPermission(['menu.nav.finance.ar.billing_schemes', 'finance.ar.billing_scheme.toggle', 'finance.ar.customers', 'finance.manage']);
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $message = '';
        $error = '';
        $algoCatalogue = $this->arSchemeAlgorithmCatalogue();
        $parties = $this->financeParties($conn, 'receive');
        $partyId = (int)($_GET['party_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ar_billing_scheme'])) {
            if (!$canSchemeCreate) {
                $this->denyNoPermission('无权限新增计费方式');
            }
            $partyId = (int)($_POST['party_id'] ?? 0);
            $label = trim((string)($_POST['scheme_label'] ?? ''));
            $algorithm = trim((string)($_POST['algorithm'] ?? ''));
            $unitName = trim((string)($_POST['unit_name'] ?? ''));
            $unitPrice = (float)($_POST['unit_price'] ?? 0);
            $baseFee = (float)($_POST['base_fee'] ?? 0);
            $basis = trim((string)($_POST['chargeable_weight_basis'] ?? 'actual'));
            $stepKg = (float)($_POST['continue_step_kg'] ?? 0);
            $perStep = (float)($_POST['continue_fee_per_step'] ?? 0);
            $validAlgo = array_keys($algoCatalogue);
            if ($partyId <= 0 || $label === '' || !in_array($algorithm, $validAlgo, true)) {
                $error = '请填写客户、方案名称并选择算法';
            } elseif ($unitName === '' || mb_strlen($unitName) > 40) {
                $error = '请填写计费单位（不超过 40 字）';
            } elseif ($unitPrice < 0 || $baseFee < 0) {
                $error = '单价与基础费用不能为负数';
            } else {
                $weightJson = null;
                $storeUnitPrice = $unitPrice;
                if ($algorithm === 'weight_first_continue') {
                    $tiers = [];
                    for ($i = 1; $i <= 3; $i++) {
                        $fk = (float)($_POST['first_kg_' . $i] ?? 0);
                        $ff = (float)($_POST['first_fee_' . $i] ?? 0);
                        if ($fk > 0 && $ff >= 0) {
                            $tiers[] = ['first_kg' => $fk, 'fee' => $ff];
                        }
                    }
                    if ($tiers === [] || $stepKg <= 0) {
                        $error = '首续重模式至少填写一组首重（公斤+费用），且续重步长公斤须大于 0';
                    } elseif ($perStep < 0) {
                        $error = '续重每步费用不能为负数';
                    } else {
                        if (!in_array($basis, ['actual', 'volumetric', 'max_of_both'], true)) {
                            $basis = 'actual';
                        }
                        $weightJson = json_encode([
                            'chargeable_weight_basis' => $basis,
                            'first_tiers' => $tiers,
                            'continue_step_kg' => $stepKg,
                            'continue_fee_per_step' => $perStep,
                        ], JSON_UNESCAPED_UNICODE);
                        $storeUnitPrice = $perStep;
                    }
                }
                if ($error === '') {
                    $maxSort = 0;
                    $ms = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) AS m FROM ar_party_billing_schemes WHERE party_id = ?');
                    if ($ms) {
                        $ms->bind_param('i', $partyId);
                        $ms->execute();
                        $maxSort = (int)(($ms->get_result()->fetch_assoc())['m'] ?? 0);
                        $ms->close();
                    }
                    $nextSort = $maxSort + 10;
                    $ins = null;
                    if ($weightJson === null) {
                        $ins = $conn->prepare('
                            INSERT INTO ar_party_billing_schemes (party_id, scheme_label, algorithm, unit_name, unit_price, base_fee, weight_config_json, sort_order, status)
                            VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 1)
                        ');
                        if ($ins) {
                            $ins->bind_param('isssddi', $partyId, $label, $algorithm, $unitName, $storeUnitPrice, $baseFee, $nextSort);
                        }
                    } else {
                        $ins = $conn->prepare('
                            INSERT INTO ar_party_billing_schemes (party_id, scheme_label, algorithm, unit_name, unit_price, base_fee, weight_config_json, sort_order, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ');
                        if ($ins) {
                            $ins->bind_param('isssddsi', $partyId, $label, $algorithm, $unitName, $storeUnitPrice, $baseFee, $weightJson, $nextSort);
                        }
                    }
                    if ($ins) {
                        $ins->execute();
                        $newId = (int)$ins->insert_id;
                        $ins->close();
                        $message = '已新增计费方式';
                        $this->writeAuditLog($conn, 'finance', 'finance.ar.billing_scheme.add', 'ar_party_billing_schemes', $newId, ['party_id' => $partyId, 'label' => $label]);
                    } else {
                        $error = '保存失败，请稍后重试';
                    }
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ar_billing_scheme'])) {
            if (!$canSchemeToggle) {
                $this->denyNoPermission('无权限修改计费方式状态');
            }
            $sid = (int)($_POST['scheme_id'] ?? 0);
            $partyId = (int)($_POST['party_id'] ?? $partyId);
            if ($sid > 0) {
                $tg = $conn->prepare('UPDATE ar_party_billing_schemes SET status = IF(status = 1, 0, 1) WHERE id = ? LIMIT 1');
                if ($tg) {
                    $tg->bind_param('i', $sid);
                    $tg->execute();
                    $affected = (int)$tg->affected_rows;
                    if ($tg->affected_rows > 0) {
                        $message = '已更新方案启用状态';
                    }
                    $tg->close();
                    if ($affected > 0) {
                        $this->writeAuditLog($conn, 'finance', 'finance.ar.billing_scheme.toggle', 'ar_party_billing_schemes', $sid, [
                            'party_id' => $partyId,
                        ]);
                    }
                }
            }
        }
        $schemeRows = [];
        if ($partyId > 0) {
            $schemeRows = $this->arPartyBillingSchemesForParty($conn, $partyId, false);
        }
        $title = '财务管理 / 应收账单 / 计费方式维护';
        $contentView = __DIR__ . '/../Views/finance/ar/billing_schemes.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arCustomers(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.customers', 'menu.nav.finance.ar.billing_schemes', 'finance.ar.customers', 'finance.manage'])) {
            $this->denyNoPermission('无权限管理应收客户档案');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $message = '';
        $error = '';
        $parties = $this->financeParties($conn, 'receive');
        $pricingModeCatalogue = $this->arPricingModeCatalogue();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ar_customer'])) {
            $partyId = (int)($_POST['party_id'] ?? 0);
            $currency = 'THB';
            $taxMode = trim((string)($_POST['tax_mode'] ?? 'excluded'));
            $billingCycle = trim((string)($_POST['billing_cycle'] ?? 'monthly'));
            $defaultSchemeId = (int)($_POST['default_billing_scheme_id'] ?? 0);
            $legacyPricingMode = trim((string)($_POST['legacy_pricing_mode'] ?? 'line_only'));
            if (!in_array($legacyPricingMode, ['line_only', 'base_plus_line'], true)) {
                $legacyPricingMode = 'line_only';
            }
            $hasSchemes = $this->arPartyHasBillingSchemes($conn, $partyId);
            if ($partyId <= 0) {
                $error = '请选择客户';
            } elseif ($hasSchemes) {
                if ($defaultSchemeId <= 0) {
                    $error = '该客户已维护计费方式方案，请在「默认计费方式」中选择一项';
                } elseif (!$this->arFetchBillingScheme($conn, $defaultSchemeId, $partyId)) {
                    $error = '默认计费方式无效或已停用，请重新选择';
                } else {
                    $config = [
                        'default_billing_scheme_id' => $defaultSchemeId,
                        'pricing_modes' => [],
                    ];
                    $json = json_encode($config, JSON_UNESCAPED_UNICODE);
                    $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                    $stmt = $conn->prepare('
                        INSERT INTO ar_customer_profiles (party_id, currency, tax_mode, billing_cycle, formula_config_json, status, created_by)
                        VALUES (?, ?, ?, ?, ?, 1, ?)
                        ON DUPLICATE KEY UPDATE
                            currency = VALUES(currency),
                            tax_mode = VALUES(tax_mode),
                            billing_cycle = VALUES(billing_cycle),
                            formula_config_json = VALUES(formula_config_json),
                            status = 1,
                            updated_at = CURRENT_TIMESTAMP
                    ');
                    if ($stmt) {
                        $stmt->bind_param('issssi', $partyId, $currency, $taxMode, $billingCycle, $json, $userId);
                        $stmt->execute();
                        $stmt->close();
                        $message = '客户计费档案已保存';
                        $this->writeAuditLog($conn, 'finance', 'finance.ar.customers.save', 'party', $partyId, ['default_billing_scheme_id' => $defaultSchemeId]);
                    } else {
                        $error = '保存失败，请稍后重试';
                    }
                }
            } else {
                $config = [
                    'default_billing_scheme_id' => null,
                    'pricing_modes' => [$legacyPricingMode],
                ];
                $json = json_encode($config, JSON_UNESCAPED_UNICODE);
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $stmt = $conn->prepare('
                    INSERT INTO ar_customer_profiles (party_id, currency, tax_mode, billing_cycle, formula_config_json, status, created_by)
                    VALUES (?, ?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        currency = VALUES(currency),
                        tax_mode = VALUES(tax_mode),
                        billing_cycle = VALUES(billing_cycle),
                        formula_config_json = VALUES(formula_config_json),
                        status = 1,
                        updated_at = CURRENT_TIMESTAMP
                ');
                if ($stmt) {
                    $stmt->bind_param('issssi', $partyId, $currency, $taxMode, $billingCycle, $json, $userId);
                    $stmt->execute();
                    $stmt->close();
                    $message = '客户计费档案已保存（尚未维护计费方式方案时，使用下方「兼容计费形态」）';
                    $this->writeAuditLog($conn, 'finance', 'finance.ar.customers.save', 'party', $partyId, ['pricing_modes' => [$legacyPricingMode]]);
                } else {
                    $error = '保存失败，请稍后重试';
                }
            }
        }
        $schemeCounts = [];
        $scRes = $conn->query('SELECT party_id, COUNT(*) AS c FROM ar_party_billing_schemes WHERE status = 1 GROUP BY party_id');
        while ($scRes && ($sr = $scRes->fetch_assoc())) {
            $schemeCounts[(int)$sr['party_id']] = (int)($sr['c'] ?? 0);
        }
        $rows = [];
        $res = $conn->query("
            SELECT p.id AS party_id, p.party_name, p.party_kind,
                   ap.id AS profile_id, ap.currency, ap.tax_mode, ap.billing_cycle, ap.formula_config_json, ap.updated_at
            FROM finance_parties p
            LEFT JOIN ar_customer_profiles ap ON ap.party_id = p.id
            WHERE p.status = 1 AND p.party_kind IN ('receive', 'both')
            ORDER BY p.party_name ASC, p.id ASC
        ");
        $allDefaultSchemeIds = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $pid = (int)($row['party_id'] ?? 0);
            $row['scheme_count'] = $schemeCounts[$pid] ?? 0;
            $row['default_scheme_label'] = '';
            if ((int)($row['profile_id'] ?? 0) > 0) {
                $cfg = json_decode((string)($row['formula_config_json'] ?? ''), true);
                $norm = $this->arNormalizeProfileConfig($cfg);
                $dsid = (int)($norm['default_billing_scheme_id'] ?? 0);
                if ($dsid > 0) {
                    $allDefaultSchemeIds[] = $dsid;
                }
            }
            $rows[] = $row;
        }
        $schemeLabelById = [];
        if ($allDefaultSchemeIds !== []) {
            $allDefaultSchemeIds = array_values(array_unique(array_filter($allDefaultSchemeIds)));
            $inList = implode(',', array_map('intval', $allDefaultSchemeIds));
            if ($inList !== '') {
                $lr = $conn->query('SELECT id, scheme_label FROM ar_party_billing_schemes WHERE id IN (' . $inList . ')');
                while ($lr && ($z = $lr->fetch_assoc())) {
                    $schemeLabelById[(int)$z['id']] = (string)($z['scheme_label'] ?? '');
                }
            }
        }
        foreach ($rows as &$row) {
            if ((int)($row['profile_id'] ?? 0) <= 0) {
                $row['pricing_mode_labels'] = '未建档';
                continue;
            }
            $cfg = json_decode((string)($row['formula_config_json'] ?? ''), true);
            $norm = $this->arNormalizeProfileConfig($cfg);
            $dsid = (int)($norm['default_billing_scheme_id'] ?? 0);
            if ($dsid > 0 && isset($schemeLabelById[$dsid])) {
                $row['default_scheme_label'] = $schemeLabelById[$dsid];
            }
            if (($row['scheme_count'] ?? 0) > 0) {
                $row['pricing_mode_labels'] = '默认：' . ($row['default_scheme_label'] !== '' ? $row['default_scheme_label'] : '（未选）')
                    . ' ｜ 已维护方案 ' . (int)$row['scheme_count'] . ' 个';
            } else {
                $labels = [];
                foreach ($norm['pricing_modes'] as $key) {
                    $labels[] = $pricingModeCatalogue[$key] ?? $key;
                }
                $row['pricing_mode_labels'] = $labels !== [] ? implode('；', $labels) : '按量计价（单价 × 数量）';
            }
        }
        unset($row);
        $partySchemesForForm = [];
        foreach ($parties as $party) {
            $ppid = (int)$party['id'];
            $partySchemesForForm[$ppid] = $this->arPartyBillingSchemesForParty($conn, $ppid, true);
        }
        $title = '财务管理 / 应收账单 / 客户计费档案';
        $contentView = __DIR__ . '/../Views/finance/ar/customers.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arChargesOptions(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.charges.create', 'finance.ar.charges.create', 'finance.manage'])) {
            $this->denyNoPermission('无权限维护费用类目与计费单位');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $error = '';
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ar_dropdown_option'])) {
                $group = trim((string)($_POST['option_group'] ?? ''));
                $name = trim((string)($_POST['name'] ?? ''));
                if (!in_array($group, ['category', 'unit'], true)) {
                    $error = '无效的选项分组';
                } elseif ($name === '' || mb_strlen($name) > 100) {
                    $error = '名称不能为空且不超过 100 字';
                } elseif ($this->arChargeOptionNameExists($conn, $group, $name)) {
                    $error = '该名称已存在';
                } else {
                    $maxSort = 0;
                    $ms = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) AS m FROM ar_charge_dropdown_options WHERE option_group = ?');
                    if ($ms) {
                        $ms->bind_param('s', $group);
                        $ms->execute();
                        $mr = $ms->get_result()->fetch_assoc();
                        $maxSort = (int)($mr['m'] ?? 0);
                        $ms->close();
                    }
                    $nextSort = $maxSort + 10;
                    $ins = $conn->prepare('INSERT INTO ar_charge_dropdown_options (option_group, name, sort_order, status) VALUES (?, ?, ?, 1)');
                    if ($ins) {
                        $ins->bind_param('ssi', $group, $name, $nextSort);
                        $ins->execute();
                        $newOptId = (int)$ins->insert_id;
                        $ins->close();
                        $message = '已新增选项';
                        $this->writeAuditLog($conn, 'finance', 'finance.ar.charges.options.add', 'ar_charge_dropdown_options', $newOptId, ['group' => $group, 'name' => $name]);
                    } else {
                        $error = '保存失败，请稍后重试';
                    }
                }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ar_dropdown_option'])) {
                $optId = (int)($_POST['option_id'] ?? 0);
                if ($optId > 0) {
                    $tg = $conn->prepare('UPDATE ar_charge_dropdown_options SET status = IF(status = 1, 0, 1) WHERE id = ? LIMIT 1');
                    if ($tg) {
                        $tg->bind_param('i', $optId);
                        $tg->execute();
                        if ($tg->affected_rows > 0) {
                            $message = $message !== '' ? $message . '；已更新启用状态' : '已更新启用状态';
                            $this->writeAuditLog($conn, 'finance', 'finance.ar.charges.options.toggle', 'ar_charge_dropdown_options', $optId, []);
                        }
                        $tg->close();
                    }
                }
        }
        $allOptions = [];
        $res = $conn->query('SELECT id, option_group, name, sort_order, status FROM ar_charge_dropdown_options ORDER BY option_group ASC, sort_order ASC, id ASC');
        while ($res && ($row = $res->fetch_assoc())) {
            $allOptions[] = $row;
        }
        $title = '财务管理 / 应收账单 / 类目与单位维护';
        $contentView = __DIR__ . '/../Views/finance/ar/charges_options.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arChargesCreate(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.charges.create', 'finance.ar.charges.create', 'finance.manage'])) {
            $this->denyNoPermission('无权限新增费用记录');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $error = '';
        $message = '';
        $parties = $this->financeParties($conn, 'receive');
        $pricingModeCatalogue = $this->arPricingModeCatalogue();
        $algoCatalogue = $this->arSchemeAlgorithmCatalogue();
        $profiles = [];
        $partyModesMap = [];
        $partyBillingSelectMap = [];
        $resProfile = $conn->query('SELECT party_id, formula_config_json FROM ar_customer_profiles WHERE status = 1');
        while ($resProfile && ($row = $resProfile->fetch_assoc())) {
            $pid = (int)$row['party_id'];
            $raw = json_decode((string)$row['formula_config_json'], true);
            $norm = $this->arNormalizeProfileConfig($raw);
            $profiles[$pid] = $norm;
            $partyModesMap[$pid] = [];
            foreach ($norm['pricing_modes'] as $modeKey) {
                $partyModesMap[$pid][] = [
                    'key' => 'L:' . $modeKey,
                    'label' => $pricingModeCatalogue[$modeKey] ?? $modeKey,
                    'scheme' => null,
                ];
            }
        }
        foreach ($parties as $party) {
            $pid = (int)$party['id'];
            $schemes = $this->arPartyBillingSchemesForParty($conn, $pid, true);
            $opts = [];
            foreach ($schemes as $s) {
                $algo = (string)($s['algorithm'] ?? '');
                $opts[] = [
                    'key' => 'S:' . (int)$s['id'],
                    'label' => (string)($s['scheme_label'] ?? '') . '（' . ($algoCatalogue[$algo] ?? $algo) . '）',
                    'scheme' => [
                        'id' => (int)$s['id'],
                        'scheme_label' => (string)($s['scheme_label'] ?? ''),
                        'algorithm' => $algo,
                        'unit_name' => (string)($s['unit_name'] ?? ''),
                        'unit_price' => (float)($s['unit_price'] ?? 0),
                        'base_fee' => (float)($s['base_fee'] ?? 0),
                        'weight_config_json' => (string)($s['weight_config_json'] ?? ''),
                    ],
                ];
            }
            if ($opts !== []) {
                $partyBillingSelectMap[$pid] = $opts;
            } else {
                $legacy = $partyModesMap[$pid] ?? [];
                $partyBillingSelectMap[$pid] = $legacy !== [] ? $legacy : [
                    [
                        'key' => 'L:line_only',
                        'label' => $pricingModeCatalogue['line_only'] ?? 'line_only',
                        'scheme' => null,
                    ],
                ];
            }
        }
        $hasPricingModeCol = $this->columnExists($conn, 'ar_charge_items', 'pricing_mode');
        $hasProjectCol = $this->columnExists($conn, 'ar_charge_items', 'project_name');
        $hasBillingSchemeIdCol = $this->columnExists($conn, 'ar_charge_items', 'billing_scheme_id');
        $categoryOpts = $this->arChargeDropdownOptions($conn, 'category');
        $unitOpts = $this->arChargeDropdownOptions($conn, 'unit');
        $categoryAllowedNames = array_column($categoryOpts, 'name');
        $unitAllowedNames = array_column($unitOpts, 'name');
        $formData = [
            'party_id' => '',
            'billing_date' => date('Y-m-d'),
            'category_name' => '',
            'project_name' => '',
            'unit_price' => '',
            'quantity' => '',
            'unit_name' => '',
            'source_ref' => '',
            'remark' => '',
            'pricing_mode' => '',
            'billing_scheme_key' => '',
            'base_fee' => '',
        ];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ar_charge'])) {
            $partyId = (int)($_POST['party_id'] ?? 0);
            $billingDate = trim((string)($_POST['billing_date'] ?? ''));
            $categoryName = trim((string)($_POST['category_name'] ?? ''));
            $projectName = trim((string)($_POST['project_name'] ?? ''));
            if ($hasProjectCol && mb_strlen($projectName) > 200) {
                $projectName = mb_substr($projectName, 0, 200);
            }
            $unitPrice = (float)($_POST['unit_price'] ?? 0);
            $quantity = (float)($_POST['quantity'] ?? 0);
            $unitName = trim((string)($_POST['unit_name'] ?? ''));
            $sourceRef = trim((string)($_POST['source_ref'] ?? ''));
            $remark = trim((string)($_POST['remark'] ?? ''));
            $billingSchemeKey = trim((string)($_POST['billing_scheme_key'] ?? ''));
            if ($billingSchemeKey === '') {
                $billingSchemeKey = 'L:' . trim((string)($_POST['pricing_mode'] ?? ''));
            }
            $pricingMode = trim((string)($_POST['pricing_mode'] ?? ''));
            $baseFee = (float)($_POST['base_fee'] ?? 0);
            $formData = [
                'party_id' => (string)$partyId,
                'billing_date' => $billingDate,
                'category_name' => $categoryName,
                'project_name' => $projectName,
                'unit_price' => (string)$unitPrice,
                'quantity' => (string)$quantity,
                'unit_name' => $unitName,
                'source_ref' => $sourceRef,
                'remark' => $remark,
                'pricing_mode' => $pricingMode,
                'billing_scheme_key' => $billingSchemeKey,
                'base_fee' => (string)$baseFee,
            ];
            $norm = $profiles[$partyId] ?? ['pricing_modes' => []];
            $allowed = $norm['pricing_modes'];
            $usePartySchemes = $this->arPartyHasBillingSchemes($conn, $partyId);
            $schemeRow = null;
            $billingSchemeId = 0;
            if ($categoryAllowedNames === []) {
                $error = '尚未配置可用费用类目，请点击「维护类目与单位」添加';
            } elseif ($partyId <= 0 || $categoryName === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $billingDate) !== 1) {
                $error = '请选择已建档的客户，并填写正确日期与类目';
            } elseif (!in_array($categoryName, $categoryAllowedNames, true)) {
                $error = '费用类目无效或已停用，请从下拉选单重新选择';
            } elseif (!$usePartySchemes && $unitAllowedNames === []) {
                $error = '尚未配置可用计费单位，请点击「维护类目与单位」添加';
            } elseif ($usePartySchemes) {
                if (!preg_match('/^S:(\d+)$/', $billingSchemeKey, $mk)) {
                    $error = '请选择该客户下已维护的计费方式';
                } else {
                    $billingSchemeId = (int)$mk[1];
                    $schemeRow = $this->arFetchBillingScheme($conn, $billingSchemeId, $partyId);
                    if (!$schemeRow) {
                        $error = '计费方式无效或已停用';
                    } elseif ($unitName !== (string)($schemeRow['unit_name'] ?? '')) {
                        $error = '计费单位须与所选方案一致';
                    } elseif (!$this->arFloatsNearEqual($unitPrice, (float)($schemeRow['unit_price'] ?? 0))) {
                        $error = '单价须与所选方案一致';
                    } else {
                        $algo = (string)($schemeRow['algorithm'] ?? '');
                        if ($algo === 'base_plus_line') {
                            if (!$this->arFloatsNearEqual($baseFee, (float)($schemeRow['base_fee'] ?? 0))) {
                                $error = '基础费用须与所选方案一致';
                            }
                        } elseif ($baseFee !== 0.0 && abs($baseFee) > 0.0001) {
                            $baseFee = 0.0;
                        }
                    }
                }
            } elseif ($unitName === '' || !in_array($unitName, $unitAllowedNames, true)) {
                $error = '计费单位无效或已停用，请从下拉选单重新选择';
            } elseif ($allowed === []) {
                $error = '该客户尚未配置计费形态，请先在「客户计费档案」中保存档案（或先维护计费方式方案）';
            } elseif (!preg_match('/^L:(.+)$/', $billingSchemeKey, $lk) || !in_array((string)$lk[1], $allowed, true)) {
                $error = '所选计费形态对该客户不可用';
            } elseif ((string)$lk[1] === 'base_plus_line' && $baseFee < 0) {
                $error = '基础费用不能为负数';
            }
            $amount = 0.0;
            $inputsJson = '{}';
            $pricingMode = 'line_only';
            if ($error === '') {
                if ($usePartySchemes && $schemeRow !== null) {
                    $pricingMode = $this->arMapAlgorithmToPricingMode((string)($schemeRow['algorithm'] ?? 'qty_unit_price'));
                    $amount = $this->arComputeSchemeAmount($schemeRow, $quantity, $unitPrice, $baseFee);
                    $inputs = [
                        'billing_scheme_id' => $billingSchemeId,
                        'billing_scheme_label' => (string)($schemeRow['scheme_label'] ?? ''),
                        'algorithm' => (string)($schemeRow['algorithm'] ?? ''),
                        'pricing_mode' => $pricingMode,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'base_fee' => $baseFee,
                    ];
                    $rawW = $schemeRow['weight_config_json'] ?? null;
                    if (is_string($rawW) && $rawW !== '') {
                        $inputs['weight_config_json'] = $rawW;
                    }
                    $inputsJson = json_encode($inputs, JSON_UNESCAPED_UNICODE);
                } elseif (!$usePartySchemes && preg_match('/^L:(.+)$/', $billingSchemeKey, $lk)) {
                    $pricingMode = (string)$lk[1];
                    $amount = $this->arComputeChargeAmount($pricingMode, $unitPrice, $quantity, $baseFee);
                    $inputs = [
                        'pricing_mode' => $pricingMode,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'base_fee' => $baseFee,
                    ];
                    $inputsJson = json_encode($inputs, JSON_UNESCAPED_UNICODE);
                } else {
                    $error = '无法计算费用，请检查计费方式';
                }
            }
            if ($error === '') {
                $status = 'draft';
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $newId = 0;
                $bsBind = ($usePartySchemes && $billingSchemeId > 0) ? $billingSchemeId : null;
                if ($hasBillingSchemeIdCol && $hasPricingModeCol && $hasProjectCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, project_name, unit_price, quantity, unit_name, pricing_mode, billing_scheme_id, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'isssddssisdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $projectName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $pricingMode,
                            $bsBind,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } elseif ($hasPricingModeCol && $hasProjectCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, project_name, unit_price, quantity, unit_name, pricing_mode, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'isssddsssdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $projectName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $pricingMode,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } elseif ($hasBillingSchemeIdCol && $hasPricingModeCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, unit_price, quantity, unit_name, pricing_mode, billing_scheme_id, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'issddssisdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $pricingMode,
                            $bsBind,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } elseif ($hasPricingModeCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, unit_price, quantity, unit_name, pricing_mode, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'issddsssdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $pricingMode,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } elseif ($hasBillingSchemeIdCol && $hasProjectCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, project_name, unit_price, quantity, unit_name, pricing_mode, billing_scheme_id, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'isssddssisdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $projectName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $pricingMode,
                            $bsBind,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } elseif ($hasProjectCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, project_name, unit_price, quantity, unit_name, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'isssddssdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $projectName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } elseif ($hasBillingSchemeIdCol) {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, unit_price, quantity, unit_name, pricing_mode, billing_scheme_id, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'issddssisdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $pricingMode,
                            $bsBind,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare('
                        INSERT INTO ar_charge_items
                        (party_id, billing_date, category_name, unit_price, quantity, unit_name, formula_inputs_json, calculated_amount, source_ref, status, remark, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($stmt) {
                        $stmt->bind_param(
                            'issddssdsssi',
                            $partyId,
                            $billingDate,
                            $categoryName,
                            $unitPrice,
                            $quantity,
                            $unitName,
                            $inputsJson,
                            $amount,
                            $sourceRef,
                            $status,
                            $remark,
                            $userId
                        );
                        $stmt->execute();
                        $newId = (int)$stmt->insert_id;
                        $stmt->close();
                    }
                }
                if ($newId > 0) {
                    $message = '费用记录已保存';
                    if (!$hasProjectCol) {
                        $message .= '（请先执行 migration 019 以保存「项目」栏位）';
                    } elseif (!$hasPricingModeCol) {
                        $message .= '（请先执行 migration 018 以在数据库中记录计费形态字段）';
                    } elseif (!$hasBillingSchemeIdCol) {
                        $message .= '（请先执行 migration 020 以关联计费方式方案）';
                    }
                    $this->writeAuditLog($conn, 'finance', 'finance.ar.charge.create', 'ar_charge_item', $newId, ['amount' => $amount, 'pricing_mode' => $pricingMode]);
                } else {
                    $error = '保存失败，请稍后重试';
                }
            }
        }
        $defaultBillingKeyByParty = [];
        foreach ($parties as $party) {
            $ppid = (int)$party['id'];
            $norm = $profiles[$ppid] ?? ['pricing_modes' => ['line_only'], 'default_billing_scheme_id' => 0];
            $dsid = (int)($norm['default_billing_scheme_id'] ?? 0);
            if ($dsid > 0 && $this->arPartyHasBillingSchemes($conn, $ppid)) {
                $defaultBillingKeyByParty[$ppid] = 'S:' . $dsid;
            } else {
                $modes = $norm['pricing_modes'] ?? ['line_only'];
                $defaultBillingKeyByParty[$ppid] = 'L:' . (string)($modes[0] ?? 'line_only');
            }
        }
        $title = '财务管理 / 应收账单 / 新增费用记录';
        $contentView = __DIR__ . '/../Views/finance/ar/charges_create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arChargesList(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.charges.list', 'finance.ar.charges.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看费用记录');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));
        if (!in_array($statusFilter, ['all', 'draft', 'invoiced', 'void'], true)) {
            $statusFilter = 'all';
        }
        $partyId = (int)($_GET['party_id'] ?? 0);
        $where = '1=1';
        $bindTypes = '';
        $bindValues = [];
        if ($statusFilter !== 'all') {
            $where .= ' AND c.status = ?';
            $bindTypes .= 's';
            $bindValues[] = $statusFilter;
        }
        if ($partyId > 0) {
            $where .= ' AND c.party_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = $partyId;
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM ar_charge_items c WHERE ' . $where);
        if ($countStmt) {
            if ($bindTypes !== '') {
                $params = [];
                $params[] = &$bindTypes;
                foreach ($bindValues as $i => $v) {
                    $params[] = &$bindValues[$i];
                }
                call_user_func_array([$countStmt, 'bind_param'], $params);
            }
            $countStmt->execute();
            $total = (int)(($countStmt->get_result()->fetch_assoc())['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $rows = [];
        $hasBsJoin = $this->columnExists($conn, 'ar_charge_items', 'billing_scheme_id');
        $joinBs = $hasBsJoin
            ? 'LEFT JOIN ar_party_billing_schemes bs ON bs.id = c.billing_scheme_id'
            : '';
        $selBs = $hasBsJoin ? ', bs.scheme_label AS billing_scheme_label' : '';
        $stmt = $conn->prepare("
            SELECT c.*, p.party_name, COALESCE(NULLIF(u.full_name, ''), u.username) AS creator{$selBs}
            FROM ar_charge_items c
            LEFT JOIN finance_parties p ON p.id = c.party_id
            LEFT JOIN users u ON u.id = c.created_by
            {$joinBs}
            WHERE {$where}
            ORDER BY c.id DESC
            LIMIT {$offset}, {$perPage}
        ");
        if ($stmt) {
            if ($bindTypes !== '') {
                $params = [];
                $params[] = &$bindTypes;
                foreach ($bindValues as $i => $v) {
                    $params[] = &$bindValues[$i];
                }
                call_user_func_array([$stmt, 'bind_param'], $params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        $parties = $this->financeParties($conn, 'receive');
        $pricingModeCatalogue = $this->arPricingModeCatalogue();
        $title = '财务管理 / 应收账单 / 费用记录列表';
        $contentView = __DIR__ . '/../Views/finance/ar/charges_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arInvoicesList(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.invoices', 'finance.ar.invoices.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看应收账单');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['build_invoice'])) {
            if (!$this->hasAnyPermission(['menu.nav.finance.ar.invoices', 'finance.ar.invoices.create', 'finance.manage'])) {
                $this->denyNoPermission('无权限生成账单');
            }
            $partyId = (int)($_POST['party_id'] ?? 0);
            $periodStart = trim((string)($_POST['period_start'] ?? ''));
            $periodEnd = trim((string)($_POST['period_end'] ?? ''));
            $issueDate = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
            if ($partyId <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd) !== 1) {
                $error = '请选择客户并填写正确期间';
            } else {
                $conn->begin_transaction();
                try {
                    $hasBsCol = $this->columnExists($conn, 'ar_charge_items', 'billing_scheme_id');
                    $itemSql = $hasBsCol
                        ? 'SELECT c.*, bs.scheme_label AS billing_scheme_label
                            FROM ar_charge_items c
                            LEFT JOIN ar_party_billing_schemes bs ON bs.id = c.billing_scheme_id
                            WHERE c.party_id = ? AND c.status = \'draft\' AND c.billing_date >= ? AND c.billing_date <= ?
                            ORDER BY c.billing_date ASC, c.id ASC'
                        : 'SELECT *
                            FROM ar_charge_items
                            WHERE party_id = ? AND status = \'draft\' AND billing_date >= ? AND billing_date <= ?
                            ORDER BY billing_date ASC, id ASC';
                    $itemStmt = $conn->prepare($itemSql);
                    if (!$itemStmt) {
                        throw new RuntimeException('读取费用记录失败');
                    }
                    $itemStmt->bind_param('iss', $partyId, $periodStart, $periodEnd);
                    $itemStmt->execute();
                    $itemRes = $itemStmt->get_result();
                    $items = [];
                    $totalAmount = 0.0;
                    while ($itemRes && ($row = $itemRes->fetch_assoc())) {
                        $items[] = $row;
                        $totalAmount += (float)($row['calculated_amount'] ?? 0);
                    }
                    $itemStmt->close();
                    if (empty($items)) {
                        throw new RuntimeException('该区间没有可开票费用记录');
                    }
                    $invoiceNo = $this->arNextInvoiceNo($conn);
                    $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                    $insertInvoice = $conn->prepare('
                        INSERT INTO ar_invoices (invoice_no, party_id, period_start, period_end, issue_date, total_amount, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if (!$insertInvoice) {
                        throw new RuntimeException('创建账单失败');
                    }
                    $status = 'issued';
                    $insertInvoice->bind_param('sisssdsi', $invoiceNo, $partyId, $periodStart, $periodEnd, $issueDate, $totalAmount, $status, $userId);
                    $insertInvoice->execute();
                    $invoiceId = (int)$insertInvoice->insert_id;
                    $insertInvoice->close();
                    $lineStmt = $conn->prepare('
                        INSERT INTO ar_invoice_lines (invoice_id, charge_item_id, line_amount, line_detail_json)
                        VALUES (?, ?, ?, ?)
                    ');
                    $markStmt = $conn->prepare("UPDATE ar_charge_items SET status = 'invoiced' WHERE id = ? LIMIT 1");
                    if (!$lineStmt || !$markStmt) {
                        throw new RuntimeException('写入账单明细失败');
                    }
                    foreach ($items as $item) {
                        $chargeId = (int)$item['id'];
                        $lineAmount = (float)$item['calculated_amount'];
                        $lineArr = [
                            'category_name' => (string)($item['category_name'] ?? ''),
                            'project_name' => (string)($item['project_name'] ?? ''),
                            'unit_price' => (float)($item['unit_price'] ?? 0),
                            'quantity' => (float)($item['quantity'] ?? 0),
                            'unit_name' => (string)($item['unit_name'] ?? ''),
                            'pricing_mode' => (string)($item['pricing_mode'] ?? 'line_only'),
                            'formula_inputs_json' => json_decode((string)($item['formula_inputs_json'] ?? '{}'), true),
                        ];
                        if (!empty($item['billing_scheme_id'])) {
                            $lineArr['billing_scheme_id'] = (int)$item['billing_scheme_id'];
                        }
                        if (!empty($item['billing_scheme_label'])) {
                            $lineArr['billing_scheme_label'] = (string)$item['billing_scheme_label'];
                        }
                        $lineJson = json_encode($lineArr, JSON_UNESCAPED_UNICODE);
                        $lineStmt->bind_param('iids', $invoiceId, $chargeId, $lineAmount, $lineJson);
                        $lineStmt->execute();
                        $markStmt->bind_param('i', $chargeId);
                        $markStmt->execute();
                    }
                    $lineStmt->close();
                    $markStmt->close();
                    $partyName = $this->arPartyName($conn, $partyId);
                    $recvStmt = $conn->prepare('
                        INSERT INTO receivables (client_name, party_id, ar_invoice_id, amount, expected_receive_date, remark, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if (!$recvStmt) {
                        throw new RuntimeException('创建待收款失败');
                    }
                    $receivableRemark = '应收账单 ' . $invoiceNo . ' 自动生成';
                    $recvStatus = 'pending';
                    $recvStmt->bind_param('siidsssi', $partyName, $partyId, $invoiceId, $totalAmount, $issueDate, $receivableRemark, $recvStatus, $userId);
                    $recvStmt->execute();
                    $receivableId = (int)$recvStmt->insert_id;
                    $recvStmt->close();
                    $bindInvoiceStmt = $conn->prepare('UPDATE ar_invoices SET receivable_id = ? WHERE id = ? LIMIT 1');
                    if ($bindInvoiceStmt) {
                        $bindInvoiceStmt->bind_param('ii', $receivableId, $invoiceId);
                        $bindInvoiceStmt->execute();
                        $bindInvoiceStmt->close();
                    }
                    $this->arWriteLedger(
                        $conn,
                        $partyId,
                        $invoiceId,
                        $receivableId,
                        null,
                        'debit',
                        (float)$totalAmount,
                        0.0,
                        '开票增加应收：' . $invoiceNo,
                        $userId
                    );
                    $this->sendFinanceNotification(
                        $conn,
                        'finance.ar.invoice.created',
                        $invoiceId,
                        $userId,
                        '应收账单已生成：' . $invoiceNo,
                        sprintf('客户 %s，金额 %.2f，已自动转待收款 #%d', $partyName, $totalAmount, $receivableId)
                    );
                    $conn->commit();
                    $message = '账单已生成并自动转入待收款';
                    $this->writeAuditLog($conn, 'finance', 'finance.ar.invoice.create', 'ar_invoice', $invoiceId, [
                        'invoice_no' => $invoiceNo,
                        'receivable_id' => $receivableId,
                        'total_amount' => $totalAmount,
                    ]);
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));
        if (!in_array($statusFilter, ['all', 'issued', 'paid', 'cancelled'], true)) {
            $statusFilter = 'all';
        }
        $where = '1=1';
        $bindTypes = '';
        $bindValues = [];
        if ($statusFilter !== 'all') {
            $where .= ' AND i.status = ?';
            $bindTypes .= 's';
            $bindValues[] = $statusFilter;
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM ar_invoices i WHERE ' . $where);
        if ($countStmt) {
            if ($bindTypes !== '') {
                $params = [];
                $params[] = &$bindTypes;
                foreach ($bindValues as $i => $v) {
                    $params[] = &$bindValues[$i];
                }
                call_user_func_array([$countStmt, 'bind_param'], $params);
            }
            $countStmt->execute();
            $total = (int)(($countStmt->get_result()->fetch_assoc())['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $rows = [];
        $stmt = $conn->prepare("
            SELECT i.*, p.party_name
            FROM ar_invoices i
            LEFT JOIN finance_parties p ON p.id = i.party_id
            WHERE {$where}
            ORDER BY i.id DESC
            LIMIT {$offset}, {$perPage}
        ");
        if ($stmt) {
            if ($bindTypes !== '') {
                $params = [];
                $params[] = &$bindTypes;
                foreach ($bindValues as $i => $v) {
                    $params[] = &$bindValues[$i];
                }
                call_user_func_array([$stmt, 'bind_param'], $params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        $parties = $this->financeParties($conn, 'receive');
        $title = '财务管理 / 应收账单 / 账单列表';
        $contentView = __DIR__ . '/../Views/finance/ar/invoices_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arInvoiceView(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.invoices', 'finance.ar.invoices.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看应收账单');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /finance/ar/invoices/list');
            exit;
        }
        $invoice = null;
        $stmt = $conn->prepare('
            SELECT i.*, p.party_name
            FROM ar_invoices i
            LEFT JOIN finance_parties p ON p.id = i.party_id
            WHERE i.id = ? LIMIT 1
        ');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$invoice) {
            header('Location: /finance/ar/invoices/list');
            exit;
        }
        $lines = [];
        $hasProjectColView = $this->columnExists($conn, 'ar_charge_items', 'project_name');
        $lineSql = $hasProjectColView
            ? 'SELECT l.*, c.billing_date, c.category_name, c.project_name, c.unit_price, c.quantity, c.unit_name, c.source_ref, c.remark
                FROM ar_invoice_lines l
                LEFT JOIN ar_charge_items c ON c.id = l.charge_item_id
                WHERE l.invoice_id = ?
                ORDER BY l.id ASC'
            : 'SELECT l.*, c.billing_date, c.category_name, c.unit_price, c.quantity, c.unit_name, c.source_ref, c.remark
                FROM ar_invoice_lines l
                LEFT JOIN ar_charge_items c ON c.id = l.charge_item_id
                WHERE l.invoice_id = ?
                ORDER BY l.id ASC';
        $lineStmt = $conn->prepare($lineSql);
        if ($lineStmt) {
            $lineStmt->bind_param('i', $id);
            $lineStmt->execute();
            $res = $lineStmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                if (!$hasProjectColView) {
                    $row['project_name'] = '';
                }
                $lines[] = $row;
            }
            $lineStmt->close();
        }
        $pricingModeCatalogue = $this->arPricingModeCatalogue();
        $title = '财务管理 / 应收账单 / 账单详情';
        $contentView = __DIR__ . '/../Views/finance/ar/invoice_view.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function arInvoicesExportUnpaid(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.invoices', 'finance.ar.invoices.export', 'finance.manage'])) {
            $this->denyNoPermission('无权限导出未收款明细');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $partyId = (int)($_GET['party_id'] ?? 0);
        if ($partyId <= 0) {
            $this->denyNoPermission('请先选择客户后再导出');
        }
        $dataset = $this->arUnpaidIssuedLineDataset($conn, $partyId);
        $partyName = $dataset['party_name'];
        $rows = $dataset['rows'];
        $sum = $dataset['sum'];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=ar_unpaid_' . $partyId . '_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            $this->csvWriteUtf8Bom($out);
            fputcsv($out, ['客户名称', $partyName, '', '', '', '', '', '']);
            fputcsv($out, ['账单号', '费用日', '类目', '项目', '数量', '单位', '单价', '小计']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string)$row['invoice_no'],
                    (string)$row['billing_date'],
                    (string)$row['category_name'],
                    (string)($row['project_name'] ?? ''),
                    (float)$row['quantity'],
                    (string)$row['unit_name'],
                    (float)$row['unit_price'],
                    (float)$row['line_amount'],
                ]);
            }
            fputcsv($out, ['', '', '', '', '', '', '总价', round($sum, 2)]);
            fclose($out);
        }
        exit;
    }

    /**
     * 未收款明细打印页：浏览器「打印」→ 目标选「另存为 PDF」。
     */
    public function arInvoicesPrintUnpaid(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.invoices', 'finance.ar.invoices.export', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看未收款打印明细');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $partyId = (int)($_GET['party_id'] ?? 0);
        if ($partyId <= 0) {
            $this->denyNoPermission('请先选择客户');
        }
        $dataset = $this->arUnpaidIssuedLineDataset($conn, $partyId);
        $partyName = $dataset['party_name'];
        $rows = $dataset['rows'];
        $sum = $dataset['sum'];
        $generatedAt = date('Y-m-d H:i:s');
        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/../Views/finance/ar/invoices_print_unpaid.php';
        exit;
    }

    public function arLedger(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.ar.ledger', 'finance.ar.ledger.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看应收台账');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $this->ensureArSchema($conn);
        $partyId = (int)($_GET['party_id'] ?? 0);
        $where = '1=1';
        $bindTypes = '';
        $bindValues = [];
        if ($partyId > 0) {
            $where .= ' AND l.party_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = $partyId;
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM ar_receivable_ledger l WHERE ' . $where);
        if ($countStmt) {
            if ($bindTypes !== '') {
                $params = [];
                $params[] = &$bindTypes;
                foreach ($bindValues as $i => $v) {
                    $params[] = &$bindValues[$i];
                }
                call_user_func_array([$countStmt, 'bind_param'], $params);
            }
            $countStmt->execute();
            $total = (int)(($countStmt->get_result()->fetch_assoc())['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $rows = [];
        $stmt = $conn->prepare("
            SELECT l.*, p.party_name, i.invoice_no
            FROM ar_receivable_ledger l
            LEFT JOIN finance_parties p ON p.id = l.party_id
            LEFT JOIN ar_invoices i ON i.id = l.invoice_id
            WHERE {$where}
            ORDER BY l.id DESC
            LIMIT {$offset}, {$perPage}
        ");
        if ($stmt) {
            if ($bindTypes !== '') {
                $params = [];
                $params[] = &$bindTypes;
                foreach ($bindValues as $i => $v) {
                    $params[] = &$bindValues[$i];
                }
                call_user_func_array([$stmt, 'bind_param'], $params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        $parties = $this->financeParties($conn, 'receive');
        $title = '财务管理 / 应收账单 / 应收台账';
        $contentView = __DIR__ . '/../Views/finance/ar/ledger.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function accounts(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.accounts', 'finance.accounts.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看账户');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
            if (!$this->hasAnyPermission(['menu.nav.finance.accounts', 'finance.accounts.create', 'finance.manage'])) {
                $this->denyNoPermission('无权限新增账户');
            }
            $accountName = trim((string)($_POST['account_name'] ?? ''));
            $accountType = trim((string)($_POST['account_type'] ?? ''));
            if ($accountName === '') {
                $error = '账户名称不能为空';
            } else {
                $status = 1;
                $stmt = $conn->prepare('INSERT INTO accounts (account_name, account_type, status) VALUES (?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssi', $accountName, $accountType, $status);
                    $stmt->execute();
                    $newId = (int)$stmt->insert_id;
                    $stmt->close();
                    $this->writeAuditLog($conn, 'finance', 'finance.accounts.create', 'account', $newId);
                    header('Location: /finance/accounts?msg=created');
                    exit;
                }
                $error = '新增失败';
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_account']) && (int)($_POST['account_id'] ?? 0) > 0) {
            if (!$this->hasAnyPermission(['menu.nav.finance.accounts', 'finance.accounts.edit', 'finance.manage'])) {
                $this->denyNoPermission('无权限启停账户');
            }
            $id = (int)($_POST['account_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE accounts SET status = IF(status=1,0,1) WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $this->writeAuditLog($conn, 'finance', 'finance.accounts.toggle', 'account', $id);
            header('Location: /finance/accounts?msg=toggled');
            exit;
        }
        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '账户新增成功';
        } elseif (isset($_GET['msg']) && $_GET['msg'] === 'toggled') {
            $message = '账户状态已更新';
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $countRes = $conn->query('SELECT COUNT(*) AS c FROM accounts');
        if ($countRes) {
            $countRow = $countRes->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $rows = [];
        $res = $conn->query('SELECT id, account_name, account_type, status FROM accounts ORDER BY id DESC LIMIT ' . $offset . ', ' . $perPage);
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $title = '财务管理 / 账户管理';
        $contentView = __DIR__ . '/../Views/finance/accounts/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function categories(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.categories', 'finance.categories.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看类目');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
            if (!$this->hasAnyPermission(['menu.nav.finance.categories', 'finance.categories.create', 'finance.manage'])) {
                $this->denyNoPermission('无权限新增类目');
            }
            $name = trim((string)($_POST['name'] ?? ''));
            $type = trim((string)($_POST['type'] ?? ''));
            if ($name === '' || !in_array($type, ['income', 'expense'], true)) {
                $error = '请填写正确的类目名称与类型';
            } else {
                $status = 1;
                $stmt = $conn->prepare('INSERT INTO transaction_categories (name, type, status) VALUES (?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssi', $name, $type, $status);
                    $stmt->execute();
                    $newId = (int)$stmt->insert_id;
                    $stmt->close();
                    $this->writeAuditLog($conn, 'finance', 'finance.categories.create', 'transaction_category', $newId);
                    header('Location: /finance/categories?msg=created');
                    exit;
                }
                $error = '新增失败';
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_category']) && (int)($_POST['category_id'] ?? 0) > 0) {
            if (!$this->hasAnyPermission(['menu.nav.finance.categories', 'finance.categories.edit', 'finance.manage'])) {
                $this->denyNoPermission('无权限启停类目');
            }
            $id = (int)($_POST['category_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE transaction_categories SET status = IF(status=1,0,1) WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $this->writeAuditLog($conn, 'finance', 'finance.categories.toggle', 'transaction_category', $id);
            header('Location: /finance/categories?msg=toggled');
            exit;
        }
        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '类目新增成功';
        } elseif (isset($_GET['msg']) && $_GET['msg'] === 'toggled') {
            $message = '类目状态已更新';
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $countRes = $conn->query('SELECT COUNT(*) AS c FROM transaction_categories');
        if ($countRes) {
            $countRow = $countRes->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $rows = [];
        $res = $conn->query('SELECT id, name, type, status FROM transaction_categories ORDER BY id DESC LIMIT ' . $offset . ', ' . $perPage);
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $title = '财务管理 / 类目管理';
        $contentView = __DIR__ . '/../Views/finance/categories/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function parties(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.parties', 'finance.parties.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看付款收款对象');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        if (!$this->tableExists($conn, 'finance_parties')) {
            $this->denyNoPermission('对象资料表未建立，请先执行 migration：016_add_finance_parties_and_refs.sql');
        }
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_party'])) {
            if (!$this->hasAnyPermission(['menu.nav.finance.parties', 'finance.parties.create', 'finance.manage'])) {
                $this->denyNoPermission('无权限新增对象');
            }
            $partyName = trim((string)($_POST['party_name'] ?? ''));
            $partyKind = trim((string)($_POST['party_kind'] ?? 'both'));
            if (!in_array($partyKind, ['pay', 'receive', 'both'], true)) {
                $partyKind = 'both';
            }
            if ($partyName === '') {
                $error = '对象名称不能为空';
            } else {
                $status = 1;
                $stmt = $conn->prepare('INSERT INTO finance_parties (party_name, party_kind, status) VALUES (?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssi', $partyName, $partyKind, $status);
                    $stmt->execute();
                    $newId = (int)$stmt->insert_id;
                    $stmt->close();
                    $this->writeAuditLog($conn, 'finance', 'finance.parties.create', 'finance_party', $newId);
                    header('Location: /finance/parties?msg=created');
                    exit;
                }
                $error = '保存失败';
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_party']) && (int)($_POST['party_id'] ?? 0) > 0) {
            if (!$this->hasAnyPermission(['menu.nav.finance.parties', 'finance.parties.edit', 'finance.manage'])) {
                $this->denyNoPermission('无权限启停对象');
            }
            $id = (int)($_POST['party_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE finance_parties SET status = IF(status=1,0,1) WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $this->writeAuditLog($conn, 'finance', 'finance.parties.toggle', 'finance_party', $id);
            header('Location: /finance/parties?msg=toggled');
            exit;
        }
        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '对象新增成功';
        } elseif (isset($_GET['msg']) && $_GET['msg'] === 'toggled') {
            $message = '对象状态已更新';
        }
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $countRes = $conn->query('SELECT COUNT(*) AS c FROM finance_parties');
        if ($countRes) {
            $countRow = $countRes->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $rows = [];
        $res = $conn->query('SELECT id, party_name, party_kind, status, created_at FROM finance_parties ORDER BY id DESC LIMIT ' . $offset . ', ' . $perPage);
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $title = '财务管理 / 付款收款对象';
        $contentView = __DIR__ . '/../Views/finance/parties/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function reportsOverview(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.reports', 'finance.reports.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看财务报表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
        $endDate = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = date('Y-m-d');
        }
        if ($startDate > $endDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }
        $summary = ['income' => 0.0, 'expense' => 0.0, 'profit' => 0.0];
        $stmt = $conn->prepare('
            SELECT type, COALESCE(SUM(amount), 0) AS total_amount
            FROM transactions
            WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
            GROUP BY type
        ');
        if ($stmt) {
            $stmt->bind_param('ss', $startDate, $endDate);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $type = (string)($row['type'] ?? '');
                $total = (float)($row['total_amount'] ?? 0);
                if ($type === 'income') {
                    $summary['income'] = $total;
                } elseif ($type === 'expense') {
                    $summary['expense'] = $total;
                }
            }
            $stmt->close();
        }
        $summary['profit'] = $summary['income'] - $summary['expense'];

        // 图表1：最近6个月收支趋势
        $trendLabels = [];
        $trendIncome = [];
        $trendExpense = [];
        $monthStart = new DateTimeImmutable(date('Y-m-01'));
        for ($i = 5; $i >= 0; $i--) {
            $cur = $monthStart->modify('-' . $i . ' month');
            $trendLabels[] = $cur->format('Y-m');
            $trendIncome[] = 0.0;
            $trendExpense[] = 0.0;
        }
        $trendIndex = [];
        foreach ($trendLabels as $idx => $label) {
            $trendIndex[$label] = $idx;
        }
        $trendStmt = $conn->prepare('
            SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS ym, type, COALESCE(SUM(amount),0) AS total_amount
            FROM transactions
            WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), \'%Y-%m-01\'), INTERVAL 5 MONTH)
            GROUP BY DATE_FORMAT(created_at, \'%Y-%m\'), type
            ORDER BY ym ASC
        ');
        if ($trendStmt) {
            $trendStmt->execute();
            $trendRes = $trendStmt->get_result();
            while ($trendRes && ($row = $trendRes->fetch_assoc())) {
                $ym = (string)($row['ym'] ?? '');
                if (!array_key_exists($ym, $trendIndex)) {
                    continue;
                }
                $idx = $trendIndex[$ym];
                $amount = (float)($row['total_amount'] ?? 0);
                if ((string)($row['type'] ?? '') === 'income') {
                    $trendIncome[$idx] = $amount;
                } elseif ((string)($row['type'] ?? '') === 'expense') {
                    $trendExpense[$idx] = $amount;
                }
            }
            $trendStmt->close();
        }

        // 图表2：支出类目前5
        $expenseCategories = [];
        $catStmt = $conn->prepare('
            SELECT
                COALESCE(NULLIF(c.name, \'\'), \'未分类\') AS category_name,
                COALESCE(SUM(t.amount),0) AS total_amount
            FROM transactions t
            LEFT JOIN transaction_categories c ON c.id = t.category_id
            WHERE t.type = \'expense\' AND DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
            GROUP BY COALESCE(NULLIF(c.name, \'\'), \'未分类\')
            ORDER BY total_amount DESC
            LIMIT 5
        ');
        if ($catStmt) {
            $catStmt->bind_param('ss', $startDate, $endDate);
            $catStmt->execute();
            $catRes = $catStmt->get_result();
            while ($catRes && ($row = $catRes->fetch_assoc())) {
                $expenseCategories[] = [
                    'label' => (string)($row['category_name'] ?? '未分类'),
                    'value' => (float)($row['total_amount'] ?? 0),
                ];
            }
            $catStmt->close();
        }

        // 图表3：待处理风险（应付/应收）
        $pipeline = [
            'payables_pending_count' => 0,
            'payables_pending_amount' => 0.0,
            'payables_overdue_count' => 0,
            'payables_overdue_amount' => 0.0,
            'receivables_pending_count' => 0,
            'receivables_pending_amount' => 0.0,
            'receivables_overdue_count' => 0,
            'receivables_overdue_amount' => 0.0,
        ];
        if ($this->tableExists($conn, 'payables')) {
            $payStmt = $conn->prepare('
                SELECT
                    COUNT(*) AS pending_count,
                    COALESCE(SUM(amount),0) AS pending_amount,
                    SUM(CASE WHEN expected_pay_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
                    COALESCE(SUM(CASE WHEN expected_pay_date < CURDATE() THEN amount ELSE 0 END),0) AS overdue_amount
                FROM payables
                WHERE status = \'pending\'
            ');
            if ($payStmt) {
                $payStmt->execute();
                $row = $payStmt->get_result()->fetch_assoc();
                if ($row) {
                    $pipeline['payables_pending_count'] = (int)($row['pending_count'] ?? 0);
                    $pipeline['payables_pending_amount'] = (float)($row['pending_amount'] ?? 0);
                    $pipeline['payables_overdue_count'] = (int)($row['overdue_count'] ?? 0);
                    $pipeline['payables_overdue_amount'] = (float)($row['overdue_amount'] ?? 0);
                }
                $payStmt->close();
            }
        }
        if ($this->tableExists($conn, 'receivables')) {
            $recStmt = $conn->prepare('
                SELECT
                    COUNT(*) AS pending_count,
                    COALESCE(SUM(amount),0) AS pending_amount,
                    SUM(CASE WHEN expected_receive_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
                    COALESCE(SUM(CASE WHEN expected_receive_date < CURDATE() THEN amount ELSE 0 END),0) AS overdue_amount
                FROM receivables
                WHERE status = \'pending\'
            ');
            if ($recStmt) {
                $recStmt->execute();
                $row = $recStmt->get_result()->fetch_assoc();
                if ($row) {
                    $pipeline['receivables_pending_count'] = (int)($row['pending_count'] ?? 0);
                    $pipeline['receivables_pending_amount'] = (float)($row['pending_amount'] ?? 0);
                    $pipeline['receivables_overdue_count'] = (int)($row['overdue_count'] ?? 0);
                    $pipeline['receivables_overdue_amount'] = (float)($row['overdue_amount'] ?? 0);
                }
                $recStmt->close();
            }
        }

        // 图表4：每月收支类目分析（按查询区间）
        $monthlyCategoryAnalysis = [];
        $monthCatStmt = $conn->prepare('
            SELECT
                DATE_FORMAT(t.created_at, \'%Y-%m\') AS ym,
                t.type,
                COALESCE(NULLIF(c.name, \'\'), \'未分类\') AS category_name,
                COALESCE(SUM(t.amount), 0) AS total_amount
            FROM transactions t
            LEFT JOIN transaction_categories c ON c.id = t.category_id
            WHERE DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
            GROUP BY DATE_FORMAT(t.created_at, \'%Y-%m\'), t.type, COALESCE(NULLIF(c.name, \'\'), \'未分类\')
            ORDER BY ym DESC, total_amount DESC
        ');
        if ($monthCatStmt) {
            $monthCatStmt->bind_param('ss', $startDate, $endDate);
            $monthCatStmt->execute();
            $monthCatRes = $monthCatStmt->get_result();
            while ($monthCatRes && ($row = $monthCatRes->fetch_assoc())) {
                $ym = (string)($row['ym'] ?? '');
                $type = (string)($row['type'] ?? '');
                if ($ym === '' || !in_array($type, ['income', 'expense'], true)) {
                    continue;
                }
                if (!isset($monthlyCategoryAnalysis[$ym])) {
                    $monthlyCategoryAnalysis[$ym] = [
                        'income' => [],
                        'expense' => [],
                    ];
                }
                $monthlyCategoryAnalysis[$ym][$type][] = [
                    'label' => (string)($row['category_name'] ?? '未分类'),
                    'value' => (float)($row['total_amount'] ?? 0),
                ];
            }
            $monthCatStmt->close();
        }

        $title = '财务管理 / 报表总览';
        $contentView = __DIR__ . '/../Views/finance/reports/overview.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function reportsDetail(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.reports', 'finance.reports.view', 'finance.manage'])) {
            $this->denyNoPermission('无权限查看财务明细');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
        $endDate = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = date('Y-m-d');
        }
        $typeFilter = trim((string)($_GET['type'] ?? 'all'));
        if (!in_array($typeFilter, ['all', 'income', 'expense'], true)) {
            $typeFilter = 'all';
        }
        $where = 'DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?';
        $bindTypes = 'ss';
        $bindValues = [$startDate, $endDate];
        $perPage = $this->resolvePerPage();
        $page = $this->resolvePage();
        $offset = ($page - 1) * $perPage;
        if ($typeFilter !== 'all') {
            $where .= ' AND t.type = ?';
            $bindTypes .= 's';
            $bindValues[] = $typeFilter;
        }
        $total = 0;
        $countSql = 'SELECT COUNT(*) AS c FROM transactions t WHERE ' . $where;
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            $bindParams = [];
            $bindParams[] = &$bindTypes;
            foreach ($bindValues as $idx => $val) {
                $bindParams[] = &$bindValues[$idx];
            }
            call_user_func_array([$countStmt, 'bind_param'], $bindParams);
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $total = (int)($countRow['c'] ?? 0);
            $countStmt->close();
        }
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
        $sql = '
            SELECT
                t.id, t.type, t.amount, t.client, t.description, t.created_at,
                c.name AS category_name, a.account_name,
                COALESCE(NULLIF(u.full_name, \'\'), u.username) AS creator
            FROM transactions t
            LEFT JOIN transaction_categories c ON c.id = t.category_id
            LEFT JOIN accounts a ON a.id = t.account_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE ' . $where . '
            ORDER BY t.id DESC
            LIMIT ' . $offset . ', ' . $perPage . '
        ';
        $rows = [];
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $bindParams = [];
            $bindParams[] = &$bindTypes;
            foreach ($bindValues as $idx => $val) {
                $bindParams[] = &$bindValues[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        $title = '财务管理 / 报表明细';
        $contentView = __DIR__ . '/../Views/finance/reports/detail.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function reportsExport(): void
    {
        $this->requireFinanceMenu();
        if (!$this->hasAnyPermission(['menu.nav.finance.reports', 'finance.reports.export', 'finance.manage'])) {
            $this->denyNoPermission('无权限导出财务报表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $this->ensureFinanceSchema($conn);
        $startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
        $endDate = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = date('Y-m-d');
        }
        $rows = [];
        $stmt = $conn->prepare('
            SELECT t.id, t.type, t.amount, t.client, t.description, t.created_at
            FROM transactions t
            WHERE DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
            ORDER BY t.id DESC
            LIMIT 5000
        ');
        if ($stmt) {
            $stmt->bind_param('ss', $startDate, $endDate);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=finance_report_' . $startDate . '_' . $endDate . '.csv');
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            $this->csvWriteUtf8Bom($out);
            fputcsv($out, ['ID', '类型', '金额', '对象', '说明', '时间']);
            foreach ($rows as $row) {
                $typeRaw = (string)($row['type'] ?? '');
                $typeLabel = match ($typeRaw) {
                    'income' => '收入',
                    'expense' => '支出',
                    default => $typeRaw,
                };
                fputcsv($out, [
                    (int)($row['id'] ?? 0),
                    $typeLabel,
                    (float)($row['amount'] ?? 0),
                    (string)($row['client'] ?? ''),
                    (string)($row['description'] ?? ''),
                    (string)($row['created_at'] ?? ''),
                ]);
            }
            fclose($out);
        }
        exit;
    }
}
