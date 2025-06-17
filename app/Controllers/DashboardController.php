<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Operation;
use App\Models\Balance;
use App\Models\Alert;
use App\Models\Service;

class DashboardController extends Controller {

    public function __construct() {
        parent::__construct();
        $this->requireAuth(); // All dashboard actions require authentication
    }

    /**
     * Data for the Gérant (Manager) Dashboard.
     */
    public function gerantDashboard(): void {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['error' => 'User not authenticated.'], 401);
            return;
        }

        $user = User::find($userId);
        if (!$user || $user->role !== 'gerant') {
            // Allow admin to view gerant dashboard if a user_id is passed as a param for impersonation/view
            $paramUserId = (int)$this->get('user_id');
            if ($this->getCurrentUserRole() === 'admin' && $paramUserId > 0) {
                $user = User::find($paramUserId);
                if (!$user || $user->role !== 'gerant') {
                    $this->jsonResponse(['error' => 'Specified user is not a gerant or does not exist.'], 403);
                    return;
                }
                $userId = $paramUserId; // Viewing as this gerant
            } else {
                $this->jsonResponse(['error' => 'Access denied or user is not a gerant.'], 403);
                return;
            }
        }

        $openBalances = Balance::getOpenBalancesForUser($userId);
        $recentOperations = Operation::getRecentOperations(5, $userId);
        $unresolvedAlerts = Alert::getUnresolvedAlerts(null, $userId); // Assuming getUnresolvedAlerts can be filtered by user

        // You might want to add more specific stats here later, e.g., today's total operations/commissions

        $this->jsonResponse([
            'message' => "Welcome to your dashboard, {$user->full_name}!",
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role
            ],
            'open_balances' => $openBalances,
            'recent_operations' => $recentOperations,
            'unresolved_alerts' => $unresolvedAlerts,
            // 'daily_summary' => [
            //    'total_operations_today' => 0, // Placeholder
            //    'total_cashed_today' => 0.0, // Placeholder
            //    'total_commissions_today' => 0.0 // Placeholder
            // ]
        ], 200);
    }

    /**
     * Data for the Admin Dashboard.
     */
    public function adminDashboard(): void {
        $this->requireAuth('admin'); // Only admins can access this

        $totalGerants = count(User::getActiveGerants());
        $totalServices = count(Service::getActiveServices());
        $recentOperationsAll = Operation::getRecentOperations(10); // Recent operations across all users
        $systemAlerts = Alert::getUnresolvedAlerts(); // All unresolved system alerts

        // Aggregate stats - these would be more complex queries in a real app
        // For example, total operations today, total commissions today across all gérants
        // $today = date('Y-m-d');
        // $globalStats = Operation::getGlobalStatsForDate($today);

        $this->jsonResponse([
            'message' => "Admin Dashboard Overview",
            'stats_summary' => [
                'active_gerants' => $totalGerants,
                'active_services' => $totalServices,
                // 'total_operations_today' => $globalStats['total_operations'] ?? 0,
                // 'total_commissions_today' => $globalStats['total_commissions'] ?? 0.0,
            ],
            'recent_operations_all_users' => $recentOperationsAll,
            'system_alerts' => $systemAlerts,
            // Potentially add links or summaries for managing users, services, etc.
        ], 200);
    }
}
