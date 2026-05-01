<?php

trait AuditLogTrait
{
    protected function writeStandardAuditLog(
        mysqli $conn,
        string $moduleKey,
        string $actionKey,
        ?string $targetType = null,
        ?int $targetId = null,
        array $detail = []
    ): void {
        if (!method_exists($this, 'tableExists') || !$this->tableExists($conn, 'system_audit_logs')) {
            return;
        }
        $actorUserId = isset($_SESSION['auth_user_id']) ? (int)$_SESSION['auth_user_id'] : null;
        $actorName = trim((string)($_SESSION['auth_full_name'] ?? ''));
        if ($actorName === '') {
            $actorName = (string)($_SESSION['auth_username'] ?? '');
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $json = !empty($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $conn->prepare('
            INSERT INTO system_audit_logs (
                module_key, action_key, target_type, target_id, actor_user_id, actor_name, detail_json, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmt) {
            $stmt->bind_param(
                'sssissss',
                $moduleKey,
                $actionKey,
                $targetType,
                $targetId,
                $actorUserId,
                $actorName,
                $json,
                $ip
            );
            $stmt->execute();
            $stmt->close();
        }
    }
}
