<?php

namespace App\Models;

use PDO;

class Operation extends Model {
    protected string $table = 'operations';
    public ?int $id = null;
    public int $user_id;
    public int $service_id;
    public int $operation_type_id;
    public ?int $balance_id = null; // Link to daily balance
    public float $amount;
    public float $commission_applied;
    public string $operation_time; // Timestamp
    public ?string $description = null;
    public ?string $reference_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Get the User (Gérant) who performed the operation.
     * @return User|null
     */
    public function user(): ?User {
        return User::find($this->user_id);
    }

    /**
     * Get the Service used for the operation.
     * @return Service|null
     */
    public function service(): ?Service {
        return Service::find($this->service_id);
    }

    /**
     * Get the Type of operation.
     * @return OperationType|null
     */
    public function operationType(): ?OperationType {
        return OperationType::find($this->operation_type_id);
    }

    /**
     * Get the Balance record this operation is linked to, if any.
     * @return Balance|null
     */
    public function balance(): ?Balance {
        if ($this->balance_id === null) {
            return null;
        }
        return Balance::find($this->balance_id);
    }

    /**
     * Get operations within a specific date range.
     * @param string $startDate (YYYY-MM-DD HH:MM:SS)
     * @param string $endDate (YYYY-MM-DD HH:MM:SS)
     * @param int|null $userId
     * @param int|null $serviceId
     * @param int|null $operationTypeId
     * @return array
     */
    public static function getOperationsByDateRange(
        string $startDate,
        string $endDate,
        ?int $userId = null,
        ?int $serviceId = null,
        ?int $operationTypeId = null
    ): array {
        $sql = "SELECT o.*, u.username as user_username, s.name as service_name, ot.name as operation_type_name
                FROM operations o
                JOIN users u ON o.user_id = u.id
                JOIN services s ON o.service_id = s.id
                JOIN operation_types ot ON o.operation_type_id = ot.id
                WHERE o.operation_time BETWEEN :start_date AND :end_date";

        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($userId !== null) {
            $sql .= " AND o.user_id = :user_id";
            $params['user_id'] = $userId;
        }
        if ($serviceId !== null) {
            $sql .= " AND o.service_id = :service_id";
            $params['service_id'] = $serviceId;
        }
        if ($operationTypeId !== null) {
            $sql .= " AND o.operation_type_id = :operation_type_id";
            $params['operation_type_id'] = $operationTypeId;
        }

        $sql .= " ORDER BY o.operation_time DESC";

        return self::query($sql, $params);
    }

    /**
     * Get recent operations.
     * @param int $limit
     * @param int|null $userId
     * @return array
     */
    public static function getRecentOperations(int $limit = 10, ?int $userId = null): array {
        $sql = "SELECT o.*, u.username as user_username, s.name as service_name, ot.name as operation_type_name
                FROM operations o
                JOIN users u ON o.user_id = u.id
                JOIN services s ON o.service_id = s.id
                JOIN operation_types ot ON o.operation_type_id = ot.id";
        $params = [];
        if ($userId !== null) {
            $sql .= " WHERE o.user_id = :user_id";
            $params['user_id'] = $userId;
        }
        $sql .= " ORDER BY o.operation_time DESC LIMIT :limit";
        $params['limit'] = $limit;

        // Need to use raw prepare/execute for fetchAll with params for LIMIT
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }
}
