<?php

namespace App\Models;

use PDO;
use DateTime;

class Balance extends Model {
    protected string $table = 'balances';
    public ?int $id = null;
    public int $user_id;
    public ?int $service_id = null; // Made nullable
    public string $balance_date; // YYYY-MM-DD
    public float $initial_amount; // Renamed from initial_balance
    public ?float $calculated_final_amount = null; // New field
    public ?float $actual_final_amount = null; // Renamed from final_balance
    public ?float $difference_amount = null; // New field (was discrepancy, now calculated by app)
    public ?float $total_commission_calculated = null; // Sum of commissions for the period
    public ?string $notes = null; // New field
    public bool $is_closed = false;
    public ?string $closed_at = null; // Timestamp
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Get the User (Gérant) associated with this balance.
     * @return User|null
     */
    public function user(): ?User {
        return User::find($this->user_id);
    }

    /**
     * Get the Service associated with this balance.
     * @return Service|null
     */
    public function service(): ?Service {
        return Service::find($this->service_id);
    }

    /**
     * Get all operations linked to this specific daily balance.
     * @return array
     */
    public function operations(): array {
        if (!$this->id) {
            // If balance ID is not set, try to fetch operations based on user, service, and date
            // This is useful for an open balance that doesn't have an ID yet, or for querying.
            return Operation::query(
                "SELECT * FROM operations WHERE user_id = :user_id AND service_id = :service_id AND DATE(operation_time) = :balance_date ORDER BY operation_time ASC",
                [
                    'user_id' => $this->user_id,
                    'service_id' => $this->service_id,
                    'balance_date' => $this->balance_date
                ]
            );
        }
        return Operation::query("SELECT * FROM operations WHERE balance_id = :balance_id ORDER BY operation_time ASC", ['balance_id' => $this->id]);
    }

