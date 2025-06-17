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
     * Provides data for the Gérant (Manager) Dashboard.
     * This method checks if the user is authenticated and has the 'gerant' role.
     * It then fetches and returns relevant dashboard data.
     */
    public function getGerantDashboardData(): void {
        // requireAuth() is called in constructor. Now check role.
        if ($this->getCurrentUserRole() !== 'gerant') {
            $this->jsonResponse(['error' => 'Forbidden. User is not a manager.'], 403);
            return;
        }

        $userId = $this->getCurrentUserId();
        $user = User::find($userId); // User should exist if role is gerant and authenticated

        if (!$user) {
            // This case should ideally not be reached if session management is robust
            $this->jsonResponse(['error' => 'Authenticated user not found.'], 500);
            return;
        }

        // TODO: Replace with actual model calls once methods are implemented in Subtask 2
        // For now, using placeholders or assuming methods might exist but could fail if not yet implemented.
        // $openBalances = method_exists(Balance::class, 'getOpenBalancesForUser') ? Balance::getOpenBalancesForUser($userId) : []; // Keep if needed for other purposes
        $currentBalance = method_exists(Balance::class, 'getCurrentForUser') ? Balance::getCurrentForUser($userId) : null;
        $recentOperations = method_exists(Operation::class, 'getRecentForUser') ? Operation::getRecentForUser($userId, 5) : [];
        $dailySummary = method_exists(Operation::class, 'getSummaryForUserToday') ? Operation::getSummaryForUserToday($userId) : ['count' => 0, 'total_amount' => 0];
        $unresolvedAlerts = method_exists(Alert::class, 'getUnresolvedAlerts') ? Alert::getUnresolvedAlerts(null, $userId) : [];


        $this->jsonResponse([
            'message' => "Welcome to your dashboard, {$user->full_name}!",
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role
            ],
            // 'open_balances' => $openBalances, // Send this if the dashboard page needs the full list of open balances
            'current_balance' => $currentBalance, // Single, most recent balance object
            'recent_operations' => $recentOperations,
            'daily_summary' => $dailySummary,
            'unresolved_alerts' => $unresolvedAlerts,
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
