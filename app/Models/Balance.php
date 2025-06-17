<?php

namespace App\Models;

use PDO;
use DateTime;

class Balance extends Model {
    protected string $table = 'balances';
    public ?int $id = null;
    public int $user_id;
    public int $service_id;
    public string $balance_date; // YYYY-MM-DD
    public float $initial_balance;
    public ?float $final_balance = null;
    // public ?float $total_cashed_calculated = null; // Read-only, generated
    // public ?float $total_commission_calculated = null; // Needs to be calculated
    // public ?float $discrepancy = null; // Read-only, generated
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
        $stmt = self::db()->prepare("SELECT * FROM " . (new static())->table . " WHERE user_id = :user_id AND service_id = :service_id AND balance_date = :balance_date");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $stmt->bindParam(':balance_date', $dateString, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $balance = new Balance();
            foreach ($data as $key => $value) {
                if (property_exists($balance, $key)) {
                    // Handle type casting for float and bool
                    if (is_numeric($value) && strpos($key, '_balance') !== false || strpos($key, '_calculated') !== false) {
                        $balance->{$key} = (float)$value;
                    } elseif (is_bool($value) || ($key === 'is_closed' && ($value === 't' || $value === 'f'))) {
                         $balance->{$key} = (bool)($value === 't' || $value === true);
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
}
