<?php

class UdaController
{
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

    private function denyNoPermission(string $message = '无权限访问'): void
    {
        http_response_code(403);
        echo $message;
        exit;
    }

    private function normalizeTrackingNo(string $raw): string
    {
        $s = trim($raw);
        $s = preg_replace('/\s+/', '', $s) ?? '';
        $s = strtoupper($s);
        $s = preg_replace('/@.*$/', '', $s) ?? '';
        return trim($s);
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

    private function syncManifestBillNoByDateNo(mysqli $conn, string $dateNo, string $billNo): void
    {
        $dateNo = trim($dateNo);
        $billNo = trim($billNo);
        if ($dateNo === '' || !$this->tableExists($conn, 'uda_manifest_batches')) {
            return;
        }
        if (!$this->columnExists($conn, 'uda_manifest_batches', 'date_no')
            || !$this->columnExists($conn, 'uda_manifest_batches', 'bill_no')) {
            return;
        }
        $up = $conn->prepare('UPDATE uda_manifest_batches SET bill_no = ? WHERE date_no = ?');
        if ($up) {
            $up->bind_param('ss', $billNo, $dateNo);
            $up->execute();
            $up->close();
        }
    }

    private function udaForwardVoucherStorageDir(): string
    {
        return __DIR__ . '/../../storage/uda/forwarding-vouchers';
    }

    private function ensureUdaForwardVoucherStorageDir(): void
    {
        $dir = $this->udaForwardVoucherStorageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{ok:bool,path:?string,error:string}
     */
    private function udaForwardVoucherSaveFromUpload(?array $file, string $filenameBase): array
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
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase) ?: 'udafwd';
        $name = $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $this->ensureUdaForwardVoucherStorageDir();
        $dest = $this->udaForwardVoucherStorageDir() . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'path' => null, 'error' => '凭证保存失败'];
        }
        return ['ok' => true, 'path' => $name, 'error' => ''];
    }

    /** @return array{full:string,mime:string}|null */
    private function udaForwardVoucherResolveStoredFile(?string $storedName): ?array
    {
        $base = basename((string)$storedName);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $base)) {
            return null;
        }
        $full = $this->udaForwardVoucherStorageDir() . DIRECTORY_SEPARATOR . $base;
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

    public function udaForwardVoucherView(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限查看凭证');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'uda_forward_packages')) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }
        $stmt = $conn->prepare('SELECT voucher_path FROM uda_forward_packages WHERE id = ? LIMIT 1');
        if (!$stmt) {
            http_response_code(500);
            exit;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $path = trim((string)($row['voucher_path'] ?? ''));
        $resolved = $path !== '' ? $this->udaForwardVoucherResolveStoredFile($path) : null;
        if (!$resolved) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        header('Content-Type: ' . $resolved['mime']);
        header('X-Content-Type-Options: nosniff');
        readfile($resolved['full']);
        exit;
    }

    public function expressForwardPackages(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问 UDA 转发合包');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'express_uda')
            && $this->tableExists($conn, 'uda_express_forward_packages')
            && $this->tableExists($conn, 'uda_saved_recipients')
            && $this->tableExists($conn, 'uda_forward_packages')
            && $this->tableExists($conn, 'uda_forward_package_items');
        $message = '';
        $error = '';
        $queueRows = [];
        $recipientRows = [];
        $savedRecipientOptions = [];
        $actorId = (int)($_SESSION['auth_user_id'] ?? 0);

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $postAction = trim((string)($_POST['post_action'] ?? ''));
            if ($postAction === 'uda_return_queue') {
                $qid = (int)($_POST['queue_id'] ?? 0);
                if ($qid <= 0) {
                    $error = '参数无效';
                } else {
                    $st = $conn->prepare('SELECT express_id FROM uda_express_forward_packages WHERE id = ? LIMIT 1');
                    if ($st) {
                        $st->bind_param('i', $qid);
                        $st->execute();
                        $hit = $st->get_result()->fetch_assoc();
                        $st->close();
                        if (!$hit) {
                            $error = '记录不存在';
                        } else {
                            $eid = (int)($hit['express_id'] ?? 0);
                            $del = $conn->prepare('DELETE FROM uda_express_forward_packages WHERE id = ?');
                            if ($del) {
                                $del->bind_param('i', $qid);
                                $del->execute();
                                $del->close();
                            }
                            if ($eid > 0) {
                                $up = $conn->prepare('UPDATE express_uda SET is_forwarded = 0, forward_time = NULL WHERE id = ?');
                                if ($up) {
                                    $up->bind_param('i', $eid);
                                    $up->execute();
                                    $up->close();
                                }
                            }
                            header('Location: /uda/express/forward-packages?msg=returned');
                            exit;
                        }
                    } else {
                        $error = '操作失败';
                    }
                }
            } elseif ($postAction === 'uda_recipient_save') {
                $editId = (int)($_POST['recipient_edit_id'] ?? 0);
                $name = trim((string)($_POST['recipient_name'] ?? ''));
                $phone = trim((string)($_POST['recipient_phone'] ?? ''));
                $address = trim((string)($_POST['recipient_address'] ?? ''));
                if ($name === '' || $phone === '' || $address === '') {
                    $error = '收件人、电话、地址均不能为空';
                } elseif ($editId > 0) {
                    $up = $conn->prepare('UPDATE uda_saved_recipients SET recipient_name = ?, phone = ?, address = ? WHERE id = ?');
                    if ($up) {
                        $up->bind_param('sssi', $name, $phone, $address, $editId);
                        if ($up->execute()) {
                            $up->close();
                            header('Location: /uda/express/forward-packages?msg=recipient_saved');
                            exit;
                        }
                        $up->close();
                    }
                    $error = '保存失败';
                } else {
                    $ins = $conn->prepare('INSERT INTO uda_saved_recipients (recipient_name, phone, address, sort_order, created_by) VALUES (?, ?, ?, 0, ?)');
                    if ($ins) {
                        $ins->bind_param('sssi', $name, $phone, $address, $actorId);
                        if ($ins->execute()) {
                            $ins->close();
                            header('Location: /uda/express/forward-packages?msg=recipient_saved');
                            exit;
                        }
                        $ins->close();
                    }
                    $error = '保存失败';
                }
            } elseif ($postAction === 'uda_recipient_delete') {
                $rid = (int)($_POST['recipient_id'] ?? 0);
                if ($rid > 0) {
                    $del = $conn->prepare('DELETE FROM uda_saved_recipients WHERE id = ?');
                    if ($del) {
                        $del->bind_param('i', $rid);
                        $del->execute();
                        $del->close();
                    }
                    header('Location: /uda/express/forward-packages?msg=recipient_deleted');
                    exit;
                }
                $error = '参数无效';
            } elseif ($postAction === 'uda_forward_create_package') {
                $packageNo = trim((string)($_POST['package_no'] ?? ''));
                $sendAt = trim((string)($_POST['send_at'] ?? ''));
                $forwardFeeRaw = trim((string)($_POST['forward_fee'] ?? ''));
                $savedRecipientId = (int)($_POST['saved_recipient_id'] ?? 0);
                $receiverName = trim((string)($_POST['receiver_name'] ?? ''));
                $receiverPhone = trim((string)($_POST['receiver_phone'] ?? ''));
                $receiverAddress = trim((string)($_POST['receiver_address'] ?? ''));
                $remark = trim((string)($_POST['remark'] ?? ''));
                $voucherFile = isset($_FILES['voucher_image']) && is_array($_FILES['voucher_image']) ? $_FILES['voucher_image'] : null;

                if ($packageNo === '' || $sendAt === '') {
                    $error = '转发单号与发出时间必填';
                } elseif ($forwardFeeRaw === '' || !is_numeric($forwardFeeRaw)) {
                    $error = '请填写有效的转发费用';
                } else {
                    $forwardFee = round((float)$forwardFeeRaw, 2);
                    if ($forwardFee < 0) {
                        $error = '转发费用不能为负数';
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

                $selectedQueueIds = [];
                if (isset($_POST['queue_pick']) && is_array($_POST['queue_pick'])) {
                    foreach ($_POST['queue_pick'] as $v) {
                        $selectedQueueIds[] = (int)$v;
                    }
                }
                $selectedQueueIds = array_values(array_unique(array_filter($selectedQueueIds, static fn($x) => $x > 0)));

                if ($error === '') {
                    $dupStmt = $conn->prepare('SELECT id FROM uda_forward_packages WHERE package_no = ? LIMIT 1');
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

                $savedVoucherPath = null;
                if ($error === '') {
                    $saveVoucher = $this->udaForwardVoucherSaveFromUpload($voucherFile, 'udafwd');
                    if (!$saveVoucher['ok']) {
                        $error = $saveVoucher['error'];
                    } else {
                        $savedVoucherPath = (string)($saveVoucher['path'] ?? '');
                    }
                }

                $queueList = [];
                if ($error === '' && !empty($selectedQueueIds)) {
                    $placeholders = implode(',', array_fill(0, count($selectedQueueIds), '?'));
                    $typesList = str_repeat('i', count($selectedQueueIds));
                    $listStmt = $conn->prepare("SELECT id, express_id, source_tracking_no FROM uda_express_forward_packages WHERE id IN ({$placeholders}) ORDER BY id ASC");
                    if ($listStmt) {
                        $listStmt->bind_param($typesList, ...$selectedQueueIds);
                        $listStmt->execute();
                        $lr = $listStmt->get_result();
                        while ($lr && ($r = $lr->fetch_assoc())) {
                            $queueList[] = $r;
                        }
                        $listStmt->close();
                    }
                }

                if ($error === '') {
                    $conn->begin_transaction();
                    try {
                        $pkgId = 0;
                        if ($savedRecipientId > 0) {
                            $ins = $conn->prepare('
                                INSERT INTO uda_forward_packages (
                                    package_no, send_at, forward_fee, saved_recipient_id,
                                    receiver_name, receiver_phone, receiver_address, voucher_path, remark, created_by
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ');
                            if (!$ins) {
                                throw new RuntimeException('insert');
                            }
                            $ins->bind_param(
                                'ssdisssssi',
                                $packageNo,
                                $sendAt,
                                $forwardFee,
                                $savedRecipientId,
                                $receiverName,
                                $receiverPhone,
                                $receiverAddress,
                                $savedVoucherPath,
                                $remark,
                                $actorId
                            );
                            $ins->execute();
                            $pkgId = (int)$ins->insert_id;
                            $ins->close();
                        } else {
                            $ins = $conn->prepare('
                                INSERT INTO uda_forward_packages (
                                    package_no, send_at, forward_fee,
                                    receiver_name, receiver_phone, receiver_address, voucher_path, remark, created_by
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ');
                            if (!$ins) {
                                throw new RuntimeException('insert');
                            }
                            $ins->bind_param(
                                'ssdsssssi',
                                $packageNo,
                                $sendAt,
                                $forwardFee,
                                $receiverName,
                                $receiverPhone,
                                $receiverAddress,
                                $savedVoucherPath,
                                $remark,
                                $actorId
                            );
                            $ins->execute();
                            $pkgId = (int)$ins->insert_id;
                            $ins->close();
                        }

                        $insItem = $conn->prepare('INSERT INTO uda_forward_package_items (forward_package_id, express_id, tracking_no) VALUES (?, ?, ?)');
                        if (!$insItem) {
                            throw new RuntimeException('item');
                        }
                        $queueIds = [];
                        foreach ($queueList as $q) {
                            $eid = (int)($q['express_id'] ?? 0);
                            $tno = trim((string)($q['source_tracking_no'] ?? ''));
                            $insItem->bind_param('iis', $pkgId, $eid, $tno);
                            $insItem->execute();
                            $qid = (int)($q['id'] ?? 0);
                            if ($qid > 0) {
                                $queueIds[] = $qid;
                            }
                        }
                        $insItem->close();

                        if (!empty($queueIds)) {
                            $in = implode(',', array_fill(0, count($queueIds), '?'));
                            $typesDel = str_repeat('i', count($queueIds));
                            $delQ = $conn->prepare("DELETE FROM uda_express_forward_packages WHERE id IN ({$in})");
                            if ($delQ) {
                                $delQ->bind_param($typesDel, ...$queueIds);
                                $delQ->execute();
                                $delQ->close();
                            }
                        }
                        $conn->commit();
                        header('Location: /uda/express/forward-packages?msg=created');
                        exit;
                    } catch (Throwable $e) {
                        $conn->rollback();
                        if (is_string($savedVoucherPath) && $savedVoucherPath !== '') {
                            $full = $this->udaForwardVoucherStorageDir() . DIRECTORY_SEPARATOR . $savedVoucherPath;
                            if (is_file($full)) {
                                @unlink($full);
                            }
                        }
                        $error = '保存转发合包失败，请稍后重试';
                    }
                }
            }
        }

        if ($schemaReady) {
            $qRes = $conn->query("
                SELECT q.id AS queue_id,
                       e.tracking_no,
                       COALESCE(NULLIF(TRIM(e.receiver_name), ''), NULLIF(TRIM(q.forward_receiver), ''), '') AS receiver_display
                FROM uda_express_forward_packages q
                INNER JOIN express_uda e ON e.id = q.express_id
                ORDER BY q.id ASC
            ");
            if ($qRes instanceof mysqli_result) {
                while ($r = $qRes->fetch_assoc()) {
                    $queueRows[] = $r;
                }
                $qRes->free();
            }
            $rRes = $conn->query('SELECT id, recipient_name, phone, address FROM uda_saved_recipients ORDER BY id DESC LIMIT 500');
            if ($rRes instanceof mysqli_result) {
                while ($r = $rRes->fetch_assoc()) {
                    $recipientRows[] = $r;
                    $label = trim((string)($r['recipient_name'] ?? '')) . ' ｜ ' . trim((string)($r['phone'] ?? ''));
                    $savedRecipientOptions[] = [
                        'id' => (int)($r['id'] ?? 0),
                        'label' => $label,
                        'recipient_name' => trim((string)($r['recipient_name'] ?? '')),
                        'phone' => trim((string)($r['phone'] ?? '')),
                        'address' => trim((string)($r['address'] ?? '')),
                    ];
                }
                $rRes->free();
            }
        }

        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'created') {
            $message = '转发合包已保存';
        }
        if ($msg === 'returned') {
            $message = '已从待合包列表移除，并已取消再发出标记';
        }
        if ($msg === 'recipient_saved') {
            $message = '常用收件人已保存';
        }
        if ($msg === 'recipient_deleted') {
            $message = '常用收件人已删除';
        }

        $title = 'UDA快件 / 快件收发 / 转发合包';
        $contentView = __DIR__ . '/../Views/uda/express_forward_packages.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function expressForwardQuery(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问转发查询');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_forward_packages')
            && $this->tableExists($conn, 'uda_forward_package_items');
        $rows = [];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $totalPages = 1;

        $qReceiver = trim((string)($_GET['q_receiver'] ?? ''));
        $qPhone = trim((string)($_GET['q_phone'] ?? ''));
        $qAddress = trim((string)($_GET['q_address'] ?? ''));
        $qFrom = trim((string)($_GET['q_from'] ?? ''));
        $qTo = trim((string)($_GET['q_to'] ?? ''));

        if ($schemaReady) {
            $where = [];
            $types = '';
            $params = [];
            if ($qReceiver !== '') {
                $where[] = 'p.receiver_name LIKE ?';
                $types .= 's';
                $params[] = '%' . $qReceiver . '%';
            }
            if ($qPhone !== '') {
                $where[] = 'p.receiver_phone LIKE ?';
                $types .= 's';
                $params[] = '%' . $qPhone . '%';
            }
            if ($qAddress !== '') {
                $where[] = 'p.receiver_address LIKE ?';
                $types .= 's';
                $params[] = '%' . $qAddress . '%';
            }
            if ($qFrom !== '') {
                $where[] = 'DATE(p.send_at) >= ?';
                $types .= 's';
                $params[] = $qFrom;
            }
            if ($qTo !== '') {
                $where[] = 'DATE(p.send_at) <= ?';
                $types .= 's';
                $params[] = $qTo;
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $countSql = "
                SELECT COUNT(*) AS c
                FROM uda_forward_package_items i
                INNER JOIN uda_forward_packages p ON p.id = i.forward_package_id
                {$whereSql}
            ";
            $countStmt = $conn->prepare($countSql);
            if ($countStmt) {
                if ($types !== '') {
                    $countStmt->bind_param($types, ...$params);
                }
                $countStmt->execute();
                $total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
                $countStmt->close();
            }
            $totalPages = max(1, (int)ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
                $offset = ($page - 1) * $perPage;
            }

            $sql = "
                SELECT
                    i.tracking_no,
                    p.receiver_name,
                    p.receiver_phone,
                    p.receiver_address,
                    p.forward_fee,
                    p.send_at
                FROM uda_forward_package_items i
                INNER JOIN uda_forward_packages p ON p.id = i.forward_package_id
                {$whereSql}
                ORDER BY p.send_at DESC, i.id DESC
                LIMIT {$offset}, {$perPage}
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

        $title = 'UDA快件 / 快件收发 / 转发查询';
        $contentView = __DIR__ . '/../Views/uda/express_forward_query.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function expressReceive(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问快件录入');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'express_uda');
        $message = '';
        $error = '';

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uda_receive_submit'])) {
            $receiveTime = trim((string)($_POST['receive_time'] ?? ''));
            $trackingNo = $this->normalizeTrackingNo((string)($_POST['tracking_no'] ?? ''));
            $receiverName = trim((string)($_POST['receiver_name'] ?? ''));
            $remark = trim((string)($_POST['remark'] ?? ''));
            $createdBy = (int)($_SESSION['auth_user_id'] ?? 0);

            if ($receiveTime === '') {
                $error = '请填写收到时间';
            } elseif ($trackingNo === '') {
                $error = '请填写快递单号';
            } else {
                $dupStmt = $conn->prepare('SELECT id FROM express_uda WHERE tracking_no = ? LIMIT 1');
                if ($dupStmt) {
                    $dupStmt->bind_param('s', $trackingNo);
                    $dupStmt->execute();
                    $dup = $dupStmt->get_result()->fetch_assoc();
                    $dupStmt->close();
                    if ($dup) {
                        $error = '该快递单号已存在';
                    }
                }
            }

            if ($error === '') {
                $ins = $conn->prepare('INSERT INTO express_uda (receive_time, tracking_no, receiver_name, remark, created_by) VALUES (?, ?, ?, ?, ?)');
                if (!$ins) {
                    $error = '保存失败';
                } else {
                    $ins->bind_param('ssssi', $receiveTime, $trackingNo, $receiverName, $remark, $createdBy);
                    if ($ins->execute()) {
                        header('Location: /uda/express/receive?msg=created');
                        exit;
                    }
                    $ins->close();
                    if ($error === '') {
                        $error = '保存失败';
                    }
                }
            }
        }

        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '快件录入成功';
        }

        $title = 'UDA快件 / 快件收发 / 快件录入';
        $contentView = __DIR__ . '/../Views/uda/express_receive.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function expressQuery(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问快件查询');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'express_uda');
        $rows = [];
        $message = '';
        $error = '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $totalPages = 1;

        if ($schemaReady) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = trim((string)($_POST['action'] ?? ''));
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = '参数无效';
                } elseif ($action === 'forward_row') {
                    $stmt = $conn->prepare('SELECT id, tracking_no, receiver_name, remark, is_forwarded, forward_time, forward_tracking_no, forward_receiver, forward_fee, forward_remark FROM express_uda WHERE id = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if (!$row) {
                            $error = '记录不存在';
                        } else {
                            $forwardTime = trim((string)($row['forward_time'] ?? ''));
                            if ($forwardTime === '') {
                                $forwardTime = date('Y-m-d H:i:s');
                            }
                            $forwardTracking = trim((string)($row['forward_tracking_no'] ?? ''));
                            if ($forwardTracking === '') {
                                $forwardTracking = trim((string)($row['tracking_no'] ?? ''));
                            }
                            $forwardReceiver = trim((string)($row['forward_receiver'] ?? ''));
                            if ($forwardReceiver === '') {
                                $forwardReceiver = trim((string)($row['receiver_name'] ?? ''));
                            }
                            $forwardRemark = trim((string)($row['forward_remark'] ?? ''));
                            if ($forwardRemark === '') {
                                $forwardRemark = trim((string)($row['remark'] ?? ''));
                            }
                            $up = $conn->prepare('UPDATE express_uda SET is_forwarded = 1, forward_time = ?, forward_tracking_no = ?, forward_receiver = ?, forward_remark = ? WHERE id = ?');
                            if ($up) {
                                $up->bind_param('ssssi', $forwardTime, $forwardTracking, $forwardReceiver, $forwardRemark, $id);
                                $ok = $up->execute();
                                $up->close();
                                if ($ok) {
                                    if ($this->tableExists($conn, 'uda_express_forward_packages')) {
                                        $insF = $conn->prepare('
                                            INSERT INTO uda_express_forward_packages
                                            (express_id, source_tracking_no, forward_tracking_no, forward_receiver, forward_fee, forward_remark, forwarded_at, created_at)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                                            ON DUPLICATE KEY UPDATE
                                                source_tracking_no = VALUES(source_tracking_no),
                                                forward_tracking_no = VALUES(forward_tracking_no),
                                                forward_receiver = VALUES(forward_receiver),
                                                forward_fee = VALUES(forward_fee),
                                                forward_remark = VALUES(forward_remark),
                                                forwarded_at = VALUES(forwarded_at)
                                        ');
                                        if ($insF) {
                                            $forwardFee = ($row['forward_fee'] ?? null);
                                            $insF->bind_param(
                                                'isssdss',
                                                $id,
                                                $row['tracking_no'],
                                                $forwardTracking,
                                                $forwardReceiver,
                                                $forwardFee,
                                                $forwardRemark,
                                                $forwardTime
                                            );
                                            $insF->execute();
                                            $insF->close();
                                        }
                                    }
                                    header('Location: /uda/express/query?msg=forwarded');
                                    exit;
                                }
                            }
                            $error = '转发失败';
                        }
                    } else {
                        $error = '转发失败';
                    }
                } elseif ($action === 'edit_row') {
                    $trackingNo = $this->normalizeTrackingNo((string)($_POST['tracking_no'] ?? ''));
                    $receiverName = trim((string)($_POST['receiver_name'] ?? ''));
                    $remark = trim((string)($_POST['remark'] ?? ''));

                    if ($trackingNo === '') {
                        $error = '快递单号不能为空';
                    } else {
                        $dup = $conn->prepare('SELECT id FROM express_uda WHERE tracking_no = ? AND id <> ? LIMIT 1');
                        if ($dup) {
                            $dup->bind_param('si', $trackingNo, $id);
                            $dup->execute();
                            $dupRow = $dup->get_result()->fetch_assoc();
                            $dup->close();
                            if ($dupRow) {
                                $error = '快递单号已存在';
                            }
                        }
                    }
                    if ($error === '') {
                        $sql = 'UPDATE express_uda SET tracking_no=?, receiver_name=?, remark=? WHERE id=?';
                        $up = $conn->prepare($sql);
                        if ($up) {
                            $up->bind_param(
                                'sssi',
                                $trackingNo,
                                $receiverName,
                                $remark,
                                $id
                            );
                            $ok = $up->execute();
                            $up->close();
                            if ($ok) {
                                header('Location: /uda/express/query?msg=updated');
                                exit;
                            }
                        }
                        $error = '修改失败';
                    }
                }
            }

            $qTrack = $this->normalizeTrackingNo((string)($_GET['q_track'] ?? ''));
            $qDateFrom = trim((string)($_GET['q_date_from'] ?? ''));
            $qDateTo = trim((string)($_GET['q_date_to'] ?? ''));
            $qForwarded = trim((string)($_GET['q_forwarded'] ?? ''));

            $where = [];
            $types = '';
            $params = [];
            if ($qTrack !== '') {
                $where[] = 'e.tracking_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qTrack . '%';
            }
            if ($qDateFrom !== '') {
                $where[] = 'DATE(e.created_at) >= ?';
                $types .= 's';
                $params[] = $qDateFrom;
            }
            if ($qDateTo !== '') {
                $where[] = 'DATE(e.created_at) <= ?';
                $types .= 's';
                $params[] = $qDateTo;
            }
            if ($qForwarded === '0' || $qForwarded === '1') {
                $where[] = 'e.is_forwarded = ?';
                $types .= 'i';
                $params[] = (int)$qForwarded;
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $countSql = "SELECT COUNT(*) AS c FROM express_uda e {$whereSql}";
            $countStmt = $conn->prepare($countSql);
            if ($countStmt) {
                if ($types !== '') {
                    $countStmt->bind_param($types, ...$params);
                }
                $countStmt->execute();
                $total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
                $countStmt->close();
            }
            $totalPages = max(1, (int)ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
                $offset = ($page - 1) * $perPage;
            }

            $sql = "
                SELECT
                    e.id, e.tracking_no, e.receiver_name, e.remark,
                    e.is_forwarded, e.forward_time, e.forward_tracking_no, e.forward_receiver, e.forward_fee,
                    e.created_at, u.full_name AS created_by_name
                FROM express_uda e
                LEFT JOIN users u ON u.id = e.created_by
                {$whereSql}
                ORDER BY e.id DESC
                LIMIT {$offset}, {$perPage}
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
        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'forwarded') $message = '已推送至转发合包，并标记为已再发出';
        if ($msg === 'updated') $message = '记录修改成功';

        $title = 'UDA快件 / 快件收发 / 快件查询';
        $contentView = __DIR__ . '/../Views/uda/express_query.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function issueCreate(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问问题订单录入');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'problem_orders')
            && $this->tableExists($conn, 'problem_order_locations')
            && $this->tableExists($conn, 'problem_order_reason_options');
        $message = '';
        $error = '';
        $locationOptions = [];
        $reasonMap = [];
        $reasonOptions = [];
        $showAlertError = false;

        if ($schemaReady) {
            $locRes = $conn->query("SELECT id, location_name FROM problem_order_locations WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            if ($locRes instanceof mysqli_result) {
                while ($r = $locRes->fetch_assoc()) {
                    $locationOptions[] = $r;
                }
                $locRes->free();
            }
            $reaRes = $conn->query("SELECT id, location_id, reason_name FROM problem_order_reason_options WHERE is_active = 1 ORDER BY location_id ASC, sort_order ASC, id ASC");
            if ($reaRes instanceof mysqli_result) {
                while ($r = $reaRes->fetch_assoc()) {
                    $lid = (int)($r['location_id'] ?? 0);
                    $reasonName = trim((string)($r['reason_name'] ?? ''));
                    if (!isset($reasonMap[$lid])) $reasonMap[$lid] = [];
                    $reasonMap[$lid][] = ['id' => (int)$r['id'], 'reason_name' => $reasonName];
                    if ($reasonName !== '' && !in_array($reasonName, $reasonOptions, true)) {
                        $reasonOptions[] = $reasonName;
                    }
                }
                $reaRes->free();
            }
        }

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['problem_create_submit'])) {
            $trackingNo = $this->normalizeTrackingNo((string)($_POST['tracking_no'] ?? ''));
            $locationId = (int)($_POST['location_id'] ?? 0);
            $problemReasonSelect = trim((string)($_POST['problem_reason_select'] ?? ''));
            $problemReasonText = trim((string)($_POST['problem_reason_text'] ?? ''));
            $problemReasonLegacy = trim((string)($_POST['problem_reason'] ?? ''));
            $problemReason = $problemReasonText !== ''
                ? $problemReasonText
                : ($problemReasonSelect !== '' ? $problemReasonSelect : $problemReasonLegacy);
            $remark = trim((string)($_POST['remark'] ?? ''));

            if ($trackingNo === '') {
                $error = '请输入面单号';
            } elseif ($locationId <= 0) {
                $error = '请选择地点';
            } elseif ($problemReason === '') {
                $error = '请选择或填写问题原因';
            } else {
                $hasProcessStatusCol = $this->columnExists($conn, 'problem_orders', 'process_status');
                if ($hasProcessStatusCol) {
                    $dupSql = "SELECT id FROM problem_orders WHERE tracking_no = ? AND COALESCE(NULLIF(TRIM(process_status), ''), CASE WHEN COALESCE(is_processed,0)=1 THEN '已处理' ELSE '未处理' END) IN ('未处理','处理中') LIMIT 1";
                } else {
                    $dupSql = "SELECT id FROM problem_orders WHERE tracking_no = ? AND COALESCE(is_processed,0) = 0 LIMIT 1";
                }

                $dupStmt = $conn->prepare($dupSql);
                if ($dupStmt) {
                    $dupStmt->bind_param('s', $trackingNo);
                    $dupStmt->execute();
                    $dupRow = $dupStmt->get_result()->fetch_assoc();
                    $dupStmt->close();
                    if ($dupRow) {
                        $error = '此面单号有其它问题未处理完成';
                        $showAlertError = true;
                    }
                }

                if ($error === '') {
                    if ($hasProcessStatusCol) {
                        $ins = $conn->prepare("INSERT INTO problem_orders (tracking_no, location_id, problem_reason, handle_method, is_processed, process_status, remark, created_at) VALUES (?, ?, ?, '', 0, '未处理', ?, NOW())");
                    } else {
                        $ins = $conn->prepare("INSERT INTO problem_orders (tracking_no, location_id, problem_reason, handle_method, is_processed, remark, created_at) VALUES (?, ?, ?, '', 0, ?, NOW())");
                    }
                    if (!$ins) {
                        $error = '保存失败';
                    } else {
                        $ins->bind_param('siss', $trackingNo, $locationId, $problemReason, $remark);
                        if ($ins->execute()) {
                            header('Location: /uda/issues/create?msg=created');
                            exit;
                        }
                        $ins->close();
                        if ($error === '') $error = '保存失败';
                    }
                }
            }
        }

        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '问题订单录入成功';
        }

        $title = 'UDA快件 / 问题订单 / 问题订单录入';
        $contentView = __DIR__ . '/../Views/uda/issues_create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function issueList(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问问题订单列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'problem_orders')
            && $this->tableExists($conn, 'problem_order_locations');
        $hasProcessStatusCol = $schemaReady && $this->columnExists($conn, 'problem_orders', 'process_status');
        $rows = [];
        $locationOptions = [];
        $reasonOptions = [];
        $reasonMap = [];
        $handleMethodOptions = [];
        $message = '';
        $error = '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $totalPages = 1;

        if ($schemaReady) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_handle_submit'])) {
                $id = (int)($_POST['id'] ?? 0);
                $newHandleMethodSelect = trim((string)($_POST['handle_method_select'] ?? ''));
                $newHandleMethodText = trim((string)($_POST['handle_method_text'] ?? ''));
                $newHandleMethodLegacy = trim((string)($_POST['handle_method'] ?? ''));
                $newHandleMethod = $newHandleMethodText !== ''
                    ? $newHandleMethodText
                    : ($newHandleMethodSelect !== '' ? $newHandleMethodSelect : $newHandleMethodLegacy);
                $newRemark = trim((string)($_POST['remark'] ?? ''));
                $newProcessStatus = trim((string)($_POST['process_status'] ?? '未处理'));
                if (!in_array($newProcessStatus, ['未处理', '处理中', '已处理'], true)) {
                    $newProcessStatus = '未处理';
                }

                if ($id <= 0) {
                    $error = '参数无效';
                } else {
                    $statusExpr = $hasProcessStatusCol
                        ? "COALESCE(NULLIF(TRIM(process_status), ''), CASE WHEN COALESCE(is_processed,0)=1 THEN '已处理' ELSE '未处理' END) AS process_status_text"
                        : "CASE WHEN COALESCE(is_processed,0)=1 THEN '已处理' ELSE '未处理' END AS process_status_text";
                    $curStmt = $conn->prepare("SELECT id, handle_method, remark, {$statusExpr} FROM problem_orders WHERE id = ? LIMIT 1");
                    if ($curStmt) {
                        $curStmt->bind_param('i', $id);
                        $curStmt->execute();
                        $cur = $curStmt->get_result()->fetch_assoc();
                        $curStmt->close();
                        if (!$cur) {
                            $error = '记录不存在';
                        } else {
                            $oldHandleMethod = trim((string)($cur['handle_method'] ?? ''));
                            $oldRemark = trim((string)($cur['remark'] ?? ''));
                            $oldProcessStatus = trim((string)($cur['process_status_text'] ?? '未处理'));
                            $changed = $newHandleMethod !== $oldHandleMethod
                                || $newRemark !== $oldRemark
                                || $newProcessStatus !== $oldProcessStatus;

                            if ($changed) {
                                $newIsProcessed = $newProcessStatus === '已处理' ? 1 : 0;
                                if ($hasProcessStatusCol) {
                                    $up = $conn->prepare('UPDATE problem_orders SET handle_method = ?, remark = ?, process_status = ?, is_processed = ?, processed_at = NOW() WHERE id = ?');
                                    if ($up) {
                                        $up->bind_param('sssii', $newHandleMethod, $newRemark, $newProcessStatus, $newIsProcessed, $id);
                                        $ok = $up->execute();
                                        $up->close();
                                        if ($ok) {
                                            header('Location: /uda/issues/list?msg=updated');
                                            exit;
                                        }
                                    }
                                    $error = '保存处理结果失败';
                                } else {
                                    $up = $conn->prepare('UPDATE problem_orders SET handle_method = ?, remark = ?, is_processed = ?, processed_at = NOW() WHERE id = ?');
                                    if ($up) {
                                        $up->bind_param('ssii', $newHandleMethod, $newRemark, $newIsProcessed, $id);
                                        $ok = $up->execute();
                                        $up->close();
                                        if ($ok) {
                                            header('Location: /uda/issues/list?msg=updated');
                                            exit;
                                        }
                                    }
                                    $error = '保存处理结果失败';
                                }
                            } else {
                                header('Location: /uda/issues/list?msg=nochange');
                                exit;
                            }
                        }
                    } else {
                        $error = '保存处理结果失败';
                    }
                }
            }

            $locRes = $conn->query("SELECT id, location_name FROM problem_order_locations WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            if ($locRes instanceof mysqli_result) {
                while ($r = $locRes->fetch_assoc()) {
                    $locationOptions[] = $r;
                }
                $locRes->free();
            }
            if ($this->tableExists($conn, 'problem_order_reason_options')) {
                $reasonRes = $conn->query("SELECT location_id, reason_name FROM problem_order_reason_options WHERE is_active = 1 AND TRIM(COALESCE(reason_name,''))<>'' ORDER BY location_id ASC, sort_order ASC, id ASC");
                if ($reasonRes instanceof mysqli_result) {
                    while ($r = $reasonRes->fetch_assoc()) {
                        $lid = (int)($r['location_id'] ?? 0);
                        $name = trim((string)($r['reason_name'] ?? ''));
                        if ($name !== '') {
                            if (!isset($reasonMap[$lid])) $reasonMap[$lid] = [];
                            if (!in_array($name, $reasonMap[$lid], true)) {
                                $reasonMap[$lid][] = $name;
                            }
                            $reasonOptions[] = $name;
                        }
                    }
                    $reasonRes->free();
                }
                $reasonOptions = array_values(array_unique($reasonOptions));
            }
            if ($this->tableExists($conn, 'problem_order_handle_methods')) {
                $hmRes = $conn->query("SELECT method_name FROM problem_order_handle_methods WHERE is_active = 1 AND TRIM(COALESCE(method_name,''))<>'' ORDER BY sort_order ASC, id ASC");
                if ($hmRes instanceof mysqli_result) {
                    while ($r = $hmRes->fetch_assoc()) {
                        $name = trim((string)($r['method_name'] ?? ''));
                        if ($name !== '') {
                            $handleMethodOptions[] = $name;
                        }
                    }
                    $hmRes->free();
                }
            }

            $qTrack = $this->normalizeTrackingNo((string)($_GET['q_track'] ?? ''));
            $qLocationId = (int)($_GET['q_location_id'] ?? 0);
            $qReasonSelect = trim((string)($_GET['q_reason_select'] ?? ''));
            $qReasonText = trim((string)($_GET['q_reason_text'] ?? ''));
            $qReason = $qReasonText !== '' ? $qReasonText : $qReasonSelect;
            $qProcessed = (string)($_GET['q_processed'] ?? '');
            $qFrom = trim((string)($_GET['q_from'] ?? ''));
            $qTo = trim((string)($_GET['q_to'] ?? ''));

            $where = [];
            $types = '';
            $params = [];
            if ($qTrack !== '') {
                $where[] = 'po.tracking_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qTrack . '%';
            }
            if ($qLocationId > 0) {
                $where[] = 'po.location_id = ?';
                $types .= 'i';
                $params[] = $qLocationId;
            }
            if ($qReason !== '') {
                $where[] = 'po.problem_reason = ?';
                $types .= 's';
                $params[] = $qReason;
            }
            if (in_array($qProcessed, ['未处理', '处理中', '已处理'], true)) {
                if ($hasProcessStatusCol) {
                    $where[] = "COALESCE(NULLIF(TRIM(po.process_status), ''), CASE WHEN COALESCE(po.is_processed,0)=1 THEN '已处理' ELSE '未处理' END) = ?";
                    $types .= 's';
                    $params[] = $qProcessed;
                } elseif ($qProcessed === '处理中') {
                    $where[] = '1 = 0';
                } else {
                    $where[] = 'po.is_processed = ?';
                    $types .= 'i';
                    $params[] = $qProcessed === '已处理' ? 1 : 0;
                }
            }
            if ($qFrom !== '') {
                $where[] = 'po.created_at >= ?';
                $types .= 's';
                $params[] = $qFrom . ' 00:00:00';
            }
            if ($qTo !== '') {
                $where[] = 'po.created_at <= ?';
                $types .= 's';
                $params[] = $qTo . ' 23:59:59';
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $countSql = "SELECT COUNT(*) AS c FROM problem_orders po {$whereSql}";
            $countStmt = $conn->prepare($countSql);
            if ($countStmt) {
                if ($types !== '') $countStmt->bind_param($types, ...$params);
                $countStmt->execute();
                $total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
                $countStmt->close();
            }
            $totalPages = max(1, (int)ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
                $offset = ($page - 1) * $perPage;
            }

            $sql = "
                SELECT po.id, po.tracking_no, po.problem_reason, po.handle_method, po.is_processed, po.created_at, po.processed_at, po.remark,
                       " . ($hasProcessStatusCol
                        ? "COALESCE(NULLIF(TRIM(po.process_status), ''), CASE WHEN COALESCE(po.is_processed,0)=1 THEN '已处理' ELSE '未处理' END)"
                        : "CASE WHEN COALESCE(po.is_processed,0)=1 THEN '已处理' ELSE '未处理' END") . " AS process_status_text,
                       pol.location_name
                FROM problem_orders po
                LEFT JOIN problem_order_locations pol ON pol.id = po.location_id
                {$whereSql}
                ORDER BY po.id DESC
                LIMIT {$offset}, {$perPage}
            ";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($types !== '') $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $rows[] = $r;
                }
                $stmt->close();
            }
        }
        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'updated') $message = '处理结果已保存';
        if ($msg === 'nochange') $message = '未检测到变更，未更新处理时间';

        $title = 'UDA快件 / 问题订单 / 问题订单列表';
        $contentView = __DIR__ . '/../Views/uda/issues_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function issueHandleMethods(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问处理方式管理');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'problem_order_handle_methods');
        $message = '';
        $error = '';
        $rows = [];

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'add_method') {
                $name = trim((string)($_POST['method_name'] ?? ''));
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($name === '') {
                    $error = '处理方式名称不能为空';
                } else {
                    $ins = $conn->prepare('INSERT INTO problem_order_handle_methods (method_name, sort_order, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
                    if ($ins) {
                        $ins->bind_param('si', $name, $sort);
                        if ($ins->execute()) {
                            header('Location: /uda/issues/handle-methods?msg=added');
                            exit;
                        }
                        $ins->close();
                    }
                    if ($error === '') $error = '新增处理方式失败';
                }
            } elseif ($action === 'disable_method') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $up = $conn->prepare('UPDATE problem_order_handle_methods SET is_active = 0 WHERE id = ?');
                    if ($up) {
                        $up->bind_param('i', $id);
                        $up->execute();
                        $up->close();
                        header('Location: /uda/issues/handle-methods?msg=disabled');
                        exit;
                    }
                }
                $error = '停用处理方式失败';
            }
        }

        if ($schemaReady) {
            $res = $conn->query('SELECT id, method_name, sort_order, is_active FROM problem_order_handle_methods ORDER BY is_active DESC, sort_order ASC, id ASC');
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
            }
        }
        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'added') $message = '处理方式新增成功';
        if ($msg === 'disabled') $message = '处理方式已停用';

        $title = 'UDA快件 / 问题订单 / 处理方式管理';
        $contentView = __DIR__ . '/../Views/uda/issues_handle_methods.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function issueLocations(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问地点管理');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'problem_order_locations');
        $message = '';
        $error = '';
        $rows = [];

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'add_location') {
                $name = trim((string)($_POST['location_name'] ?? ''));
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($name === '') {
                    $error = '地点名称不能为空';
                } else {
                    $ins = $conn->prepare('INSERT INTO problem_order_locations (location_name, sort_order, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
                    if ($ins) {
                        $ins->bind_param('si', $name, $sort);
                        if ($ins->execute()) {
                            header('Location: /uda/issues/locations?msg=added');
                            exit;
                        }
                        $ins->close();
                    }
                    if ($error === '') $error = '新增地点失败';
                }
            } elseif ($action === 'disable_location') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $up = $conn->prepare('UPDATE problem_order_locations SET is_active = 0 WHERE id = ?');
                    if ($up) {
                        $up->bind_param('i', $id);
                        $up->execute();
                        $up->close();
                        header('Location: /uda/issues/locations?msg=disabled');
                        exit;
                    }
                }
                $error = '停用地点失败';
            }
        }

        if ($schemaReady) {
            $res = $conn->query('SELECT id, location_name, sort_order, is_active FROM problem_order_locations ORDER BY is_active DESC, sort_order ASC, id ASC');
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
            }
        }
        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'added') $message = '地点新增成功';
        if ($msg === 'disabled') $message = '地点已停用';

        $title = 'UDA快件 / 问题订单 / 地点管理';
        $contentView = __DIR__ . '/../Views/uda/issues_locations.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function issueReasons(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问问题原因管理');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'problem_order_locations')
            && $this->tableExists($conn, 'problem_order_reason_options');
        $message = '';
        $error = '';
        $locations = [];
        $rows = [];

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'add_reason') {
                $locationId = (int)($_POST['location_id'] ?? 0);
                $name = trim((string)($_POST['reason_name'] ?? ''));
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($locationId <= 0 || $name === '') {
                    $error = '地点与原因名称不能为空';
                } else {
                    $ins = $conn->prepare('INSERT INTO problem_order_reason_options (location_id, reason_name, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())');
                    if ($ins) {
                        $ins->bind_param('isi', $locationId, $name, $sort);
                        if ($ins->execute()) {
                            header('Location: /uda/issues/reasons?msg=added');
                            exit;
                        }
                        $ins->close();
                    }
                    if ($error === '') $error = '新增原因失败';
                }
            } elseif ($action === 'disable_reason') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $up = $conn->prepare('UPDATE problem_order_reason_options SET is_active = 0 WHERE id = ?');
                    if ($up) {
                        $up->bind_param('i', $id);
                        $up->execute();
                        $up->close();
                        header('Location: /uda/issues/reasons?msg=disabled');
                        exit;
                    }
                }
                $error = '停用原因失败';
            }
        }

        if ($schemaReady) {
            $resLoc = $conn->query('SELECT id, location_name, is_active FROM problem_order_locations ORDER BY sort_order ASC, id ASC');
            if ($resLoc instanceof mysqli_result) {
                while ($row = $resLoc->fetch_assoc()) {
                    $locations[] = $row;
                }
                $resLoc->free();
            }
            $res = $conn->query('
                SELECT r.id, r.location_id, r.reason_name, r.sort_order, r.is_active, l.location_name
                FROM problem_order_reason_options r
                LEFT JOIN problem_order_locations l ON l.id = r.location_id
                ORDER BY r.is_active DESC, l.sort_order ASC, r.sort_order ASC, r.id ASC
            ');
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
            }
        }
        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'added') $message = '问题原因新增成功';
        if ($msg === 'disabled') $message = '问题原因已停用';

        $title = 'UDA快件 / 问题订单 / 问题原因管理';
        $contentView = __DIR__ . '/../Views/uda/issues_reasons.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    private function manifestVolumeM3FromCm(float $lCm, float $wCm, float $hCm): float
    {
        $lm = max(0.0, $lCm) / 100.0;
        $wm = max(0.0, $wCm) / 100.0;
        $hm = max(0.0, $hCm) / 100.0;
        return round($lm * $wm * $hm, 6);
    }

    /**
     * @return list<string>
     */
    private function manifestParseWaybillLines(string $raw): array
    {
        $lines = preg_split('/\R+/u', trim($raw)) ?: [];
        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $n = $this->normalizeTrackingNo((string)$line);
            if ($n === '' || isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            $out[] = $n;
        }
        return $out;
    }

    /**
     * @return array{total_weight:float,total_volume:float,bundle_count:int,next_seq:int,total_pieces:int}
     */
    private function manifestBatchTotals(mysqli $conn, int $manifestId): array
    {
        $tw = 0.0;
        $tv = 0.0;
        $bc = 0;
        $st = $conn->prepare('SELECT COALESCE(SUM(weight_kg),0) AS w, COALESCE(SUM(volume_m3),0) AS v, COUNT(*) AS c FROM uda_manifest_bundles WHERE batch_id = ?');
        if ($st) {
            $st->bind_param('i', $manifestId);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) {
                $tw = (float)($r['w'] ?? 0);
                $tv = (float)($r['v'] ?? 0);
                $bc = (int)($r['c'] ?? 0);
            }
        }
        $pieces = 0;
        $pc = $conn->prepare('SELECT COUNT(*) AS c FROM uda_manifest_bundle_waybills WHERE batch_id = ?');
        if ($pc) {
            $pc->bind_param('i', $manifestId);
            $pc->execute();
            $pr = $pc->get_result()->fetch_assoc();
            $pc->close();
            $pieces = (int)($pr['c'] ?? 0);
        }
        $nextSeq = $bc + 1;
        return ['total_weight' => $tw, 'total_volume' => $tv, 'bundle_count' => $bc, 'next_seq' => $nextSeq, 'total_pieces' => $pieces];
    }

    public function manifestWaybillCheck(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            header('Content-Type: application/json; charset=utf-8', true, 403);
            echo json_encode(['ok' => false, 'error' => '无权限'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Content-Type: application/json; charset=utf-8', true, 405);
            echo json_encode(['ok' => false, 'error' => '仅支持 GET'], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $tracking = $this->normalizeTrackingNo((string)($_GET['tracking_no'] ?? ''));
        $currentBatchId = (int)($_GET['batch_id'] ?? 0);
        if ($tracking === '') {
            echo json_encode(['ok' => false, 'error' => '面单号为空'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $conn = require __DIR__ . '/../../config/database.php';
        if (!$this->tableExists($conn, 'uda_manifest_bundle_waybills')) {
            echo json_encode(['ok' => true, 'exists' => false, 'schema' => false], JSON_UNESCAPED_UNICODE);
            return;
        }
        $hasDateNoCol = $this->columnExists($conn, 'uda_manifest_batches', 'date_no');
        $hasBillNoCol = $this->columnExists($conn, 'uda_manifest_batches', 'bill_no');
        $sql = $hasDateNoCol && $hasBillNoCol
            ? 'SELECT w.batch_id, b.batch_code, b.date_no, b.bill_no, b.status FROM uda_manifest_bundle_waybills w INNER JOIN uda_manifest_batches b ON b.id = w.batch_id WHERE w.tracking_no = ? LIMIT 1'
            : 'SELECT w.batch_id, b.batch_code, \'\' AS date_no, \'\' AS bill_no, b.status FROM uda_manifest_bundle_waybills w INNER JOIN uda_manifest_batches b ON b.id = w.batch_id WHERE w.tracking_no = ? LIMIT 1';
        $st = $conn->prepare($sql);
        if (!$st) {
            echo json_encode(['ok' => false, 'error' => '查询失败'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $st->bind_param('s', $tracking);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            echo json_encode(['ok' => true, 'exists' => false], JSON_UNESCAPED_UNICODE);
            return;
        }
        $bid = (int)($row['batch_id'] ?? 0);
        $sameOpenBatch = $currentBatchId > 0 && $bid === $currentBatchId;
        echo json_encode([
            'ok' => true,
            'exists' => true,
            'date_no' => (string)($row['date_no'] ?? ''),
            'bill_no' => (string)($row['bill_no'] ?? ''),
            'batch_code' => (string)($row['batch_code'] ?? ''),
            'batch_status' => (string)($row['status'] ?? ''),
            'same_open_batch' => $sameOpenBatch,
        ], JSON_UNESCAPED_UNICODE);
    }

    // Legacy name kept for route compatibility: "batch" == manifest/shipment ticket.
    public function batchCreate(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问集包录入');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_manifest_batches')
            && $this->columnExists($conn, 'uda_manifest_batches', 'date_no')
            && $this->columnExists($conn, 'uda_manifest_batches', 'bill_no')
            && $this->tableExists($conn, 'uda_manifest_bundles')
            && $this->tableExists($conn, 'uda_manifest_bundle_waybills');
        $message = '';
        $error = '';
        $sessionKey = 'uda_manifest_batch_id';
        $currentBatch = null;
        $totals = ['total_weight' => 0.0, 'total_volume' => 0.0, 'bundle_count' => 0, 'next_seq' => 1, 'total_pieces' => 0];

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            $createdBy = (int)($_SESSION['auth_user_id'] ?? 0);

            if ($action === 'set_batch') {
                $dateNo = trim((string)($_POST['date_no'] ?? $_POST['batch_code'] ?? ''));
                $billNo = trim((string)($_POST['bill_no'] ?? ''));
                if ($dateNo === '') {
                    $error = '请填写日期号';
                } elseif (mb_strlen($dateNo) > 100) {
                    $error = '日期号过长';
                } elseif ($billNo !== '' && mb_strlen($billNo) > 100) {
                    $error = '提单号过长';
                } else {
                    $bid = 0;
                    $sel = $conn->prepare("SELECT id, status, bill_no FROM uda_manifest_batches WHERE date_no = ? OR (date_no = '' AND batch_code = ?) LIMIT 1");
                    if ($sel) {
                        $sel->bind_param('ss', $dateNo, $dateNo);
                        $sel->execute();
                        $row = $sel->get_result()->fetch_assoc();
                        $sel->close();
                        if ($row) {
                            $st = (string)($row['status'] ?? '');
                            if ($st === 'open') {
                                $bid = (int)$row['id'];
                                if ($billNo !== '' && trim((string)($row['bill_no'] ?? '')) !== $billNo) {
                                    $upBill = $conn->prepare('UPDATE uda_manifest_batches SET bill_no = ? WHERE id = ?');
                                    if ($upBill) {
                                        $upBill->bind_param('si', $billNo, $bid);
                                        $upBill->execute();
                                        $upBill->close();
                                    }
                                }
                            } else {
                                $error = '该日期号已存在且已结束，全库不可重复。请更换日期号。';
                            }
                        }
                    }
                    if ($error === '' && $bid <= 0) {
                        $insErr = 0;
                        if ($createdBy > 0) {
                            $ins = $conn->prepare("INSERT INTO uda_manifest_batches (batch_code, date_no, bill_no, status, created_by) VALUES (?, ?, ?, 'open', ?)");
                            if ($ins) {
                                $ins->bind_param('sssi', $dateNo, $dateNo, $billNo, $createdBy);
                                if ($ins->execute()) {
                                    $bid = (int)$ins->insert_id;
                                } else {
                                    $insErr = (int)$ins->errno;
                                }
                                $ins->close();
                            }
                        } else {
                            $ins = $conn->prepare("INSERT INTO uda_manifest_batches (batch_code, date_no, bill_no, status, created_by) VALUES (?, ?, ?, 'open', NULL)");
                            if ($ins) {
                                $ins->bind_param('sss', $dateNo, $dateNo, $billNo);
                                if ($ins->execute()) {
                                    $bid = (int)$ins->insert_id;
                                } else {
                                    $insErr = (int)$ins->errno;
                                }
                                $ins->close();
                            }
                        }
                        if ($bid <= 0) {
                            if ($error === '' && $insErr === 1062) {
                                $error = '该日期号已存在（全库唯一），请刷新后从进行中的日期号继续或更换号码';
                            } elseif ($error === '') {
                                $error = '创建集包主档失败';
                            }
                        }
                    }
                    if ($error === '' && $bid > 0) {
                        $_SESSION[$sessionKey] = $bid;
                        header('Location: /uda/batches/create?msg=batch_ready');
                        exit;
                    }
                }
            } elseif ($action === 'complete_bundle') {
                $manifestId = (int)($_POST['batch_id'] ?? 0);
                $sid = (int)($_SESSION[$sessionKey] ?? 0);
                if ($manifestId <= 0 || $sid !== $manifestId) {
                    $error = '会话已失效，请重新设定日期号';
                } else {
                    $chk = $conn->prepare("SELECT id, status FROM uda_manifest_batches WHERE id = ? LIMIT 1");
                    $open = false;
                    if ($chk) {
                        $chk->bind_param('i', $manifestId);
                        $chk->execute();
                        $br = $chk->get_result()->fetch_assoc();
                        $chk->close();
                        $open = $br && ($br['status'] ?? '') === 'open';
                    }
                    if (!$open) {
                        $error = '该日期号已结束或不存在';
                    } else {
                        $waybills = $this->manifestParseWaybillLines((string)($_POST['waybill_lines'] ?? ''));
                        $weight = (float)str_replace(',', '.', trim((string)($_POST['weight_kg'] ?? '')));
                        $len = (float)str_replace(',', '.', trim((string)($_POST['length_cm'] ?? '')));
                        $wid = (float)str_replace(',', '.', trim((string)($_POST['width_cm'] ?? '')));
                        $hei = (float)str_replace(',', '.', trim((string)($_POST['height_cm'] ?? '')));
                        if ($waybills === []) {
                            $error = '请至少扫描录入一个面单号';
                        } elseif ($weight <= 0) {
                            $error = '重量须大于 0';
                        } elseif ($len <= 0 || $wid <= 0 || $hei <= 0) {
                            $error = '长、宽、高须均大于 0';
                        } else {
                            $vol = $this->manifestVolumeM3FromCm($len, $wid, $hei);
                            $conn->begin_transaction();
                            try {
                                $seqSt = $conn->prepare('SELECT COALESCE(MAX(bundle_seq), 0) + 1 AS n FROM uda_manifest_bundles WHERE batch_id = ? FOR UPDATE');
                                $bundleSeq = 1;
                                if ($seqSt) {
                                    $seqSt->bind_param('i', $manifestId);
                                    $seqSt->execute();
                                    $nr = $seqSt->get_result()->fetch_assoc();
                                    $seqSt->close();
                                    $bundleSeq = max(1, (int)($nr['n'] ?? 1));
                                }
                                $insB = $conn->prepare('INSERT INTO uda_manifest_bundles (batch_id, bundle_seq, weight_kg, length_cm, width_cm, height_cm, volume_m3) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if (!$insB) {
                                    throw new RuntimeException('bundle');
                                }
                                $insB->bind_param('iiddddd', $manifestId, $bundleSeq, $weight, $len, $wid, $hei, $vol);
                                if (!$insB->execute()) {
                                    $insB->close();
                                    throw new RuntimeException('bundle exec');
                                }
                                $bundleId = (int)$insB->insert_id;
                                $insB->close();
                                if ($bundleId <= 0) {
                                    throw new RuntimeException('bundle id');
                                }
                                $insW = $conn->prepare('INSERT INTO uda_manifest_bundle_waybills (batch_id, bundle_id, tracking_no) VALUES (?, ?, ?)');
                                if (!$insW) {
                                    throw new RuntimeException('wb');
                                }
                                foreach ($waybills as $tn) {
                                    $insW->bind_param('iis', $manifestId, $bundleId, $tn);
                                    if (!$insW->execute()) {
                                        throw new RuntimeException('dup wb');
                                    }
                                }
                                $insW->close();
                                $conn->commit();
                                header('Location: /uda/batches/create?msg=bundle_done');
                                exit;
                            } catch (Throwable) {
                                $errno = (int)$conn->errno;
                                $conn->rollback();
                                $error = $errno === 1062 ? '有面单号在系统中已存在（全库不可重复），请检查与其它日期号重复或重复扫描' : '集包保存失败，请重试';
                            }
                        }
                    }
                }
            } elseif ($action === 'complete_batch') {
                $manifestId = (int)($_POST['batch_id'] ?? 0);
                $sid = (int)($_SESSION[$sessionKey] ?? 0);
                if ($manifestId <= 0 || $sid !== $manifestId) {
                    $error = '会话已失效，请重新设定日期号';
                } else {
                    $cnt = 0;
                    $cst = $conn->prepare('SELECT COUNT(*) AS c FROM uda_manifest_bundles WHERE batch_id = ?');
                    if ($cst) {
                        $cst->bind_param('i', $manifestId);
                        $cst->execute();
                        $cnt = (int)($cst->get_result()->fetch_assoc()['c'] ?? 0);
                        $cst->close();
                    }
                    if ($cnt < 1) {
                        $error = '请至少完成一个集包后再结束日期号';
                    } else {
                        $up = $conn->prepare("UPDATE uda_manifest_batches SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'open'");
                        if ($up) {
                            $up->bind_param('i', $manifestId);
                            $up->execute();
                            $aff = $up->affected_rows;
                            $up->close();
                            if ($aff > 0) {
                                unset($_SESSION[$sessionKey]);
                                header('Location: /uda/batches/create?msg=batch_done');
                                exit;
                            }
                        }
                        $error = '日期号结束失败（可能已结束）';
                    }
                }
            } elseif ($action === 'abandon_batch') {
                $manifestId = (int)($_POST['batch_id'] ?? 0);
                $sid = (int)($_SESSION[$sessionKey] ?? 0);
                if ($manifestId <= 0 || $sid !== $manifestId) {
                    $error = '会话已失效，请重新设定日期号';
                } else {
                    $del = $conn->prepare("DELETE FROM uda_manifest_batches WHERE id = ? AND status = 'open'");
                    if ($del) {
                        $del->bind_param('i', $manifestId);
                        $del->execute();
                        $aff = $del->affected_rows;
                        $del->close();
                        if ($aff > 0) {
                            unset($_SESSION[$sessionKey]);
                            header('Location: /uda/batches/create?msg=abandoned');
                            exit;
                        }
                    }
                    $error = '放弃失败（仅进行中的日期号可删除）';
                }
            }
        }

        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'batch_ready') {
            $message = '已设定日期号，请扫描本集包面单并填写尺寸重量';
        }
        if ($msg === 'bundle_done') {
            $message = '本集包已提交，可继续下一集包';
        }
        if ($msg === 'batch_done') {
            $message = '日期号已全部完成';
        }
        if ($msg === 'abandoned') {
            $message = '已放弃本组集包，可重新设定日期号';
        }

        if ($schemaReady) {
            $bid = (int)($_SESSION[$sessionKey] ?? 0);
            if ($bid > 0) {
                $st = $conn->prepare('SELECT id, batch_code, date_no, bill_no, status FROM uda_manifest_batches WHERE id = ? LIMIT 1');
                if ($st) {
                    $st->bind_param('i', $bid);
                    $st->execute();
                    $currentBatch = $st->get_result()->fetch_assoc() ?: null;
                    $st->close();
                }
                if (!$currentBatch || ($currentBatch['status'] ?? '') !== 'open') {
                    unset($_SESSION[$sessionKey]);
                    $currentBatch = null;
                } else {
                    $totals = $this->manifestBatchTotals($conn, $bid);
                }
            }
        }

        $title = 'UDA快件 / 仓内操作 / 集包录入';
        $contentView = __DIR__ . '/../Views/uda/batches_create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    // Legacy name kept for route compatibility: "batch" == manifest/shipment ticket.
    public function batchList(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问集包列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_manifest_batches')
            && $this->columnExists($conn, 'uda_manifest_batches', 'date_no')
            && $this->columnExists($conn, 'uda_manifest_batches', 'bill_no')
            && $this->tableExists($conn, 'uda_manifest_bundles');
        $rows = [];
        $manifestRow = null;
        $detailBundles = [];
        $message = '';
        $error = '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $totalPages = 1;
        $viewManifestId = max(0, (int)($_GET['manifest_id'] ?? $_GET['batch_id'] ?? 0));
        $manifestWaybillLookup = null;

        if ($schemaReady) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'delete_batch') {
                $delId = (int)($_POST['manifest_id'] ?? $_POST['batch_id'] ?? 0);
                if ($delId > 0) {
                    $del = $conn->prepare('DELETE FROM uda_manifest_batches WHERE id = ?');
                    if ($del) {
                        $del->bind_param('i', $delId);
                        $del->execute();
                        $del->close();
                    }
                    header('Location: /uda/batches/list?msg=deleted');
                    exit;
                }
            }

            if ($viewManifestId > 0) {
                $st = $conn->prepare('
                    SELECT b.id, b.batch_code, b.date_no, b.bill_no, b.status, b.created_at, b.completed_at, u.full_name AS created_by_name,
                        (SELECT COALESCE(SUM(weight_kg),0) FROM uda_manifest_bundles x WHERE x.batch_id = b.id) AS total_weight,
                        (SELECT COALESCE(SUM(volume_m3),0) FROM uda_manifest_bundles x WHERE x.batch_id = b.id) AS total_volume,
                        (SELECT COUNT(*) FROM uda_manifest_bundles x WHERE x.batch_id = b.id) AS bundle_count
                    FROM uda_manifest_batches b
                    LEFT JOIN users u ON u.id = b.created_by
                    WHERE b.id = ?
                    LIMIT 1
                ');
                if ($st) {
                    $st->bind_param('i', $viewManifestId);
                    $st->execute();
                    $manifestRow = $st->get_result()->fetch_assoc() ?: null;
                    $st->close();
                }
                if ($manifestRow) {
                    $bq = $conn->prepare('SELECT id, bundle_seq, weight_kg, length_cm, width_cm, height_cm, volume_m3, created_at FROM uda_manifest_bundles WHERE batch_id = ? ORDER BY bundle_seq ASC');
                    if ($bq) {
                        $bq->bind_param('i', $viewManifestId);
                        $bq->execute();
                        $br = $bq->get_result();
                        while ($br && ($row = $br->fetch_assoc())) {
                            $detailBundles[] = $row;
                        }
                        $bq->close();
                    }
                    foreach ($detailBundles as $idx => $brow) {
                        $bid = (int)($brow['id'] ?? 0);
                        $wbs = [];
                        if ($bid > 0) {
                            $wq = $conn->prepare('SELECT tracking_no FROM uda_manifest_bundle_waybills WHERE bundle_id = ? ORDER BY id ASC');
                            if ($wq) {
                                $wq->bind_param('i', $bid);
                                $wq->execute();
                                $wr = $wq->get_result();
                                while ($wr && ($w = $wr->fetch_assoc())) {
                                    $wbs[] = (string)($w['tracking_no'] ?? '');
                                }
                                $wq->close();
                            }
                        }
                        $detailBundles[$idx]['waybills'] = $wbs;
                    }
                } elseif ($viewManifestId > 0) {
                    $error = '日期号不存在或已删除';
                    $viewManifestId = 0;
                }
            }

            $skipMainList = $viewManifestId > 0 && is_array($manifestRow);
            if (!$skipMainList) {
                $qDateNo = trim((string)($_GET['q_date_no'] ?? $_GET['q_manifest_code'] ?? $_GET['q_batch_code'] ?? ''));
                $qBillNo = trim((string)($_GET['q_bill_no'] ?? ''));
                $qFrom = trim((string)($_GET['q_from'] ?? ''));
                $qTo = trim((string)($_GET['q_to'] ?? ''));
                $qTrackRaw = trim((string)($_GET['q_tracking_no'] ?? ''));
                $qTrackingNoNorm = $qTrackRaw !== '' ? $this->normalizeTrackingNo($qTrackRaw) : '';

                if ($qTrackingNoNorm !== '') {
                    $lq = $conn->prepare('
                        SELECT b.id AS batch_id, b.batch_code, b.date_no, b.bill_no, b.status, x.bundle_seq, w.tracking_no
                        FROM uda_manifest_bundle_waybills w
                        INNER JOIN uda_manifest_batches b ON b.id = w.batch_id
                        INNER JOIN uda_manifest_bundles x ON x.id = w.bundle_id
                        WHERE w.tracking_no = ?
                        LIMIT 1
                    ');
                    if ($lq) {
                        $lq->bind_param('s', $qTrackingNoNorm);
                        $lq->execute();
                        $hit = $lq->get_result()->fetch_assoc();
                        $lq->close();
                        if ($hit) {
                            $seq = (int)($hit['bundle_seq'] ?? 0);
                            $manifestWaybillLookup = [
                                'tracking_no' => (string)($hit['tracking_no'] ?? $qTrackingNoNorm),
                                'manifest_id' => (int)($hit['batch_id'] ?? 0),
                                'date_no' => (string)($hit['date_no'] ?? ''),
                                'bill_no' => (string)($hit['bill_no'] ?? ''),
                                'manifest_code' => (string)($hit['date_no'] ?? $hit['batch_code'] ?? ''),
                                'batch_id' => (int)($hit['batch_id'] ?? 0), // backward compatible
                                'batch_code' => (string)($hit['batch_code'] ?? ''), // backward compatible
                                'batch_status' => (string)($hit['status'] ?? ''),
                                'bundle_seq' => $seq,
                                'bundle_label' => str_pad((string)max(1, $seq), 3, '0', STR_PAD_LEFT),
                            ];
                        }
                    }
                }

                $where = [];
                $types = '';
                $params = [];
                if ($qDateNo !== '') {
                    $where[] = 'COALESCE(NULLIF(b.date_no, \'\'), b.batch_code) LIKE ?';
                    $types .= 's';
                    $params[] = '%' . $qDateNo . '%';
                }
                if ($qBillNo !== '') {
                    $where[] = 'b.bill_no LIKE ?';
                    $types .= 's';
                    $params[] = '%' . $qBillNo . '%';
                }
                if ($qFrom !== '') {
                    $where[] = 'DATE(b.created_at) >= ?';
                    $types .= 's';
                    $params[] = $qFrom;
                }
                if ($qTo !== '') {
                    $where[] = 'DATE(b.created_at) <= ?';
                    $types .= 's';
                    $params[] = $qTo;
                }
                if ($qTrackingNoNorm !== '') {
                    $where[] = 'EXISTS (SELECT 1 FROM uda_manifest_bundle_waybills wq WHERE wq.batch_id = b.id AND wq.tracking_no = ?)';
                    $types .= 's';
                    $params[] = $qTrackingNoNorm;
                }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

                $countSql = "SELECT COUNT(*) AS c FROM uda_manifest_batches b {$whereSql}";
                $cst = $conn->prepare($countSql);
                if ($cst) {
                    if ($types !== '') {
                        $cst->bind_param($types, ...$params);
                    }
                    $cst->execute();
                    $total = (int)($cst->get_result()->fetch_assoc()['c'] ?? 0);
                    $cst->close();
                }
                $totalPages = max(1, (int)ceil($total / $perPage));
                if ($page > $totalPages) {
                    $page = $totalPages;
                    $offset = ($page - 1) * $perPage;
                }

                $sql = "
                    SELECT b.id, b.batch_code, b.date_no, b.bill_no, b.status, b.created_at, b.completed_at, u.full_name AS created_by_name,
                        (SELECT COUNT(*) FROM uda_manifest_bundles x WHERE x.batch_id = b.id) AS bundle_count,
                        (SELECT COALESCE(SUM(weight_kg),0) FROM uda_manifest_bundles x WHERE x.batch_id = b.id) AS total_weight,
                        (SELECT COALESCE(SUM(volume_m3),0) FROM uda_manifest_bundles x WHERE x.batch_id = b.id) AS total_volume
                    FROM uda_manifest_batches b
                    LEFT JOIN users u ON u.id = b.created_by
                    {$whereSql}
                    ORDER BY b.id DESC
                    LIMIT {$offset}, {$perPage}
                ";
                $lst = $conn->prepare($sql);
                if ($lst) {
                    if ($types !== '') {
                        $lst->bind_param($types, ...$params);
                    }
                    $lst->execute();
                    $res = $lst->get_result();
                    while ($res && ($row = $res->fetch_assoc())) {
                        $rows[] = $row;
                    }
                    $lst->close();
                }
            }
        }

        $listMsg = trim((string)($_GET['msg'] ?? ''));
        if ($listMsg === 'deleted') {
            $message = '已删除该日期号及下属全部集包、面单';
        }

        $title = 'UDA快件 / 仓内操作 / 集包列表';
        $contentView = __DIR__ . '/../Views/uda/batches_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    // Legacy name kept for route compatibility: "batch" == manifest/shipment ticket.
    public function batchEdit(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限修改集包');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_manifest_batches')
            && $this->columnExists($conn, 'uda_manifest_batches', 'date_no')
            && $this->columnExists($conn, 'uda_manifest_batches', 'bill_no')
            && $this->tableExists($conn, 'uda_manifest_bundles')
            && $this->tableExists($conn, 'uda_manifest_bundle_waybills');
        $message = '';
        $error = '';
        $manifestId = max(0, (int)($_POST['manifest_id'] ?? $_POST['batch_id'] ?? $_GET['manifest_id'] ?? $_GET['batch_id'] ?? 0));

        if (!$schemaReady || $manifestId <= 0) {
            $title = 'UDA快件 / 仓内操作 / 集包修改';
            $manifestRow = null;
            $editBundles = [];
            $contentView = __DIR__ . '/../Views/uda/batches_edit.php';
            if (!$schemaReady) {
                $error = '数据表未就绪';
            } else {
                $error = '参数无效';
            }
            require __DIR__ . '/../Views/layouts/main.php';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'update_bundle') {
                $bundleId = (int)($_POST['bundle_id'] ?? 0);
                $weight = (float)str_replace(',', '.', trim((string)($_POST['weight_kg'] ?? '')));
                $len = (float)str_replace(',', '.', trim((string)($_POST['length_cm'] ?? '')));
                $wid = (float)str_replace(',', '.', trim((string)($_POST['width_cm'] ?? '')));
                $hei = (float)str_replace(',', '.', trim((string)($_POST['height_cm'] ?? '')));
                if ($bundleId <= 0) {
                    $error = '集包无效';
                } elseif ($weight <= 0 || $len <= 0 || $wid <= 0 || $hei <= 0) {
                    $error = '重量与长宽高须均大于 0';
                } else {
                    $chk = $conn->prepare('SELECT id FROM uda_manifest_bundles WHERE id = ? AND batch_id = ? LIMIT 1');
                    $ok = false;
                    if ($chk) {
                        $chk->bind_param('ii', $bundleId, $manifestId);
                        $chk->execute();
                        $ok = (bool)$chk->get_result()->fetch_assoc();
                        $chk->close();
                    }
                    if (!$ok) {
                        $error = '集包不属于该日期号';
                    } else {
                        $vol = $this->manifestVolumeM3FromCm($len, $wid, $hei);
                        $up = $conn->prepare('UPDATE uda_manifest_bundles SET weight_kg = ?, length_cm = ?, width_cm = ?, height_cm = ?, volume_m3 = ? WHERE id = ? AND batch_id = ?');
                        if ($up) {
                            $up->bind_param('dddddii', $weight, $len, $wid, $hei, $vol, $bundleId, $manifestId);
                            $up->execute();
                            $up->close();
                        }
                        header('Location: /uda/batches/edit?manifest_id=' . $manifestId . '&msg=updated');
                        exit;
                    }
                }
            } elseif ($action === 'delete_waybill') {
                $waybillId = (int)($_POST['waybill_id'] ?? 0);
                if ($waybillId <= 0) {
                    $error = '参数无效';
                } else {
                    $del = $conn->prepare('DELETE FROM uda_manifest_bundle_waybills WHERE id = ? AND batch_id = ?');
                    if ($del) {
                        $del->bind_param('ii', $waybillId, $manifestId);
                        $del->execute();
                        $del->close();
                    }
                    header('Location: /uda/batches/edit?manifest_id=' . $manifestId . '&msg=waybill_removed');
                    exit;
                }
            } elseif ($action === 'add_waybill') {
                $bundleId = (int)($_POST['bundle_id'] ?? 0);
                $tn = $this->normalizeTrackingNo((string)($_POST['tracking_no'] ?? ''));
                if ($bundleId <= 0 || $tn === '') {
                    $error = '请选择集包并填写面单号';
                } else {
                    $chk = $conn->prepare('SELECT id FROM uda_manifest_bundles WHERE id = ? AND batch_id = ? LIMIT 1');
                    $ok = false;
                    if ($chk) {
                        $chk->bind_param('ii', $bundleId, $manifestId);
                        $chk->execute();
                        $ok = (bool)$chk->get_result()->fetch_assoc();
                        $chk->close();
                    }
                    if (!$ok) {
                        $error = '集包不属于该日期号';
                    } else {
                        $dup = $conn->prepare('SELECT id FROM uda_manifest_bundle_waybills WHERE batch_id = ? AND tracking_no = ? LIMIT 1');
                        $exists = false;
                        if ($dup) {
                            $dup->bind_param('is', $manifestId, $tn);
                            $dup->execute();
                            $exists = (bool)$dup->get_result()->fetch_assoc();
                            $dup->close();
                        }
                        if ($exists) {
                            $error = '该面单已存在于本日期号中';
                        } else {
                            $ins = $conn->prepare('INSERT INTO uda_manifest_bundle_waybills (batch_id, bundle_id, tracking_no) VALUES (?, ?, ?)');
                            if ($ins) {
                                $ins->bind_param('iis', $manifestId, $bundleId, $tn);
                                if ($ins->execute()) {
                                    $ins->close();
                                    header('Location: /uda/batches/edit?manifest_id=' . $manifestId . '&msg=waybill_added');
                                    exit;
                                }
                                $errno = (int)$ins->errno;
                                $ins->close();
                                $error = $errno === 1062 ? '该面单号在系统中已存在（全库不可重复）' : '添加面单失败';
                            } else {
                                $error = '添加面单失败';
                            }
                        }
                    }
                }
            }
        }

        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'updated') {
            $message = '集包尺寸重量已更新';
        }
        if ($msg === 'waybill_removed') {
            $message = '已删除面单';
        }
        if ($msg === 'waybill_added') {
            $message = '已添加面单';
        }

        $manifestRow = null;
        $editBundles = [];
        $st = $conn->prepare('SELECT id, batch_code, date_no, bill_no, status, created_at, completed_at FROM uda_manifest_batches WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $manifestId);
            $st->execute();
            $manifestRow = $st->get_result()->fetch_assoc() ?: null;
            $st->close();
        }
        if (!$manifestRow) {
            $error = $error !== '' ? $error : '日期号不存在';
        } else {
            $bq = $conn->prepare('SELECT id, bundle_seq, weight_kg, length_cm, width_cm, height_cm, volume_m3 FROM uda_manifest_bundles WHERE batch_id = ? ORDER BY bundle_seq ASC');
            if ($bq) {
                $bq->bind_param('i', $manifestId);
                $bq->execute();
                $br = $bq->get_result();
                while ($br && ($row = $br->fetch_assoc())) {
                    $editBundles[] = $row;
                }
                $bq->close();
            }
            foreach ($editBundles as $idx => $brow) {
                $bid = (int)($brow['id'] ?? 0);
                $lines = [];
                if ($bid > 0) {
                    $wq = $conn->prepare('SELECT id, tracking_no FROM uda_manifest_bundle_waybills WHERE bundle_id = ? ORDER BY id ASC');
                    if ($wq) {
                        $wq->bind_param('i', $bid);
                        $wq->execute();
                        $wr = $wq->get_result();
                        while ($wr && ($w = $wr->fetch_assoc())) {
                            $lines[] = $w;
                        }
                        $wq->close();
                    }
                }
                $editBundles[$idx]['waybill_rows'] = $lines;
            }
        }

        $title = 'UDA快件 / 仓内操作 / 集包修改';
        $contentView = __DIR__ . '/../Views/uda/batches_edit.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function warehouseBatchCreate(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问批次录入');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_warehouse_batches')
            && $this->tableExists($conn, 'uda_warehouse_batch_waybills');
        $message = '';
        $error = '';

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'create_batch') {
                $dateNo = trim((string)($_POST['date_no'] ?? ''));
                $billNo = trim((string)($_POST['bill_no'] ?? ''));
                $udaCountRaw = trim((string)($_POST['uda_count'] ?? ''));
                $jdCountRaw = trim((string)($_POST['jd_count'] ?? ''));
                $flightDateRaw = trim((string)($_POST['flight_date'] ?? ''));
                $pickupDateRaw = trim((string)($_POST['customs_pickup_date'] ?? ''));

                $udaCount = $udaCountRaw === '' ? null : max(0, (int)$udaCountRaw);
                $jdCount = $jdCountRaw === '' ? null : max(0, (int)$jdCountRaw);

                $flightDate = $flightDateRaw === '' ? null : $flightDateRaw;
                $pickupDate = $pickupDateRaw === '' ? null : $pickupDateRaw;

                $totalCount = ($udaCount === null && $jdCount === null)
                    ? null
                    : (($udaCount ?? 0) + ($jdCount ?? 0));
                $createdBy = (int)($_SESSION['auth_user_id'] ?? 0);

                if ($dateNo === '') {
                    $error = '请填写日期号';
                } elseif ($billNo === '') {
                    $error = '请填写提单号';
                } else {
                    $ins = $conn->prepare('
                        INSERT INTO uda_warehouse_batches
                        (date_no, bill_no, uda_count, jd_count, total_count, flight_date, customs_pickup_date, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    if ($ins) {
                        // 使用 string 绑定 nullable INT：当变量为 null 时会写入 NULL
                        $ins->bind_param('sssssssi', $dateNo, $billNo, $udaCount, $jdCount, $totalCount, $flightDate, $pickupDate, $createdBy);
                        if ($ins->execute()) {
                            $ins->close();
                            // 若日期号已存在于“集包模块”，同步提单号到集包主表
                            $this->syncManifestBillNoByDateNo($conn, $dateNo, $billNo);
                            header('Location: /uda/warehouse/create-bundle?msg=created&date_no=' . urlencode($dateNo));
                            exit;
                        }
                        $errno = (int)$ins->errno;
                        $ins->close();
                        $error = $errno === 1062 ? '该日期号已存在' : '保存失败';
                    } else {
                        $error = '保存失败';
                    }
                }
            } elseif ($action === 'import_waybills') {
                $dateNo = trim((string)($_POST['date_no'] ?? ''));
                $file = $_FILES['csv_file'] ?? null;
                if ($dateNo === '') {
                    $error = '请填写日期号后再导入';
                } elseif (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $error = '请上传 CSV 文件';
                } else {
                    $batch = null;
                    $st = $conn->prepare('SELECT id FROM uda_warehouse_batches WHERE date_no = ? LIMIT 1');
                    if ($st) {
                        $st->bind_param('s', $dateNo);
                        $st->execute();
                        $batch = $st->get_result()->fetch_assoc() ?: null;
                        $st->close();
                    }
                    if (!$batch) {
                        $error = '日期号不存在，请先录入批次主数据';
                    } else {
                        $batchId = (int)$batch['id'];
                        $tmp = (string)($file['tmp_name'] ?? '');
                        $raw = @file_get_contents($tmp);
                        if ($raw === false || $raw === '') {
                            $error = 'CSV 读取失败';
                        } else {
                            // 兼容 Excel 导出的不同编码（UTF-8/UTF-16LE/UTF-16BE/GBK 等）
                            $rawBin = (string)$raw;
                            if (strncmp($rawBin, "\xFF\xFE", 2) === 0) {
                                $rawBin = mb_convert_encoding(substr($rawBin, 2), 'UTF-8', 'UTF-16LE');
                            } elseif (strncmp($rawBin, "\xFE\xFF", 2) === 0) {
                                $rawBin = mb_convert_encoding(substr($rawBin, 2), 'UTF-8', 'UTF-16BE');
                            } elseif (strncmp($rawBin, "\xEF\xBB\xBF", 3) === 0) {
                                $rawBin = substr($rawBin, 3);
                            } else {
                                $enc = mb_detect_encoding($rawBin, ['UTF-8', 'GB18030', 'GBK', 'BIG5'], true);
                                if ($enc && strtoupper($enc) !== 'UTF-8') {
                                    $rawBin = mb_convert_encoding($rawBin, 'UTF-8', $enc);
                                }
                            }

                            $lines = preg_split('/\r\n|\n|\r/', (string)$rawBin) ?: [];
                            $lines = array_values(array_filter($lines, static function ($line) {
                                return trim((string)$line) !== '';
                            }));
                            if ($lines === []) {
                                $error = 'CSV 内容为空';
                                $header = null;
                            } else {
                                $firstLine = (string)$lines[0];
                                $delimiters = [',', ';', "\t", '|'];
                                $delimiter = ',';
                                $maxCols = -1;
                                foreach ($delimiters as $d) {
                                    $cols = str_getcsv($firstLine, $d);
                                    $count = is_array($cols) ? count($cols) : 0;
                                    if ($count > $maxCols) {
                                        $maxCols = $count;
                                        $delimiter = $d;
                                    }
                                }
                                $header = str_getcsv($firstLine, $delimiter);
                            }

                            $trackingIdx = -1;
                            $dateIdx = -1;
                            if (is_array($header)) {
                                foreach ($header as $i => $h) {
                                    $name = (string)$h;
                                    // 兼容 Excel CSV 的 UTF-8 BOM、全角空格、下划线/大小写差异
                                    $name = preg_replace('/^\xEF\xBB\xBF/u', '', $name) ?? $name;
                                    $name = str_replace("\xC2\xA0", ' ', $name);
                                    $name = str_replace('　', ' ', $name);
                                    $name = trim($name);
                                    $nameLower = strtolower($name);
                                    $nameCanon = str_replace([' ', '_', '-'], '', $nameLower);
                                    if (in_array($name, ['面单号', '运单号'], true) || in_array($nameCanon, ['trackingno', 'waybillno'], true)) {
                                        $trackingIdx = (int)$i;
                                    }
                                    if ($name === '日期号' || in_array($nameCanon, ['dateno', 'date'], true)) {
                                        $dateIdx = (int)$i;
                                    }
                                }
                            }
                            if ($trackingIdx < 0 || $dateIdx < 0) {
                                $error = 'CSV 表头需包含：面单号、日期号（支持逗号/分号/Tab分隔）';
                            } else {
                                $inserted = 0;
                                $skipped = 0;
                                $ins = $conn->prepare('INSERT INTO uda_warehouse_batch_waybills (batch_id, date_no, tracking_no) VALUES (?, ?, ?)');
                                if (!$ins) {
                                    $error = '导入失败';
                                } else {
                                    for ($lineNo = 1; $lineNo < count($lines); $lineNo++) {
                                        $row = str_getcsv((string)$lines[$lineNo], $delimiter);
                                        $trackRaw = (string)($row[$trackingIdx] ?? '');
                                        $rowDateNo = trim((string)($row[$dateIdx] ?? ''));
                                        $trackingNo = $this->normalizeTrackingNo($trackRaw);
                                        if ($trackingNo === '' || $rowDateNo === '' || $rowDateNo !== $dateNo) {
                                            $skipped++;
                                            continue;
                                        }
                                        $ins->bind_param('iss', $batchId, $dateNo, $trackingNo);
                                        if ($ins->execute()) {
                                            $inserted++;
                                        } else {
                                            $skipped++;
                                        }
                                    }
                                    $ins->close();
                                    $message = "CSV 导入完成：新增 {$inserted} 条，跳过 {$skipped} 条";
                                }
                            }
                        }
                    }
                }
            }
        }

        $prefillDateNo = trim((string)($_GET['date_no'] ?? $_POST['date_no'] ?? ''));
        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'created') {
            $message = $message !== '' ? $message : '批次主数据已保存，可继续导入面单 CSV';
        }

        $title = 'UDA快件 / 批次操作 / 批次录入';
        $contentView = __DIR__ . '/../Views/uda/warehouse_batches_create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function warehouseBatchList(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问批次列表');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_warehouse_batches')
            && $this->tableExists($conn, 'uda_warehouse_batch_waybills');
        $rows = [];
        $message = '';
        $error = '';

        if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $del = $conn->prepare('DELETE FROM uda_warehouse_batches WHERE id = ?');
                if ($del) {
                    $del->bind_param('i', $id);
                    $del->execute();
                    $del->close();
                    header('Location: /uda/warehouse/bundles?msg=deleted');
                    exit;
                }
            }
            $error = '删除失败';
        }

        if ($schemaReady) {
            $qDateNo = trim((string)($_GET['q_date_no'] ?? ''));
            $qBillNo = trim((string)($_GET['q_bill_no'] ?? ''));
            $qTrackingNo = $this->normalizeTrackingNo((string)($_GET['q_tracking_no'] ?? ''));
            $where = [];
            $types = '';
            $params = [];
            if ($qDateNo !== '') {
                $where[] = 'b.date_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qDateNo . '%';
            }
            if ($qBillNo !== '') {
                $where[] = 'b.bill_no LIKE ?';
                $types .= 's';
                $params[] = '%' . $qBillNo . '%';
            }
            if ($qTrackingNo !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM uda_warehouse_batch_waybills wb WHERE wb.batch_id = b.id AND wb.tracking_no LIKE ?)';
                $types .= 's';
                $params[] = '%' . $qTrackingNo . '%';
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $sql = "
                SELECT b.id, b.date_no, b.bill_no, b.uda_count, b.jd_count, b.total_count, b.flight_date, b.customs_pickup_date, b.created_at
                FROM uda_warehouse_batches b
                {$whereSql}
                ORDER BY b.id DESC
            ";
            $st = $conn->prepare($sql);
            if ($st) {
                if ($types !== '') $st->bind_param($types, ...$params);
                $st->execute();
                $res = $st->get_result();
                while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
                $st->close();
            }
        }
        $warehouseRows = $rows;

        if (trim((string)($_GET['msg'] ?? '')) === 'deleted') $message = '已删除该批次';

        $title = 'UDA快件 / 批次操作 / 批次列表';
        $contentView = __DIR__ . '/../Views/uda/warehouse_batches_list.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function warehouseBatchView(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问批次详情');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_warehouse_batches')
            && $this->tableExists($conn, 'uda_warehouse_batch_waybills');
        $row = null;
        $waybills = [];
        $error = '';
        $id = (int)($_GET['id'] ?? 0);
        if (!$schemaReady || $id <= 0) {
            $error = '参数无效或数据表未就绪';
        } else {
            $st = $conn->prepare('SELECT * FROM uda_warehouse_batches WHERE id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('i', $id);
                $st->execute();
                $row = $st->get_result()->fetch_assoc() ?: null;
                $st->close();
            }
            if ($row) {
                $wq = $conn->prepare('SELECT tracking_no FROM uda_warehouse_batch_waybills WHERE batch_id = ? ORDER BY id ASC');
                if ($wq) {
                    $wq->bind_param('i', $id);
                    $wq->execute();
                    $res = $wq->get_result();
                    while ($res && ($r = $res->fetch_assoc())) $waybills[] = (string)$r['tracking_no'];
                    $wq->close();
                }
            } else {
                $error = '批次不存在';
            }
        }
        $warehouseRow = $row;
        $warehouseWaybills = $waybills;
        $title = 'UDA快件 / 批次操作 / 批次详情';
        $contentView = __DIR__ . '/../Views/uda/warehouse_batches_view.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function warehouseBatchEdit(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问批次修改');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $schemaReady = $this->tableExists($conn, 'uda_warehouse_batches')
            && $this->tableExists($conn, 'uda_warehouse_batch_waybills');
        $message = '';
        $error = '';
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $row = null;
        $waybills = [];

        if ($schemaReady && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'save_meta') {
                $billNo = trim((string)($_POST['bill_no'] ?? ''));
                $udaCountRaw = trim((string)($_POST['uda_count'] ?? ''));
                $jdCountRaw = trim((string)($_POST['jd_count'] ?? ''));
                $flightDateRaw = trim((string)($_POST['flight_date'] ?? ''));
                $pickupDateRaw = trim((string)($_POST['customs_pickup_date'] ?? ''));

                $udaCount = $udaCountRaw === '' ? null : max(0, (int)$udaCountRaw);
                $jdCount = $jdCountRaw === '' ? null : max(0, (int)$jdCountRaw);

                $flightDate = $flightDateRaw === '' ? null : $flightDateRaw;
                $pickupDate = $pickupDateRaw === '' ? null : $pickupDateRaw;

                $total = ($udaCount === null && $jdCount === null)
                    ? null
                    : (($udaCount ?? 0) + ($jdCount ?? 0));
                $dateNoForSync = '';
                $dateNoSt = $conn->prepare('SELECT date_no FROM uda_warehouse_batches WHERE id = ? LIMIT 1');
                if ($dateNoSt) {
                    $dateNoSt->bind_param('i', $id);
                    $dateNoSt->execute();
                    $dateNoForSync = (string)($dateNoSt->get_result()->fetch_assoc()['date_no'] ?? '');
                    $dateNoSt->close();
                }
                $up = $conn->prepare('UPDATE uda_warehouse_batches SET bill_no=?, uda_count=?, jd_count=?, total_count=?, flight_date=?, customs_pickup_date=? WHERE id=?');
                if ($up) {
                    // 使用 string 绑定 nullable INT：当变量为 null 时会写入 NULL
                    $up->bind_param('ssssssi', $billNo, $udaCount, $jdCount, $total, $flightDate, $pickupDate, $id);
                    $up->execute();
                    $up->close();
                    // 批次修改提单号后，同步集包主表提单号
                    $this->syncManifestBillNoByDateNo($conn, $dateNoForSync, $billNo);
                    header('Location: /uda/warehouse/batch-edit?id=' . $id . '&msg=saved');
                    exit;
                }
                $error = '保存失败';
            } elseif ($action === 'add_or_remove_waybill') {
                $trackingNo = $this->normalizeTrackingNo((string)($_POST['tracking_no'] ?? ''));
                $mode = trim((string)($_POST['mode'] ?? ''));
                if ($trackingNo === '') {
                    $error = '请输入面单号';
                } elseif ($mode === 'add') {
                    $st = $conn->prepare('SELECT date_no FROM uda_warehouse_batches WHERE id = ? LIMIT 1');
                    $dateNo = '';
                    if ($st) {
                        $st->bind_param('i', $id);
                        $st->execute();
                        $dateNo = (string)($st->get_result()->fetch_assoc()['date_no'] ?? '');
                        $st->close();
                    }
                    $ins = $conn->prepare('INSERT INTO uda_warehouse_batch_waybills (batch_id, date_no, tracking_no) VALUES (?, ?, ?)');
                    if ($ins) {
                        $ins->bind_param('iss', $id, $dateNo, $trackingNo);
                        if ($ins->execute()) {
                            $ins->close();
                            header('Location: /uda/warehouse/batch-edit?id=' . $id . '&msg=waybill_added');
                            exit;
                        }
                        $ins->close();
                    }
                    $error = '添加失败（可能重复）';
                } elseif ($mode === 'remove') {
                    $del = $conn->prepare('DELETE FROM uda_warehouse_batch_waybills WHERE batch_id = ? AND tracking_no = ?');
                    if ($del) {
                        $del->bind_param('is', $id, $trackingNo);
                        $del->execute();
                        $del->close();
                        header('Location: /uda/warehouse/batch-edit?id=' . $id . '&msg=waybill_removed');
                        exit;
                    }
                    $error = '删除失败';
                }
            }
        }

        if (!$schemaReady || $id <= 0) {
            $error = $error !== '' ? $error : '参数无效或数据表未就绪';
        } else {
            $st = $conn->prepare('SELECT * FROM uda_warehouse_batches WHERE id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('i', $id);
                $st->execute();
                $row = $st->get_result()->fetch_assoc() ?: null;
                $st->close();
            }
            if ($row) {
                $wq = $conn->prepare('SELECT tracking_no FROM uda_warehouse_batch_waybills WHERE batch_id = ? ORDER BY id ASC');
                if ($wq) {
                    $wq->bind_param('i', $id);
                    $wq->execute();
                    $res = $wq->get_result();
                    while ($res && ($r = $res->fetch_assoc())) $waybills[] = (string)$r['tracking_no'];
                    $wq->close();
                }
            } else {
                $error = '批次不存在';
            }
        }

        $msg = trim((string)($_GET['msg'] ?? ''));
        if ($msg === 'saved') $message = '批次主数据已保存';
        if ($msg === 'waybill_added') $message = '面单已添加';
        if ($msg === 'waybill_removed') $message = '面单已删除';

        $warehouseRow = $row;
        $warehouseWaybills = $waybills;
        $title = 'UDA快件 / 批次操作 / 批次修改';
        $contentView = __DIR__ . '/../Views/uda/warehouse_batches_edit.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function warehouseBatchExport(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限下载批次文档');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'bad request';
            return;
        }
        $st = $conn->prepare('SELECT * FROM uda_warehouse_batches WHERE id = ? LIMIT 1');
        $row = null;
        if ($st) {
            $st->bind_param('i', $id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc() ?: null;
            $st->close();
        }
        if (!$row) {
            http_response_code(404);
            echo 'not found';
            return;
        }
        $waybills = [];
        $wq = $conn->prepare('SELECT tracking_no FROM uda_warehouse_batch_waybills WHERE batch_id = ? ORDER BY id ASC');
        if ($wq) {
            $wq->bind_param('i', $id);
            $wq->execute();
            $res = $wq->get_result();
            while ($res && ($r = $res->fetch_assoc())) $waybills[] = (string)$r['tracking_no'];
            $wq->close();
        }

        $filename = 'warehouse_batch_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($row['date_no'] ?? '')) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        if (!$out) return;
        // Excel 兼容：写入 UTF-8 BOM，避免中文乱码
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['日期号', '提单号', 'UDA件数', 'JD件数', '总件数', '航班日期', '清关完成提货日期']);
        fputcsv($out, [
            (string)($row['date_no'] ?? ''),
            (string)($row['bill_no'] ?? ''),
            $row['uda_count'] === null ? '' : (string)$row['uda_count'],
            $row['jd_count'] === null ? '' : (string)$row['jd_count'],
            $row['total_count'] === null ? '' : (string)$row['total_count'],
            (string)($row['flight_date'] ?? ''),
            (string)($row['customs_pickup_date'] ?? ''),
        ]);
        fputcsv($out, []);
        fputcsv($out, ['面单号']);
        foreach ($waybills as $wb) {
            fputcsv($out, [$wb]);
        }
        fclose($out);
    }

    public function warehouseImportTemplate(): void
    {
        if (!$this->hasAnyPermission(['menu.dispatch', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限下载模板');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="warehouse_waybill_import_template.csv"');
        $out = fopen('php://output', 'w');
        if (!$out) {
            return;
        }
        // Excel 兼容：写入 UTF-8 BOM，确保中文表头不乱码
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['面单号', '日期号']);
        fputcsv($out, ['示例面单001', '260429']);
        fclose($out);
    }
}
