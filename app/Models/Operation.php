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

    /**
     * Get the N most recent operations for a specific user.
     * @param int $userId The ID of the user.
     * @param int $limit The maximum number of operations to retrieve.
     * @return array An array of Operation objects.
     */
    public static function getRecentForUser(int $userId, int $limit = 5): array {
        $sql = "SELECT o.*, u.username as user_username, s.name as service_name, ot.name as operation_type_name
                FROM operations o
                JOIN users u ON o.user_id = u.id
                JOIN services s ON o.service_id = s.id
                JOIN operation_types ot ON o.operation_type_id = ot.id
                WHERE o.user_id = :user_id
                ORDER BY o.operation_time DESC
                LIMIT :limit";

        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Get a summary of operations for a specific user for the current date.
     * Includes total number of operations and total amount.
     * @param int $userId The ID of the user.
     * @return array An array with 'count' and 'total_amount'.
     */
    public static function getSummaryForUserToday(int $userId): array {
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');

        $sql = "SELECT COUNT(*) as count, SUM(amount) as total_amount
                FROM operations
                WHERE user_id = :user_id
                AND operation_time BETWEEN :today_start AND :today_end";

        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':today_start', $today_start, PDO::PARAM_STR);
        $stmt->bindParam(':today_end', $today_end, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'count' => (int)($result['count'] ?? 0),
            'total_amount' => (float)($result['total_amount'] ?? 0.0)
        ];
    }

    /**
     * Find an operation by its ID.
     * (This might leverage a base Model::find method if available and suitable)
     * @param int $id
     * @return Operation|null
     */
    public static function findById(int $id): ?Operation {
        // Assuming Model::find() handles fetching and hydrating to static::class
        $operation = parent::find($id);
        return $operation instanceof Operation ? $operation : null;
    }

    /**
     * Create a new operation.
     * @param array $data Associative array of operation data.
     * @return Operation|null The created Operation object or null on failure.
     */
    public static function createOperation(array $data): ?Operation {
        // Basic validation: ensure required fields are present
        if (empty($data['user_id']) || empty($data['service_id']) || empty($data['operation_type_id']) || !isset($data['amount'])) {
            return null; // Or throw an InvalidArgumentException
        }

        $db = self::db();
        $sql = "INSERT INTO " . (new static())->table . "
                (user_id, service_id, operation_type_id, amount, commission_applied, operation_time, description, reference_id, balance_id, created_at, updated_at)
                VALUES (:user_id, :service_id, :operation_type_id, :amount, :commission_applied, :operation_time, :description, :reference_id, :balance_id, NOW(), NOW())";

        $stmt = $db->prepare($sql);

        $success = $stmt->execute([
            ':user_id' => $data['user_id'],
            ':service_id' => $data['service_id'],
            ':operation_type_id' => $data['operation_type_id'],
            ':amount' => $data['amount'],
            ':commission_applied' => $data['commission_applied'] ?? 0.0,
            ':operation_time' => $data['operation_time'] ?? date('Y-m-d H:i:s'),
            ':description' => $data['description'] ?? null,
            ':reference_id' => $data['reference_id'] ?? null,
            ':balance_id' => $data['balance_id'] ?? null,
        ]);

        if ($success) {
            $id = $db->lastInsertId();
            return self::findById((int)$id);
        }
        return null;
    }

    /**
     * Update an existing operation.
     * @param int $id The ID of the operation to update.
     * @param array $data Associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public static function updateOperation(int $id, array $data): bool {
        if (empty($data)) {
            return false; // Nothing to update
        }

        $fields = [];
        $params = [':id' => $id];
        foreach ($data as $key => $value) {
            // Allow only specific fields to be updated to prevent mass assignment vulnerabilities
            if (in_array($key, ['service_id', 'operation_type_id', 'amount', 'commission_applied', 'operation_time', 'description', 'reference_id', 'balance_id'])) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($fields)) {
            return false; // No valid fields to update
        }

        $fields[] = "updated_at = NOW()"; // Always update the timestamp

        $sql = "UPDATE " . (new static())->table . " SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete an operation.
     * @param int $id The ID of the operation to delete.
     * @return bool True on success, false on failure.
     */
    public static function deleteOperation(int $id): bool {
        $sql = "DELETE FROM " . (new static())->table . " WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Find all operations for a specific user with optional filters and pagination.
     * @param int $userId
     * @param array $filters Associative array of filters (e.g., ['service_id' => 1, 'date_from' => '2023-01-01'])
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function findAllByUser(int $userId, array $filters = [], int $limit = 10, int $offset = 0): array {
        $params = [':user_id' => $userId, ':limit' => $limit, ':offset' => $offset];
        $sql = "SELECT o.*, u.username as user_username, s.name as service_name, ot.name as operation_type_name
                FROM operations o
                JOIN users u ON o.user_id = u.id
                JOIN services s ON o.service_id = s.id
                JOIN operation_types ot ON o.operation_type_id = ot.id
                WHERE o.user_id = :user_id";

        // Add more filters
        if (!empty($filters['service_id'])) {
            $sql .= " AND o.service_id = :service_id";
            $params[':service_id'] = $filters['service_id'];
        }
        if (!empty($filters['operation_type_id'])) {
            $sql .= " AND o.operation_type_id = :operation_type_id";
            $params[':operation_type_id'] = $filters['operation_type_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND o.operation_time >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND o.operation_time <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        // Add other filters as needed

        $sql .= " ORDER BY o.operation_time DESC LIMIT :limit OFFSET :offset";

        $stmt = self::db()->prepare($sql);
        // Bind parameters correctly, especially for limit and offset
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Find all operations (for admin) with optional filters and pagination.
     * @param array $filters Associative array of filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function findAllAdmin(array $filters = [], int $limit = 10, int $offset = 0): array {
        $params = [':limit' => $limit, ':offset' => $offset];
        $sql = "SELECT o.*, u.username as user_username, s.name as service_name, ot.name as operation_type_name
                FROM operations o
                JOIN users u ON o.user_id = u.id
                JOIN services s ON o.service_id = s.id
                JOIN operation_types ot ON o.operation_type_id = ot.id";

        $whereClauses = [];
        if (!empty($filters['user_id'])) {
            $whereClauses[] = "o.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['service_id'])) {
            $whereClauses[] = "o.service_id = :service_id";
            $params[':service_id'] = $filters['service_id'];
        }
        if (!empty($filters['operation_type_id'])) {
            $whereClauses[] = "o.operation_type_id = :operation_type_id";
            $params[':operation_type_id'] = $filters['operation_type_id'];
        }
        if (!empty($filters['date_from'])) {
            $whereClauses[] = "o.operation_time >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $whereClauses[] = "o.operation_time <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $sql .= " ORDER BY o.operation_time DESC LIMIT :limit OFFSET :offset";

        $stmt = self::db()->prepare($sql);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Get all operations for a specific user created today that are not yet closed (balance_id IS NULL).
     * @param int $userId The ID of the user.
     * @return array An array of Operation objects.
     */
    public static function getUnclosedOperationsForUserToday(int $userId): array {
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');

        $sql = "SELECT * FROM " . (new static())->table . "
                WHERE user_id = :user_id
                AND balance_id IS NULL
                AND operation_time BETWEEN :today_start AND :today_end
                ORDER BY operation_time ASC";

        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':today_start', $today_start, PDO::PARAM_STR);
        $stmt->bindParam(':today_end', $today_end, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Mark a list of operations as closed by associating them with a balance entry.
     * @param array $operationIds An array of operation IDs to update.
     * @param int $balanceId The ID of the balance entry to link to.
     * @return bool True if all operations were updated successfully, false otherwise.
     */
    public static function markOperationsAsClosed(array $operationIds, int $balanceId): bool {
        if (empty($operationIds)) {
            return true; // Nothing to update
        }

        $placeholders = implode(',', array_fill(0, count($operationIds), '?'));

        $sql = "UPDATE " . (new static())->table . "
                SET balance_id = ?, updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $stmt = self::db()->prepare($sql);

        $params = array_merge([$balanceId], $operationIds);

        $success = $stmt->execute($params);
        return $success && $stmt->rowCount() === count($operationIds); // Ensure all specified operations were updated
    }

    /**
     * Build WHERE clause and parameters for criteria-based queries.
     * @param array $criteria Associative array of filters.
     * @param array &$params Reference to the array of parameters to bind.
     * @return string The WHERE SQL clause.
     */
    private static function buildCriteriaWhereClause(array $criteria, array &$params): string {
        $whereClauses = [];
        $allowedFilters = [
            'user_id' => 'o.user_id',
            'service_id' => 'o.service_id',
            'operation_type_id' => 'o.operation_type_id',
            'balance_id' => 'o.balance_id',
            // Special handling for date ranges
            'date_from' => 'o.operation_time >=',
            'date_to' => 'o.operation_time <=',
            // Add other potential filterable columns here
        ];

        foreach ($criteria as $key => $value) {
            if (isset($allowedFilters[$key]) && $value !== null) {
                $columnAndOperator = explode(' ', $allowedFilters[$key]);
                $column = $columnAndOperator[0];
                $operator = count($columnAndOperator) > 1 ? $columnAndOperator[1] : '=';

                $paramKey = ":crit_{$key}";
                $whereClauses[] = "{$column} {$operator} {$paramKey}";
                $params[$paramKey] = $value;
            }
        }
        return empty($whereClauses) ? "" : "WHERE " . implode(" AND ", $whereClauses);
    }

    /**
     * Calculate the sum of a specific column based on criteria.
     * @param array $criteria Associative array of filters.
     * @param string $sumColumn The column to sum (default 'amount').
     * @return float The sum.
     */
    public static function sumAmountByCriteria(array $criteria, string $sumColumn = 'amount'): float {
        if (!in_array($sumColumn, ['amount', 'commission_applied'])) {
            throw new \InvalidArgumentException("Cannot sum unauthorized column: $sumColumn");
        }

        $params = [];
        $whereClause = self::buildCriteriaWhereClause($criteria, $params);

        $sql = "SELECT SUM(o.{$sumColumn}) as total FROM " . (new static())->table . " o {$whereClause}";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total'] ?? 0.0);
    }

    /**
     * Count operations based on criteria.
     * @param array $criteria Associative array of filters.
     * @return int The count.
     */
    public static function countByCriteria(array $criteria): int {
        $params = [];
        $whereClause = self::buildCriteriaWhereClause($criteria, $params);

        $sql = "SELECT COUNT(*) as count FROM " . (new static())->table . " o {$whereClause}";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get statistics (count and sum of amounts) grouped by service, based on criteria.
     * @param array $criteria Associative array of filters.
     * @return array Array of ['service_id', 'service_name', 'operation_count', 'total_amount', 'total_commission']
     */
    public static function getStatsByService(array $criteria): array {
        $params = [];
        $baseTableAlias = 'o'; // Alias for the operations table in buildCriteriaWhereClause
        // Need to adjust buildCriteriaWhereClause if it hardcodes alias, or pass alias to it.
        // For now, assume buildCriteriaWhereClause works with 'o.' prefix.
        $whereClause = self::buildCriteriaWhereClause($criteria, $params);

        $sql = "SELECT s.id as service_id, s.name as service_name,
                       COUNT(o.id) as operation_count,
                       SUM(o.amount) as total_amount,
                       SUM(o.commission_applied) as total_commission
                FROM operations o
                JOIN services s ON o.service_id = s.id "
              . $whereClause .
               " GROUP BY s.id, s.name
                 ORDER BY s.name ASC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
