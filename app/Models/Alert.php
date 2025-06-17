<?php

namespace App\Models;

use PDO;
use DateTime;

class Alert extends Model {
    protected string $table = 'alerts';
    public ?int $id = null;
    public ?int $user_id = null; // User associated with the alert, if any
    public string $alert_type; // e.g., 'closing_due', 'large_discrepancy', 'missing_data'
    public string $message;
    public string $severity = 'warning'; // e.g., 'info', 'warning', 'critical'
    public bool $is_resolved = false;
    public ?string $resolved_at = null; // Timestamp
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Get the User associated with this alert, if any.
     * @return User|null
     */
    public function user(): ?User {
        if ($this->user_id === null) {
            return null;
        }
        return User::find($this->user_id);
    }

    /**
     * Mark an alert as resolved.
     * @return bool
     */
    public function resolve(): bool {
        if ($this->is_resolved) {
            return true; // Already resolved
        }
        $this->is_resolved = true;
        $this->resolved_at = (new DateTime())->format('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Get unresolved alerts.
     * @param string|null $severity Filter by severity if needed.
     * @return array
     */
    public static function getUnresolvedAlerts(?string $severity = null): array {
        $sql = "SELECT a.*, u.username as user_username
                FROM alerts a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.is_resolved = FALSE";
        $params = [];
        if ($severity !== null) {
            $sql .= " AND severity = :severity";
            $params['severity'] = $severity;
        }
        $sql .= " ORDER BY a.created_at DESC";

        return self::query($sql, $params);
    }

    /**
     * Get all alerts, newest first.
     * @param int $limit
     * @return array
     */
    public static function getAllAlerts(int $limit = 50): array {
         $sql = "SELECT a.*, u.username as user_username
                FROM alerts a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.created_at DESC LIMIT :limit";
        $params['limit'] = $limit;

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Create a new alert.
     *
     * @param string $type Alert type.
     * @param string $message Alert message.
     * @param string $severity Severity ('info', 'warning', 'critical').
     * @param int|null $userId Associated user ID.
     * @return int|false ID of the created alert or false on failure.
     */
    public static function newAlert(string $type, string $message, string $severity = 'warning', ?int $userId = null): int|false
    {
        $data = [
            'alert_type' => $type,
            'message' => $message,
            'severity' => $severity,
            'user_id' => $userId,
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'is_resolved' => false
        ];
        return self::create($data);
    }
}
