<?php
require_once __DIR__ . '/Concerns/AuditLogTrait.php';

class CalendarController
{
    use AuditLogTrait;
    private const EVENT_TYPES = ['reminder', 'todo', 'meeting'];
    private array $tableExistsCache = [];
    private array $columnExistsCache = [];

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

    private function typeLabel(string $type): string
    {
        if ($type === 'todo') {
            return '待办';
        }
        if ($type === 'meeting') {
            return '会议';
        }
        return '提醒';
    }

    private function isSuperAdmin(): bool
    {
        return (string)($_SESSION['auth_role_name'] ?? '') === 'super_admin';
    }

    private function canAccessEvent(mysqli $conn, int $eventId, int $userId): bool
    {
        if ($eventId <= 0 || $userId <= 0) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        $hasAssigneeTable = $this->tableExists($conn, 'calendar_event_assignees');
        if ($hasAssigneeTable) {
            $stmt = $conn->prepare('
                SELECT ce.id
                FROM calendar_events ce
                LEFT JOIN calendar_event_assignees cea ON cea.event_id = ce.id
                WHERE ce.id = ?
                  AND (ce.created_by = ? OR cea.user_id = ?)
                LIMIT 1
            ');
            if ($stmt) {
                $stmt->bind_param('iii', $eventId, $userId, $userId);
                $stmt->execute();
                $found = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return (bool)$found;
            }
            return false;
        }
        $stmt = $conn->prepare('SELECT id FROM calendar_events WHERE id = ? AND created_by = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $eventId, $userId);
            $stmt->execute();
            $found = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (bool)$found;
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
        $exists = $res && $res->num_rows > 0;
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
        $exists = $res && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $this->columnExistsCache[$key] = $exists;
        return $exists;
    }

    private function fetchEventAssigneeIds(mysqli $conn, int $eventId): array
    {
        if ($eventId <= 0 || !$this->tableExists($conn, 'calendar_event_assignees')) {
            return [];
        }
        $ids = [];
        $stmt = $conn->prepare('SELECT user_id FROM calendar_event_assignees WHERE event_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $uid = (int)($row['user_id'] ?? 0);
                if ($uid > 0) {
                    $ids[] = $uid;
                }
            }
            $stmt->close();
        }
        return array_values(array_unique($ids));
    }

    private function sendCalendarNotifications(
        mysqli $conn,
        string $eventKey,
        int $eventId,
        int $createdBy,
        array $assigneeIds,
        int $actorUserId,
        string $title,
        string $content
    ): void {
        if (!$this->tableExists($conn, 'notification_rules') || !$this->tableExists($conn, 'notifications_inbox')) {
            return;
        }
        $stmt = $conn->prepare('
            SELECT event_key, enabled, recipients_mode, custom_user_ids
            FROM notification_rules
            WHERE event_key = ?
            LIMIT 1
        ');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $eventKey);
        $stmt->execute();
        $rule = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$rule || (int)($rule['enabled'] ?? 0) !== 1) {
            return;
        }

        $recipientIds = [];
        $mode = (string)($rule['recipients_mode'] ?? 'creator_and_assignees');
        if ($mode === 'creator') {
            if ($createdBy > 0) {
                $recipientIds[] = $createdBy;
            }
        } elseif ($mode === 'assignees') {
            $recipientIds = $assigneeIds;
        } elseif ($mode === 'custom_users') {
            $csv = trim((string)($rule['custom_user_ids'] ?? ''));
            if ($csv !== '') {
                foreach (explode(',', $csv) as $part) {
                    $uid = (int)trim($part);
                    if ($uid > 0) {
                        $recipientIds[] = $uid;
                    }
                }
            }
        } elseif ($mode === 'all_active_users') {
            $activeRes = $conn->query('SELECT id FROM users WHERE status = 1');
            while ($activeRes && ($activeRow = $activeRes->fetch_assoc())) {
                $uid = (int)($activeRow['id'] ?? 0);
                if ($uid > 0) {
                    $recipientIds[] = $uid;
                }
            }
        } else {
            if ($createdBy > 0) {
                $recipientIds[] = $createdBy;
            }
            foreach ($assigneeIds as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) {
                    $recipientIds[] = $aid;
                }
            }
        }

        $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), static fn ($v) => $v > 0)));
        if (empty($recipientIds)) {
            return;
        }
        $insertStmt = $conn->prepare('
            INSERT INTO notifications_inbox (user_id, title, content, biz_type, biz_id, created_by, is_read)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ');
        if (!$insertStmt) {
            return;
        }
        $bizType = 'calendar_event';
        foreach ($recipientIds as $rid) {
            $insertStmt->bind_param('isssii', $rid, $title, $content, $bizType, $eventId, $actorUserId);
            $insertStmt->execute();
        }
        $insertStmt->close();
    }

    public function create(): void
    {
        if (!$this->hasAnyPermission(['menu.calendar', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问行事历管理');
        }

        $canCreate = $this->hasAnyPermission(['calendar.events.create', 'dashboard.calendar.manage']);
        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';
        $formData = [
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'title' => '',
            'event_type' => 'reminder',
            'note' => '',
            'assignee_ids' => [],
        ];
        $assignableUsers = [];
        $hasEventTypeColumn = $this->columnExists($conn, 'calendar_events', 'event_type');
        $hasStartDateColumn = $this->columnExists($conn, 'calendar_events', 'start_date');
        $hasEndDateColumn = $this->columnExists($conn, 'calendar_events', 'end_date');
        $hasAssigneeTable = $this->tableExists($conn, 'calendar_event_assignees');

        $userStmt = $conn->prepare('SELECT id, username, full_name FROM users WHERE status = 1 ORDER BY username ASC');
        if ($userStmt) {
            $userStmt->execute();
            $userRes = $userStmt->get_result();
            while ($userRes && ($row = $userRes->fetch_assoc())) {
                $assignableUsers[] = $row;
            }
            $userStmt->close();
        }

        if (!$canCreate) {
            $error = '你没有新增行事历事件权限。';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_calendar_event'])) {
            if (!$canCreate) {
                $this->denyNoPermission('无权限新增行事历事件');
            }
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $endDate = trim((string)($_POST['end_date'] ?? ''));
            $titleText = trim((string)($_POST['title'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            $eventType = trim((string)($_POST['event_type'] ?? 'reminder'));
            $rawAssigneeIds = $_POST['assignee_ids'] ?? [];
            $assigneeIds = [];
            if (is_array($rawAssigneeIds)) {
                foreach ($rawAssigneeIds as $rawAssigneeId) {
                    $parsedId = (int)$rawAssigneeId;
                    if ($parsedId > 0) {
                        $assigneeIds[] = $parsedId;
                    }
                }
            }
            $assigneeIds = array_values(array_unique($assigneeIds));
            $formData = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'title' => $titleText,
                'event_type' => $eventType,
                'note' => $note,
                'assignee_ids' => $assigneeIds,
            ];

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $titleText === '') {
                $error = '请填写正确的开始日期、结束日期与事件标题';
            } elseif ($startDate > $endDate) {
                $error = '结束日期不能早于开始日期';
            } elseif (!in_array($eventType, self::EVENT_TYPES, true)) {
                $error = '事项类型无效';
            } elseif (($eventType === 'todo' || $eventType === 'meeting') && count($assigneeIds) === 0) {
                $error = '待办和会议至少需要指派一位成员';
            } elseif (!$hasAssigneeTable && !empty($assigneeIds)) {
                $error = '当前数据库尚未启用多人指派，请先执行 migration：006_alter_calendar_events_for_type_and_assignees.sql';
            } else {
                $userId = (int)($_SESSION['auth_user_id'] ?? 0);
                $columns = ['event_date'];
                $types = 's';
                $values = [$startDate];
                if ($hasStartDateColumn) {
                    $columns[] = 'start_date';
                    $types .= 's';
                    $values[] = $startDate;
                }
                if ($hasEndDateColumn) {
                    $columns[] = 'end_date';
                    $types .= 's';
                    $values[] = $endDate;
                }
                $columns[] = 'title';
                $types .= 's';
                $values[] = $titleText;
                $columns[] = 'note';
                $types .= 's';
                $values[] = $note;
                if ($hasEventTypeColumn) {
                    $columns[] = 'event_type';
                    $types .= 's';
                    $values[] = $eventType;
                }
                $columns[] = 'created_by';
                $types .= 'i';
                $values[] = $userId;

                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $insertSql = 'INSERT INTO calendar_events (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
                $insertEventStmt = $conn->prepare($insertSql);
                if ($insertEventStmt) {
                    $conn->begin_transaction();
                    try {
                        $bindParams = [];
                        $bindParams[] = &$types;
                        foreach ($values as $idx => $val) {
                            $bindParams[] = &$values[$idx];
                        }
                        call_user_func_array([$insertEventStmt, 'bind_param'], $bindParams);
                        $insertEventStmt->execute();
                        $eventId = (int)$conn->insert_id;
                        $insertEventStmt->close();

                        if (!empty($assigneeIds) && $hasAssigneeTable) {
                            $insertAssigneeStmt = $conn->prepare('INSERT INTO calendar_event_assignees (event_id, user_id, assigned_by) VALUES (?, ?, ?)');
                            if ($insertAssigneeStmt) {
                                foreach ($assigneeIds as $assigneeId) {
                                    $insertAssigneeStmt->bind_param('iii', $eventId, $assigneeId, $userId);
                                    $insertAssigneeStmt->execute();
                                }
                                $insertAssigneeStmt->close();
                            }
                        }

                        $notifyTitle = '行事历新增事件：' . $titleText;
                        $notifyContent = sprintf(
                            '类型：%s；日期：%s ~ %s',
                            $this->typeLabel($eventType),
                            $startDate,
                            $endDate
                        );
                        $this->sendCalendarNotifications(
                            $conn,
                            'calendar.event_created',
                            $eventId,
                            $userId,
                            $assigneeIds,
                            $userId,
                            $notifyTitle,
                            $notifyContent
                        );

                        $conn->commit();
                        $this->writeAuditLog($conn, 'calendar', 'calendar.events.create', 'event', $eventId, [
                            'title' => $titleText,
                            'event_type' => $eventType,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'assignee_ids' => $assigneeIds,
                        ]);
                        header('Location: /calendar/create?msg=created');
                        exit;
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $error = '保存失败，请稍后重试';
                    }
                } else {
                    $error = '保存失败，请稍后重试';
                }
            }
        }

        if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
            $message = '事件新增成功';
        }

        $title = '行事历管理 / 新增事件';
        $contentView = __DIR__ . '/../Views/calendar/create.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function events(): void
    {
        if (!$this->hasAnyPermission(['menu.calendar', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限访问行事历管理');
        }
        if (!$this->hasAnyPermission(['calendar.events.view', 'dashboard.calendar.manage'])) {
            $this->denyNoPermission('无权限查看事件列表');
        }

        $conn = require __DIR__ . '/../../config/database.php';
        $monthInput = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $monthInput = date('Y-m');
        }
        $start = $monthInput . '-01';
        $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
        $ownerFilter = trim((string)($_GET['owner_filter'] ?? 'all'));
        if (!in_array($ownerFilter, ['all', 'created_by_me', 'assigned_to_me'], true)) {
            $ownerFilter = 'all';
        }
        $completionFilter = trim((string)($_GET['completion_filter'] ?? 'all'));
        if (!in_array($completionFilter, ['all', 'unfinished'], true)) {
            $completionFilter = 'all';
        }
        $hasEventTypeColumn = $this->columnExists($conn, 'calendar_events', 'event_type');
        $hasStartDateColumn = $this->columnExists($conn, 'calendar_events', 'start_date');
        $hasEndDateColumn = $this->columnExists($conn, 'calendar_events', 'end_date');
        $hasAssigneeTable = $this->tableExists($conn, 'calendar_event_assignees');

        $events = [];
        $perPage = (int)($_GET['per_page'] ?? 20);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }
        $page = (int)($_GET['page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;
        $currentUserId = (int)($_SESSION['auth_user_id'] ?? 0);
        $isSuperAdmin = $this->isSuperAdmin();
        $startExpr = $hasStartDateColumn ? 'COALESCE(ce.start_date, ce.event_date)' : 'ce.event_date';
        $endExpr = $hasEndDateColumn ? 'COALESCE(ce.end_date, ce.event_date)' : 'ce.event_date';
        $eventTypeExpr = $hasEventTypeColumn ? 'ce.event_type' : "'reminder'";
        $progressExpr = $this->columnExists($conn, 'calendar_events', 'progress_percent') ? 'ce.progress_percent' : '0';
        $completedExpr = $this->columnExists($conn, 'calendar_events', 'is_completed') ? 'ce.is_completed' : '0';
        $creatorExpr = "COALESCE(NULLIF(u.full_name, ''), u.username)";
        $assigneeExpr = $hasAssigneeTable
            ? "GROUP_CONCAT(DISTINCT COALESCE(NULLIF(au.full_name, ''), au.username) ORDER BY COALESCE(NULLIF(au.full_name, ''), au.username) SEPARATOR ', ')"
            : "''";
        $assigneeJoin = $hasAssigneeTable
            ? 'LEFT JOIN calendar_event_assignees cea ON cea.event_id = ce.id LEFT JOIN users au ON au.id = cea.user_id'
            : '';
        $visibilityClause = '';
        if (!$isSuperAdmin) {
            $visibilityClause = $hasAssigneeTable
                ? 'AND (ce.created_by = ? OR EXISTS (SELECT 1 FROM calendar_event_assignees cea2 WHERE cea2.event_id = ce.id AND cea2.user_id = ?))'
                : 'AND ce.created_by = ?';
        }
        $extraFilterSql = '';
        if ($ownerFilter === 'created_by_me') {
            $extraFilterSql .= ' AND ce.created_by = ?';
        } elseif ($ownerFilter === 'assigned_to_me') {
            if ($hasAssigneeTable) {
                $extraFilterSql .= ' AND EXISTS (SELECT 1 FROM calendar_event_assignees cea3 WHERE cea3.event_id = ce.id AND cea3.user_id = ?)';
            } else {
                $extraFilterSql .= ' AND 1 = 0';
            }
        }
        if ($completionFilter === 'unfinished') {
            if ($this->columnExists($conn, 'calendar_events', 'is_completed')) {
                $extraFilterSql .= ' AND COALESCE(ce.is_completed, 0) = 0';
            }
        }
        $total = 0;
        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT ce.id) AS c
            FROM calendar_events ce
            {$assigneeJoin}
            WHERE {$startExpr} <= ?
              AND {$endExpr} >= ?
              {$visibilityClause}
              {$extraFilterSql}
        ");
        if ($countStmt) {
            $countBindTypes = 'ss';
            $countBindValues = [$end, $start];
            if (!$isSuperAdmin) {
                if ($hasAssigneeTable) {
                    $countBindTypes .= 'ii';
                    $countBindValues[] = $currentUserId;
                    $countBindValues[] = $currentUserId;
                } else {
                    $countBindTypes .= 'i';
                    $countBindValues[] = $currentUserId;
                }
            }
            if ($ownerFilter === 'created_by_me') {
                $countBindTypes .= 'i';
                $countBindValues[] = $currentUserId;
            } elseif ($ownerFilter === 'assigned_to_me' && $hasAssigneeTable) {
                $countBindTypes .= 'i';
                $countBindValues[] = $currentUserId;
            }
            $countBindParams = [];
            $countBindParams[] = &$countBindTypes;
            foreach ($countBindValues as $idx => $val) {
                $countBindParams[] = &$countBindValues[$idx];
            }
            call_user_func_array([$countStmt, 'bind_param'], $countBindParams);
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
        $stmt = $conn->prepare("
            SELECT
                ce.id,
                ce.created_by AS created_by_id,
                {$startExpr} AS start_date,
                {$endExpr} AS end_date,
                ce.title,
                ce.note,
                {$eventTypeExpr} AS event_type,
                {$progressExpr} AS progress_percent,
                {$completedExpr} AS is_completed,
                {$creatorExpr} AS creator,
                {$assigneeExpr} AS assignees
            FROM calendar_events ce
            LEFT JOIN users u ON u.id = ce.created_by
            {$assigneeJoin}
            WHERE {$startExpr} <= ?
              AND {$endExpr} >= ?
              {$visibilityClause}
              {$extraFilterSql}
            GROUP BY
                ce.id,
                ce.created_by,
                {$startExpr},
                {$endExpr},
                ce.title,
                ce.note,
                {$eventTypeExpr},
                {$progressExpr},
                {$completedExpr},
                {$creatorExpr}
            ORDER BY {$startExpr} DESC, ce.id DESC
            LIMIT {$offset}, {$perPage}
        ");
        if ($stmt) {
            $bindTypes = 'ss';
            $bindValues = [$end, $start];
            if (!$isSuperAdmin) {
                if ($hasAssigneeTable) {
                    $bindTypes .= 'ii';
                    $bindValues[] = $currentUserId;
                    $bindValues[] = $currentUserId;
                } else {
                    $bindTypes .= 'i';
                    $bindValues[] = $currentUserId;
                }
            }
            if ($ownerFilter === 'created_by_me') {
                $bindTypes .= 'i';
                $bindValues[] = $currentUserId;
            } elseif ($ownerFilter === 'assigned_to_me' && $hasAssigneeTable) {
                $bindTypes .= 'i';
                $bindValues[] = $currentUserId;
            }
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
            $row['event_type_label'] = $this->typeLabel((string)($row['event_type'] ?? 'reminder'));
            $row['progress_percent'] = (int)($row['progress_percent'] ?? 0);
            $row['is_completed'] = (int)($row['is_completed'] ?? 0);
            $events[] = $row;
        }
        $stmt->close();

        $eventStats = [
            'total' => count($events),
            'with_note' => 0,
            'created_by_me' => 0,
            'today' => 0,
        ];
        $currentUserId = (int)($_SESSION['auth_user_id'] ?? 0);
        $todayStr = date('Y-m-d');
        foreach ($events as $event) {
            if (trim((string)($event['note'] ?? '')) !== '') {
                $eventStats['with_note']++;
            }
            if ((int)($event['created_by_id'] ?? 0) === $currentUserId) {
                $eventStats['created_by_me']++;
            }
            $eventStartDate = (string)($event['start_date'] ?? '');
            $eventEndDate = (string)($event['end_date'] ?? $eventStartDate);
            if ($eventStartDate <= $todayStr && $todayStr <= $eventEndDate) {
                $eventStats['today']++;
            }
        }

        $title = '行事历管理 / 事件列表';
        $contentView = __DIR__ . '/../Views/calendar/events.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function updateStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /');
            exit;
        }
        if (!$this->hasAnyPermission(['menu.calendar', 'menu.dashboard'])) {
            $this->denyNoPermission('无权限更新事件状态');
        }
        if (!$this->hasAnyPermission(['calendar.events.update_status', 'dashboard.calendar.manage'])) {
            $this->denyNoPermission('无权限更新事件状态');
        }
        $conn = require __DIR__ . '/../../config/database.php';
        $eventId = (int)($_POST['event_id'] ?? 0);
        $progress = (int)($_POST['progress_percent'] ?? 0);
        $isCompleted = isset($_POST['is_completed']) ? 1 : 0;
        $redirect = trim((string)($_POST['redirect'] ?? '/'));
        if ($redirect === '' || $redirect[0] !== '/') {
            $redirect = '/';
        }

        if ($progress < 0) {
            $progress = 0;
        }
        if ($progress > 100) {
            $progress = 100;
        }
        if ($isCompleted === 1 && $progress < 100) {
            $progress = 100;
        }
        if ($eventId <= 0) {
            header('Location: ' . $redirect);
            exit;
        }
        if (!$this->columnExists($conn, 'calendar_events', 'progress_percent') || !$this->columnExists($conn, 'calendar_events', 'is_completed')) {
            header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'err=missing_progress_columns');
            exit;
        }
        $userId = (int)($_SESSION['auth_user_id'] ?? 0);
        if (!$this->canAccessEvent($conn, $eventId, $userId)) {
            $this->denyNoPermission('无权限更新此事件');
        }
        $oldProgress = 0;
        $oldCompleted = 0;
        $eventTitle = '';
        $eventCreatedBy = 0;
        $readStmt = $conn->prepare('SELECT progress_percent, is_completed, title, created_by FROM calendar_events WHERE id = ? LIMIT 1');
        if ($readStmt) {
            $readStmt->bind_param('i', $eventId);
            $readStmt->execute();
            $row = $readStmt->get_result()->fetch_assoc();
            if ($row) {
                $oldProgress = (int)($row['progress_percent'] ?? 0);
                $oldCompleted = (int)($row['is_completed'] ?? 0);
                $eventTitle = trim((string)($row['title'] ?? ''));
                $eventCreatedBy = (int)($row['created_by'] ?? 0);
            }
            $readStmt->close();
        }
        $stmt = $conn->prepare('UPDATE calendar_events SET progress_percent = ?, is_completed = ? WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('iii', $progress, $isCompleted, $eventId);
            $stmt->execute();
            $stmt->close();
        }
        if ($this->tableExists($conn, 'calendar_event_status_logs')) {
            $logStmt = $conn->prepare('
                INSERT INTO calendar_event_status_logs (event_id, changed_by, old_progress_percent, new_progress_percent, old_is_completed, new_is_completed)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            if ($logStmt) {
                $logStmt->bind_param('iiiiii', $eventId, $userId, $oldProgress, $progress, $oldCompleted, $isCompleted);
                $logStmt->execute();
                $logStmt->close();
            }
        }
        $this->writeAuditLog($conn, 'calendar', 'calendar.events.update_status', 'event', $eventId, [
            'old_progress_percent' => $oldProgress,
            'new_progress_percent' => $progress,
            'old_is_completed' => $oldCompleted,
            'new_is_completed' => $isCompleted,
        ]);
        $assigneeIds = $this->fetchEventAssigneeIds($conn, $eventId);
        $statusNotifyTitle = '行事历状态更新：' . ($eventTitle !== '' ? $eventTitle : ('#' . $eventId));
        $statusNotifyContent = sprintf('进度 %d%% -> %d%%；完成：%s -> %s', $oldProgress, $progress, $oldCompleted ? '是' : '否', $isCompleted ? '是' : '否');
        $this->sendCalendarNotifications(
            $conn,
            'calendar.status_updated',
            $eventId,
            $eventCreatedBy,
            $assigneeIds,
            $userId,
            $statusNotifyTitle,
            $statusNotifyContent
        );
        if ($isCompleted === 1) {
            $this->sendCalendarNotifications(
                $conn,
                'calendar.completed',
                $eventId,
                $eventCreatedBy,
                $assigneeIds,
                $userId,
                '行事历事件已完成：' . ($eventTitle !== '' ? $eventTitle : ('#' . $eventId)),
                sprintf('事件已标记完成，当前进度 %d%%', $progress)
            );
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function statusLogs(): void
    {
        if (!$this->hasAnyPermission(['menu.calendar', 'menu.dashboard'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => '无权限']);
            exit;
        }
        $eventId = (int)($_GET['event_id'] ?? 0);
        $conn = require __DIR__ . '/../../config/database.php';
        $userId = (int)($_SESSION['auth_user_id'] ?? 0);
        if ($eventId <= 0 || !$this->canAccessEvent($conn, $eventId, $userId)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => '无权限']);
            exit;
        }
        if (!$this->tableExists($conn, 'calendar_event_status_logs')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'logs' => [],
                'missing_log_table' => true,
                'message' => '未找到状态日志表，请先执行 migration：009_create_calendar_event_status_logs.sql',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $logs = [];
        $stmt = $conn->prepare('
            SELECT
                l.id,
                l.old_progress_percent,
                l.new_progress_percent,
                l.old_is_completed,
                l.new_is_completed,
                l.created_at,
                u.full_name AS changed_by_full_name,
                u.username AS changed_by_username
            FROM calendar_event_status_logs l
            LEFT JOIN users u ON u.id = l.changed_by
            WHERE l.event_id = ?
            ORDER BY l.id DESC
            LIMIT 30
        ');
        if ($stmt) {
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $logs[] = [
                    'id' => (int)$row['id'],
                    'old_progress_percent' => (int)$row['old_progress_percent'],
                    'new_progress_percent' => (int)$row['new_progress_percent'],
                    'old_is_completed' => (int)$row['old_is_completed'],
                    'new_is_completed' => (int)$row['new_is_completed'],
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'changed_by_name' => trim((string)($row['changed_by_full_name'] ?? '')) !== ''
                        ? (string)$row['changed_by_full_name']
                        : (string)($row['changed_by_username'] ?? ''),
                ];
            }
            $stmt->close();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'logs' => $logs,
            'missing_log_table' => false,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