    /**
     * Find a balance record by user, service, and date.
     * @param int $userId
     * @param int $serviceId
     * @param string $dateString (YYYY-MM-DD)
     * @return Balance|null
     */
    public static function findByUserDateService(int $userId, int $serviceId, string $dateString): ?Balance {
        // Ensure service_id is handled correctly if it can be NULL for global balances,
        // though this method implies a specific service.
        $sql = "SELECT * FROM " . (new static())->table . " WHERE user_id = :user_id AND service_id = :service_id AND balance_date = :balance_date";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT); // This method is for service-specific balances
        $stmt->bindParam(':balance_date', $dateString, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $balance = new Balance();
            foreach ($data as $key => $value) {
                if (property_exists($balance, $key)) {
                    // Adjust type casting for new/renamed properties
                    if (in_array($key, ['initial_amount', 'calculated_final_amount', 'actual_final_amount', 'difference_amount', 'total_commission_calculated'])) {
                        $balance->{$key} = ($value !== null) ? (float)$value : null;
                    } elseif ($key === 'is_closed') {
                         $balance->{$key} = (bool)($value === 't' || $value === true || $value === 1 || $value === '1');
                    } else {
                        $balance->{$key} = $value;
                    }
                }
            }
             $balance->id = (int)$data['id']; // Ensure ID is integer
            return $balance;
        }
        return null;
    }

    /**
     * Get open balances for a specific user.
     * @param int $userId
     * @return array
     */
    public static function getOpenBalancesForUser(int $userId): array {
        return self::query(
            "SELECT b.*, s.name as service_name FROM balances b JOIN services s ON b.service_id = s.id WHERE b.user_id = :user_id AND b.is_closed = FALSE ORDER BY b.balance_date ASC, s.name ASC",
            ['user_id' => $userId]
        );
    }

    /**
     * Get balances for a specific date for all users and services.
     * @param string $dateString (YYYY-MM-DD)
     * @return array
     */
    public static function getBalancesByDate(string $dateString): array {
        return self::query(
            "SELECT b.*, u.username as user_username, s.name as service_name
             FROM balances b
             JOIN users u ON b.user_id = u.id
             JOIN services s ON b.service_id = s.id
             WHERE b.balance_date = :balance_date
             ORDER BY u.username ASC, s.name ASC",
            ['balance_date' => $dateString]
        );
    }

    /**
     * Calculate total cashed and total commissions for this balance period.
     * This method should be called before closing the balance.
     * Note: This is a simplified calculation. Real-world scenarios might be more complex.
     */
    public function calculateTotals(): array {
        $operations = $this->operations();
        $totalCashed = 0;
        $totalCommissions = 0;

        foreach ($operations as $operation) {
            // Assuming 'Dépôt' and 'Transfert National' (as receiver) increase cash
            // Assuming 'Retrait' and 'Transfert National' (as sender) decrease cash
            // This logic needs to be very robust based on precise operation type effects
            $opType = OperationType::find($operation->operation_type_id);
            if ($opType) {
                 // Simplified: Dépôt increases, Retrait decreases. Other types might be neutral or specific.
                if (str_contains(strtolower($opType->name), 'dépôt')) {
                    $totalCashed += $operation->amount;
                } elseif (str_contains(strtolower($opType->name), 'retrait')) {
                    $totalCashed -= $operation->amount;
                }
                // Add more conditions for other operation types affecting cash flow
            }
            $totalCommissions += $operation->commission_applied;
        }

        // The `total_cashed_calculated` in the DB is (final_balance - initial_balance)
        // The logic here is for calculating based on operations.
        // The `final_balance` should ideally be `initial_balance + sum_of_deposits_etc - sum_of_withdrawals_etc`
        // `total_cashed_calculated` should be sum of operations that contribute to cash inflow for the agent.
        // `total_commission_calculated` is sum of commissions earned from operations.

        // For now, let's assume $totalCashed is the sum of cash movements based on operations.
        // The discrepancy calculation will use these.
        // $this->total_cashed_calculated = $totalCashed; // This is a generated column in DB
        $this->total_commission_calculated = $totalCommissions; // This needs to be stored

        return ['total_cashed_operations' => $totalCashed, 'total_commissions' => $totalCommissions];
    }


    /**
     * Close the balance for the day.
     * @param float $finalBalanceFromInput The final balance manually entered by the user.
     * @return bool
     */
    public function closeBalance(float $finalBalanceFromInput): bool {
        if ($this->is_closed) {
            return false; // Already closed
        }

        $calculatedTotals = $this->calculateTotals();
        $this->total_commission_calculated = $calculatedTotals['total_commissions'];
        // $this->total_cashed_calculated is generated, but we can compare with operations sum for internal checks

        $this->final_balance = $finalBalanceFromInput;
        $this->is_closed = true;
        $this->closed_at = (new DateTime())->format('Y-m-d H:i:s');

        // The discrepancy is now auto-calculated by the database if using generated columns.
        // If not, calculate it here:
        // $expectedFinalBalance = $this->initial_balance + $calculatedTotals['total_cashed_operations_affecting_balance'] - $this->total_commission_calculated;
        // $this->discrepancy = $this->final_balance - $expectedFinalBalance;

        return $this->save();
    }

    /**
     * Get the most current (latest by date, then by ID) balance record for a specific user.
     * @param int $userId The ID of the user.
     * @return Balance|null The Balance object or null if not found.
     */
    public static function getCurrentForUser(int $userId): ?Balance {
        $sql = "SELECT * FROM " . (new static())->table . "
                WHERE user_id = :user_id
                ORDER BY balance_date DESC, id DESC
                LIMIT 1";

        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $balance = new static(); // Use new static() for late static binding
            foreach ($data as $key => $value) {
                if (property_exists($balance, $key)) {
                    // Type casting logic for new/renamed properties
                    if (in_array($key, ['initial_amount', 'calculated_final_amount', 'actual_final_amount', 'difference_amount', 'total_commission_calculated'])) {
                        $balance->{$key} = ($value !== null) ? (float)$value : null;
                    } elseif ($key === 'is_closed') {
                        $balance->{$key} = (bool)($value === 't' || $value === true || $value === 1 || $value === '1');
                    } else {
                        $balance->{$key} = $value;
                    }
                }
            }
            $balance->id = (int)$data['id']; // Ensure ID is integer

            // Manually map generated columns if they are part of the class properties
            // and selected in the query (SELECT * includes them).
            // These are often handled by direct property access if the DB populates them.
            // Example: $balance->total_cashed_calculated = (float)($data['total_cashed_calculated'] ?? 0.0); // This was removed from schema
            //          $balance->discrepancy = (float)($data['discrepancy'] ?? 0.0); // This was removed from schema

            return $balance;
        }
        return null;
    }

    /**
     * Create a new balance entry.
     * @param array $data Associative array of balance data.
     * @return Balance|null The created Balance object or null on failure.
     */
    public static function createBalanceEntry(array $data): ?Balance {
        // Basic validation for required fields based on the new schema
        if (empty($data['user_id']) || empty($data['balance_date']) || !isset($data['initial_amount'])) {
            // calculated_final_amount, actual_final_amount, difference_amount might be set later or be nullable
            return null;
        }

        $db = self::db();
        // Note: service_id is nullable
        $sql = "INSERT INTO " . (new static())->table . "
                (user_id, service_id, balance_date, initial_amount, calculated_final_amount, actual_final_amount, difference_amount, total_commission_calculated, notes, is_closed, closed_at, created_at, updated_at)
                VALUES (:user_id, :service_id, :balance_date, :initial_amount, :calculated_final_amount, :actual_final_amount, :difference_amount, :total_commission_calculated, :notes, :is_closed, :closed_at, NOW(), NOW())";

        $stmt = $db->prepare($sql);

        $success = $stmt->execute([
            ':user_id' => $data['user_id'],
            ':service_id' => $data['service_id'] ?? null,
            ':balance_date' => $data['balance_date'],
            ':initial_amount' => $data['initial_amount'],
            ':calculated_final_amount' => $data['calculated_final_amount'] ?? null,
            ':actual_final_amount' => $data['actual_final_amount'] ?? null,
            ':difference_amount' => $data['difference_amount'] ?? null,
            ':total_commission_calculated' => $data['total_commission_calculated'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':is_closed' => $data['is_closed'] ?? false,
            ':closed_at' => $data['closed_at'] ?? null,
        ]);

        if ($success) {
            $id = $db->lastInsertId();
            // Use parent::find() to fetch the newly created record to ensure all fields (including defaults/timestamps) are populated
            $newBalance = parent::find((int)$id);
            return $newBalance instanceof Balance ? $newBalance : null;
        }
        return null;
    }

    /**
     * Build WHERE clause and parameters for criteria-based balance queries.
     * @param array $criteria Associative array of filters.
     * @param array &$params Reference to the array of parameters to bind.
     * @param string $tableAlias Alias for the balance table in the query.
     * @return string The WHERE SQL clause.
     */
    private static function buildBalanceCriteriaWhereClause(array $criteria, array &$params, string $tableAlias = 'b'): string {
        $whereClauses = [];
        $allowedFilters = [
            'user_id' => "{$tableAlias}.user_id",
            'service_id' => "{$tableAlias}.service_id", // Can be NULL for global balances
            'date_from' => "{$tableAlias}.balance_date >=",
            'date_to' => "{$tableAlias}.balance_date <=",
            'is_closed' => "{$tableAlias}.is_closed",
        ];

        foreach ($criteria as $key => $value) {
            if (isset($allowedFilters[$key])) {
                 if ($key === 'service_id' && $value === null) { // Specific handling for IS NULL
                    $whereClauses[] = "{$allowedFilters[$key]} IS NULL";
                } elseif ($value !== null) {
                    $columnAndOperator = explode(' ', $allowedFilters[$key]);
                    $column = $columnAndOperator[0];
                    $operator = count($columnAndOperator) > 1 ? $columnAndOperator[1] : '=';

                    $paramKey = ":crit_bal_{$key}"; // Unique param key prefix for balance criteria
                    $whereClauses[] = "{$column} {$operator} {$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
        }
        return empty($whereClauses) ? "" : "WHERE " . implode(" AND ", $whereClauses);
    }

    /**
     * Get balance evolution data points for a chart.
     * Returns daily actual_final_amount for closed balances within the criteria.
     * @param array $criteria Associative array of filters (user_id, date_from, date_to, service_id (optional)).
     * @return array Array of ['balance_date', 'amount'].
     */
    public static function getBalanceEvolution(array $criteria): array {
        $params = [];
        // Default to closed balances for evolution, as actual_final_amount is most reliable.
        // If service_id is not provided in criteria, it will fetch for all services or global (if service_id can be null).
        // To fetch specifically global balances, criteria should include 'service_id' => null.
        $criteria['is_closed'] = true;
        $whereClause = self::buildBalanceCriteriaWhereClause($criteria, $params);

        $sql = "SELECT b.balance_date, b.actual_final_amount as amount
                FROM " . (new static())->table . " b "
              . $whereClause .
               " ORDER BY b.balance_date ASC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
