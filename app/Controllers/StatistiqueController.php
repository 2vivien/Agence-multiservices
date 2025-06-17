<?php

namespace App\Controllers;

use App\Models\Operation;
use App\Models\User;
use App\Models\Service;
// use App\Models\Balance; // If balance evolution stats are needed

class StatistiqueController extends Controller {

    public function __construct() {
        parent::__construct();
        $this->requireAuth(); // All stat methods require authentication
    }

    /**
     * Get overall system statistics.
     * Access: Admin only.
     */
    public function getOverallStats(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        // TODO: Fetch data from models
        // $totalOperations = Operation::countByCriteria([]); // Example
        // $totalTurnover = Operation::sumAmountByCriteria([]); // Example
        // $activeGerants = User::countActiveUsersByRole('gerant'); // Example
        // $activeServices = Service::count(['is_active' => true]); // Example, if Model has generic count
        $dateFrom = $this->get('date_from');
        $dateTo = $this->get('date_to');

        $criteria = [];
        if ($dateFrom) $criteria['date_from'] = $dateFrom . " 00:00:00";
        if ($dateTo) $criteria['date_to'] = $dateTo . " 23:59:59";

        // Validate dates
        if (($dateFrom && !$this->isValidDate($dateFrom)) || ($dateTo && !$this->isValidDate($dateTo))) {
            $this->jsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
            return;
        }

        $totalOperations = Operation::countByCriteria($criteria);
        $totalTurnover = Operation::sumAmountByCriteria($criteria, 'amount');
        $activeGerants = User::countActiveUsersByRole('gerant');
        $activeServicesModels = Service::getActiveServices(); // Returns array of Service objects
        $activeServicesCount = count($activeServicesModels);


        $this->jsonResponse([
            'message' => 'Overall stats retrieved successfully.',
            'data' => [
                'total_operations' => $totalOperations,
                'total_turnover' => $totalTurnover,
                'active_gerants' => $activeGerants,
                'active_services' => $activeServicesCount,
            ]
        ]);
    }

    /**
     * Get statistics grouped by service.
     * Access: Admin (all services), Gérant (own activity per service).
     */
    public function getServiceBasedStats(): void {
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
            'service_id' => $this->get('service_id') ? (int)$this->get('service_id') : null,
        ];
        $filters = array_filter($filters, fn($value) => $value !== null || is_int($value)); // Allow 0 for IDs if valid

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format. Use YYYY-MM-DD.'], 400);
            return;
        }
         if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format. Use YYYY-MM-DD.'], 400);
            return;
        }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";


        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
            // Allow admin to filter by a specific gérant for this stat view
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        $serviceStats = Operation::getStatsByService($criteria);

        $this->jsonResponse([
            'message' => 'Service-based stats retrieved successfully.',
            'role' => $userRole,
            'criteria_applied' => $criteria,
            'data' => $serviceStats
        ]);
    }

    /**
     * Get financial summary (deposits, withdrawals, commissions) over a period.
     * Access: Admin (all), Gérant (own activity).
     */
    public function getFinancialSummary(): void {
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format. Use YYYY-MM-DD.'], 400);
            return;
        }
         if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format. Use YYYY-MM-DD.'], 400);
            return;
        }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        // These need more specific criteria, e.g. based on operation_type names or a category field
        // For simplicity, we sum all 'amount' for deposit/withdrawal placeholders
        // This requires OperationType model to have a way to categorize types, e.g. 'deposit', 'withdrawal'
        // $depositTypes = OperationType::getIdsByCategory('deposit'); // Hypothetical
        // $withdrawalTypes = OperationType::getIdsByCategory('withdrawal'); // Hypothetical

        // $totalDeposits = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id_in' => $depositTypes]));
        // $totalWithdrawals = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id_in' => $withdrawalTypes]));
        // For placeholder:
        $totalDeposits = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 1])); // Assuming ID 1 is 'Dépôt'
        $totalWithdrawals = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 2])); // Assuming ID 2 is 'Retrait'
        $totalCommissionsEarned = Operation::sumAmountByCriteria($criteria, 'commission_applied');


        $this->jsonResponse([
            'message' => 'Financial summary retrieved successfully.',
            'role' => $userRole,
            'criteria_applied' => $criteria,
            'data' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'total_commissions_earned' => $totalCommissionsEarned,
            ]
        ]);
    }

    /**
     * Get user activity statistics (for gérants).
     * Access: Admin only.
     */
    public function getUserActivityStats(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format. Use YYYY-MM-DD.'], 400);
            return;
        }
         if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format. Use YYYY-MM-DD.'], 400);
            return;
        }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        // If a specific user_id is provided in query, filter for that user.
        $specificUserId = $this->get('user_id') ? (int)$this->get('user_id') : null;

        $gerants = User::query("SELECT id, full_name FROM users WHERE role = 'gerant' AND is_active = TRUE" . ($specificUserId ? " AND id = :specific_user_id" : ""), ($specificUserId ? ['specific_user_id' => $specificUserId] : []));

        $userActivity = [];
        foreach ($gerants as $gerant) {
            $criteria = array_merge($filters, ['user_id' => $gerant->id]);
            $opCount = Operation::countByCriteria($criteria);
            // last_activity_date would require querying operations table for MAX(operation_time)
            $lastOpStmt = Operation::db()->prepare("SELECT MAX(operation_time) as last_op FROM operations WHERE user_id = :user_id" .
                                                (isset($filters['date_from']) ? " AND operation_time >= :date_from" : "") .
                                                (isset($filters['date_to']) ? " AND operation_time <= :date_to" : ""));
            $lastOpParams = ['user_id' => $gerant->id];
            if(isset($filters['date_from'])) $lastOpParams['date_from'] = $filters['date_from'];
            if(isset($filters['date_to'])) $lastOpParams['date_to'] = $filters['date_to'];

            $lastOpStmt->execute($lastOpParams);
            $lastOpResult = $lastOpStmt->fetch(\PDO::FETCH_ASSOC);

            $userActivity[] = [
                'user_id' => $gerant->id,
                'user_full_name' => $gerant->full_name,
                'total_ops' => $opCount,
                'last_activity_date' => $lastOpResult['last_op'] ?? null
            ];
        }

        $this->jsonResponse([
            'message' => 'User activity stats retrieved successfully.',
            'criteria_applied' => array_merge($filters, $specificUserId ? ['user_id_filter' => $specificUserId] : []),
            'data' => $userActivity
        ]);
    }

    private function isValidDate(string $dateString, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $dateString);
        return $d && $d->format($format) === $dateString;
    }
}
