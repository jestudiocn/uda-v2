<?php

class DashboardController
{
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
        $safeTable = $conn->real_escape_string($table);
        $safeCol = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
        $ok = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $ok;
    }

    private function fetchCalendarPendingItems(mysqli $conn, int $userId, bool $isSuperAdmin, int $limit = 80): array
    {
        if ($userId <= 0 || !$this->columnExists($conn, 'calendar_events', 'is_completed')) {
            return [];
        }
        $hasAssigneeTable = $this->tableExists($conn, 'calendar_event_assignees');
        $hasProgressColumn = $this->columnExists($conn, 'calendar_events', 'progress_percent');
        $hasEventType = $this->columnExists($conn, 'calendar_events', 'event_type');
        $progressExpr = $hasProgressColumn ? 'ce.progress_percent' : '0';
        $eventTypeExpr = $hasEventType ? 'ce.event_type' : "'reminder'";
        $assigneesExpr = $hasAssigneeTable
            ? "GROUP_CONCAT(DISTINCT COALESCE(NULLIF(au.full_name, ''), au.username) ORDER BY COALESCE(NULLIF(au.full_name, ''), au.username) SEPARATOR ', ')"
            : "''";
        $assigneeJoin = $hasAssigneeTable
            ? 'LEFT JOIN calendar_event_assignees cea ON cea.event_id = ce.id LEFT JOIN users au ON au.id = cea.user_id'
            : '';
        $creatorExpr = "COALESCE(NULLIF(cu.full_name, ''), cu.username)";
        $visibilitySql = $hasAssigneeTable
            ? '(ce.created_by = ? OR EXISTS (SELECT 1 FROM calendar_event_assignees x WHERE x.event_id = ce.id AND x.user_id = ?))'
            : 'ce.created_by = ?';
        $sql = "
            SELECT
                ce.id,
                ce.title,
                COALESCE(ce.start_date, ce.event_date) AS start_date,
                COALESCE(ce.end_date, ce.event_date) AS end_date,
                {$progressExpr} AS progress_percent,
                ce.is_completed,
                {$eventTypeExpr} AS event_type,
                {$creatorExpr} AS creator,
                {$assigneesExpr} AS assignees
            FROM calendar_events ce
            LEFT JOIN users cu ON cu.id = ce.created_by
            {$assigneeJoin}
            WHERE COALESCE(ce.is_completed, 0) = 0
              AND {$visibilitySql}
            GROUP BY
                ce.id,
                ce.title,
                COALESCE(ce.start_date, ce.event_date),
                COALESCE(ce.end_date, ce.event_date),
                {$progressExpr},
                ce.is_completed,
                {$eventTypeExpr},
                {$creatorExpr}
            ORDER BY COALESCE(ce.end_date, ce.event_date) ASC, ce.id DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($hasAssigneeTable) {
            $stmt->bind_param('ii', $userId, $userId);
        } else {
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $progress = max(0, min(100, (int)($row['progress_percent'] ?? 0)));
            $statusLabel = $progress <= 0 ? '未处理' : ($progress >= 100 ? '待完成' : '待处理');
            $row['progress_percent'] = $progress;
            $row['status_label'] = $statusLabel;
            $row['event_type_label'] = ((string)($row['event_type'] ?? 'reminder')) === 'meeting'
                ? '会议'
                : (((string)($row['event_type'] ?? 'reminder')) === 'todo' ? '待办' : '提醒');
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    private function fetchPayablesPendingItems(mysqli $conn, int $userId, int $limit = 80): array
    {
        if ($userId <= 0 || !$this->tableExists($conn, 'payables')) {
            return [];
        }
        $items = [];
        $stmt = $conn->prepare('
            SELECT
                p.id,
                p.vendor_name AS title,
                p.expected_pay_date AS start_date,
                p.expected_pay_date AS end_date,
                p.amount,
                p.status,
                COALESCE(NULLIF(u.full_name, \'\'), u.username) AS creator
            FROM payables p
            LEFT JOIN users u ON u.id = p.created_by
            WHERE p.status = \'pending\' AND p.created_by = ?
            ORDER BY p.expected_pay_date ASC, p.id DESC
            LIMIT ?
        ');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $dueDate = (string)($row['end_date'] ?? '');
            $today = date('Y-m-d');
            $dueLevel = 'normal';
            $statusLabel = '待处理';
            if ($dueDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                if ($dueDate < $today) {
                    $dueLevel = 'overdue';
                    $statusLabel = '已逾期';
                } elseif ($dueDate === $today) {
                    $dueLevel = 'due_today';
                    $statusLabel = '今天到期';
                }
            }
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'start_date' => (string)($row['start_date'] ?? ''),
                'end_date' => (string)($row['end_date'] ?? ''),
                'progress_percent' => 0,
                'status_label' => $statusLabel,
                'event_type_label' => '待付款',
                'creator' => (string)($row['creator'] ?? ''),
                'assignees' => '',
                'module_key' => 'payables',
                'module_label' => '待付款',
                'amount' => (float)($row['amount'] ?? 0),
                'due_level' => $dueLevel,
            ];
        }
        $stmt->close();
        return $items;
    }

    private function fetchReceivablesPendingItems(mysqli $conn, int $userId, int $limit = 80): array
    {
        if ($userId <= 0 || !$this->tableExists($conn, 'receivables')) {
            return [];
        }
        $items = [];
        $stmt = $conn->prepare('
            SELECT
                r.id,
                r.client_name AS title,
                r.expected_receive_date AS start_date,
                r.expected_receive_date AS end_date,
                r.amount,
                r.status,
                COALESCE(NULLIF(u.full_name, \'\'), u.username) AS creator
            FROM receivables r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.status = \'pending\' AND r.created_by = ?
            ORDER BY r.expected_receive_date ASC, r.id DESC
            LIMIT ?
        ');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $dueDate = (string)($row['end_date'] ?? '');
            $today = date('Y-m-d');
            $dueLevel = 'normal';
            $statusLabel = '待处理';
            if ($dueDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                if ($dueDate < $today) {
                    $dueLevel = 'overdue';
                    $statusLabel = '已逾期';
                } elseif ($dueDate === $today) {
                    $dueLevel = 'due_today';
                    $statusLabel = '今天到期';
                }
            }
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'start_date' => (string)($row['start_date'] ?? ''),
                'end_date' => (string)($row['end_date'] ?? ''),
                'progress_percent' => 0,
                'status_label' => $statusLabel,
                'event_type_label' => '待收款',
                'creator' => (string)($row['creator'] ?? ''),
                'assignees' => '',
                'module_key' => 'receivables',
                'module_label' => '待收款',
                'amount' => (float)($row['amount'] ?? 0),
                'due_level' => $dueLevel,
            ];
        }
        $stmt->close();
        return $items;
    }

    public function index(): void
    {
        $conn = require __DIR__ . '/../../config/database.php';
        $message = '';
        $error = '';
        $showPendingTodoPopup = (int)($_SESSION['show_pending_todo_popup'] ?? 0) === 1;
        if ($showPendingTodoPopup) {
            unset($_SESSION['show_pending_todo_popup']);
        }
        $pendingTodoItems = [];

        $monthInput = trim((string)($_GET['month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $monthInput = date('Y-m');
        }

        $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $monthInput . '-01');
        if (!$monthStart) {
            $monthStart = new DateTimeImmutable(date('Y-m-01'));
            $monthInput = $monthStart->format('Y-m');
        }

        $prevMonth = $monthStart->modify('-1 month')->format('Y-m');
        $nextMonth = $monthStart->modify('+1 month')->format('Y-m');
        $monthEnd = $monthStart->modify('last day of this month');

        $eventsByDate = [];
        $calendarTableReady = true;
        try {
            $hasStartDate = false;
            $hasEndDate = false;
            $colStartRes = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'start_date'");
            if ($colStartRes instanceof mysqli_result && $colStartRes->num_rows > 0) {
                $hasStartDate = true;
            }
            if ($colStartRes instanceof mysqli_result) {
                $colStartRes->free();
            }
            $colEndRes = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'end_date'");
            if ($colEndRes instanceof mysqli_result && $colEndRes->num_rows > 0) {
                $hasEndDate = true;
            }
            if ($colEndRes instanceof mysqli_result) {
                $colEndRes->free();
            }

            $startExpr = $hasStartDate ? 'COALESCE(start_date, event_date)' : 'event_date';
            $endExpr = $hasEndDate ? 'COALESCE(end_date, event_date)' : 'event_date';
            $hasProgress = false;
            $hasCompleted = false;
            $hasEventType = false;
            $hasAssigneeTable = false;
            $progressRes = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'progress_percent'");
            if ($progressRes instanceof mysqli_result && $progressRes->num_rows > 0) {
                $hasProgress = true;
            }
            if ($progressRes instanceof mysqli_result) {
                $progressRes->free();
            }
            $completedRes = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'is_completed'");
            if ($completedRes instanceof mysqli_result && $completedRes->num_rows > 0) {
                $hasCompleted = true;
            }
            if ($completedRes instanceof mysqli_result) {
                $completedRes->free();
            }
            $typeRes = $conn->query("SHOW COLUMNS FROM calendar_events LIKE 'event_type'");
            if ($typeRes instanceof mysqli_result && $typeRes->num_rows > 0) {
                $hasEventType = true;
            }
            if ($typeRes instanceof mysqli_result) {
                $typeRes->free();
            }
            $assigneeTblRes = $conn->query("SHOW TABLES LIKE 'calendar_event_assignees'");
            if ($assigneeTblRes instanceof mysqli_result && $assigneeTblRes->num_rows > 0) {
                $hasAssigneeTable = true;
            }
            if ($assigneeTblRes instanceof mysqli_result) {
                $assigneeTblRes->free();
            }
            $progressExpr = $hasProgress ? 'ce.progress_percent' : '0';
            $completedExpr = $hasCompleted ? 'ce.is_completed' : '0';
            $eventTypeExpr = $hasEventType ? 'ce.event_type' : "'reminder'";
            $creatorExpr = "COALESCE(NULLIF(cu.full_name, ''), cu.username)";
            $assigneeExpr = $hasAssigneeTable
                ? "GROUP_CONCAT(DISTINCT COALESCE(NULLIF(au.full_name, ''), au.username) ORDER BY COALESCE(NULLIF(au.full_name, ''), au.username) SEPARATOR ', ')"
                : "''";
            $assigneeJoin = $hasAssigneeTable ? 'LEFT JOIN calendar_event_assignees cea ON cea.event_id = ce.id LEFT JOIN users au ON au.id = cea.user_id' : '';
            $isSuperAdmin = (string)($_SESSION['auth_role_name'] ?? '') === 'super_admin';
            $currentUserId = (int)($_SESSION['auth_user_id'] ?? 0);
            $visibilityClause = '';
            if (!$isSuperAdmin) {
                $visibilityClause = $hasAssigneeTable
                    ? 'AND (ce.created_by = ? OR EXISTS (SELECT 1 FROM calendar_event_assignees cea2 WHERE cea2.event_id = ce.id AND cea2.user_id = ?))'
                    : 'AND ce.created_by = ?';
            }
            $sql = "
                SELECT
                    ce.id,
                    ce.event_date,
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
                LEFT JOIN users cu ON cu.id = ce.created_by
                {$assigneeJoin}
                WHERE {$startExpr} <= ?
                  AND {$endExpr} >= ?
                  {$visibilityClause}
                GROUP BY
                    ce.id,
                    ce.event_date,
                    {$startExpr},
                    {$endExpr},
                    ce.title,
                    ce.note,
                    {$eventTypeExpr},
                    {$progressExpr},
                    {$completedExpr},
                    {$creatorExpr}
                ORDER BY {$startExpr} ASC, ce.id ASC
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Prepare failed in dashboard calendar query');
            }
            $startStr = $monthStart->format('Y-m-d');
            $endStr = $monthEnd->format('Y-m-d');
            if ($isSuperAdmin) {
                $stmt->bind_param('ss', $endStr, $startStr);
            } elseif ($hasAssigneeTable) {
                $stmt->bind_param('ssii', $endStr, $startStr, $currentUserId, $currentUserId);
            } else {
                $stmt->bind_param('ssi', $endStr, $startStr, $currentUserId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $rawStart = (string)($row['start_date'] ?? $row['event_date']);
                $rawEnd = (string)($row['end_date'] ?? $row['event_date']);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawEnd)) {
                    continue;
                }
                if ($rawStart > $rawEnd) {
                    $tmp = $rawStart;
                    $rawStart = $rawEnd;
                    $rawEnd = $tmp;
                }

                $displayStart = ($rawStart < $startStr) ? $startStr : $rawStart;
                $displayEnd = ($rawEnd > $endStr) ? $endStr : $rawEnd;
                $cur = DateTimeImmutable::createFromFormat('Y-m-d', $displayStart);
                $stop = DateTimeImmutable::createFromFormat('Y-m-d', $displayEnd);
                if (!$cur || !$stop) {
                    continue;
                }
                while ($cur <= $stop) {
                    $dateKey = $cur->format('Y-m-d');
                    if (!isset($eventsByDate[$dateKey])) {
                        $eventsByDate[$dateKey] = [];
                    }
                    $eventsByDate[$dateKey][] = $row;
                    $cur = $cur->modify('+1 day');
                }
            }
            $stmt->close();
        } catch (Throwable $e) {
            $calendarTableReady = false;
            $error = '行事历资料表尚未建立，请先执行 migration：004_create_calendar_events.sql';
        }

        $firstWeekDay = (int)$monthStart->format('N'); // 1 (Mon) - 7 (Sun)
        $daysInMonth = (int)$monthStart->format('t');
        $calendarCells = [];
        for ($i = 1; $i < $firstWeekDay; $i++) {
            $calendarCells[] = null;
        }
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateObj = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $day);
            $dateKey = $dateObj->format('Y-m-d');
            $calendarCells[] = [
                'day' => $day,
                'date' => $dateKey,
                'events' => $eventsByDate[$dateKey] ?? [],
                'is_today' => $dateKey === date('Y-m-d'),
            ];
        }
        while (count($calendarCells) % 7 !== 0) {
            $calendarCells[] = null;
        }

        if ($showPendingTodoPopup) {
            $currentUserId = (int)($_SESSION['auth_user_id'] ?? 0);
            $isSuperAdmin = (string)($_SESSION['auth_role_name'] ?? '') === 'super_admin';
            $pendingTodoItems = array_merge(
                $this->fetchCalendarPendingItems($conn, $currentUserId, $isSuperAdmin, 15),
                $this->fetchPayablesPendingItems($conn, $currentUserId, 8),
                $this->fetchReceivablesPendingItems($conn, $currentUserId, 8)
            );
            usort($pendingTodoItems, static function (array $a, array $b): int {
                $priorityMap = ['overdue' => 0, 'due_today' => 1, 'normal' => 2];
                $aLevel = (string)($a['due_level'] ?? 'normal');
                $bLevel = (string)($b['due_level'] ?? 'normal');
                $aPriority = $priorityMap[$aLevel] ?? 2;
                $bPriority = $priorityMap[$bLevel] ?? 2;
                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }
                $aDate = (string)($a['end_date'] ?? $a['start_date'] ?? '');
                $bDate = (string)($b['end_date'] ?? $b['start_date'] ?? '');
                return strcmp($aDate, $bDate);
            });
            $pendingTodoItems = array_slice($pendingTodoItems, 0, 25);
        }

        $title = 'UDA-V2 控制台';
        $contentView = __DIR__ . '/../Views/dashboard/index.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function pendingTasks(): void
    {
        $conn = require __DIR__ . '/../../config/database.php';
        $currentUserId = (int)($_SESSION['auth_user_id'] ?? 0);
        $isSuperAdmin = (string)($_SESSION['auth_role_name'] ?? '') === 'super_admin';
        $selectedModule = trim((string)($_GET['module'] ?? 'all'));
        if (!in_array($selectedModule, ['all', 'calendar', 'payables', 'receivables', 'issues'], true)) {
            $selectedModule = 'all';
        }
        $perPage = (int)($_GET['per_page'] ?? 20);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }
        $page = (int)($_GET['page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }
        $calendarItems = $this->fetchCalendarPendingItems($conn, $currentUserId, $isSuperAdmin, 200);
        $payableItems = $this->fetchPayablesPendingItems($conn, $currentUserId, 200);
        $receivableItems = $this->fetchReceivablesPendingItems($conn, $currentUserId, 200);
        $moduleStats = [
            'calendar' => count($calendarItems),
            'payables' => count($payableItems),
            'receivables' => count($receivableItems),
            'issues' => 0,
        ];
        $pendingItems = [];
        if ($selectedModule === 'all' || $selectedModule === 'calendar') {
            foreach ($calendarItems as $item) {
                $item['module_key'] = 'calendar';
                $item['module_label'] = '行事历';
                $pendingItems[] = $item;
            }
        }
        if ($selectedModule === 'all' || $selectedModule === 'payables') {
            foreach ($payableItems as $item) {
                $pendingItems[] = $item;
            }
        }
        if ($selectedModule === 'all' || $selectedModule === 'receivables') {
            foreach ($receivableItems as $item) {
                $pendingItems[] = $item;
            }
        }
        usort($pendingItems, static function (array $a, array $b): int {
            $priorityMap = ['overdue' => 0, 'due_today' => 1, 'normal' => 2];
            $aLevel = (string)($a['due_level'] ?? 'normal');
            $bLevel = (string)($b['due_level'] ?? 'normal');
            $aPriority = $priorityMap[$aLevel] ?? 2;
            $bPriority = $priorityMap[$bLevel] ?? 2;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }
            $aDate = (string)($a['end_date'] ?? $a['start_date'] ?? '');
            $bDate = (string)($b['end_date'] ?? $b['start_date'] ?? '');
            return strcmp($aDate, $bDate);
        });
        $total = count($pendingItems);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $pendingItems = array_slice($pendingItems, ($page - 1) * $perPage, $perPage);
        $title = '待处理事件列表';
        $contentView = __DIR__ . '/../Views/dashboard/pending_tasks.php';
        require __DIR__ . '/../Views/layouts/main.php';
    }
}
