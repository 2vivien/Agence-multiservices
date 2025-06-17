<?php

// API Routes
// This file should return an array of routes.
// The main index.php will include this file and process the routes.

use App\Controllers\AuthController;
use App\Controllers\DashboardController;

return [
    // Example route
    'GET /api/ping' => function() {
        header('Content-Type: application/json');
        echo json_encode(['message' => 'pong', 'timestamp' => date('Y-m-d H:i:s')]);
    },

    // Authentication routes
    'POST /api/login' => [AuthController::class, 'login'],
    'POST /api/logout' => [AuthController::class, 'logout'], // Or GET, POST is safer for state-changing actions
    'GET /api/auth/status' => [AuthController::class, 'status'],

    // Dashboard routes
    'GET /api/dashboard/gerant' => [DashboardController::class, 'getGerantDashboardData'],
    // Add other dashboard routes here, e.g., for admin:
    // 'GET /api/dashboard/admin' => [DashboardController::class, 'adminDashboard'], // Assuming adminDashboard method exists

    // Operation CRUD routes
    'GET /api/operations' => [\App\Controllers\OperationController::class, 'index'],
    'POST /api/operations' => [\App\Controllers\OperationController::class, 'store'],
    'GET /api/operations/{id}' => [\App\Controllers\OperationController::class, 'show'],
    'PUT /api/operations/{id}' => [\App\Controllers\OperationController::class, 'update'],
    'DELETE /api/operations/{id}' => [\App\Controllers\OperationController::class, 'destroy'],

    // Routes for fetching services and operation types (for forms, etc.)
    'GET /api/services' => [\App\Controllers\ServiceController::class, 'index'],
    'GET /api/operation-types' => [\App\Controllers\OperationTypeController::class, 'index'],

    // Cloture routes
    'GET /api/cloture/today' => [\App\Controllers\ClotureController::class, 'getClotureDataToday'],
    'POST /api/cloture/submit' => [\App\Controllers\ClotureController::class, 'submitCloture'],

    // Statistique routes
    'GET /api/stats/overall' => [\App\Controllers\StatistiqueController::class, 'getOverallStats'],
    'GET /api/stats/services' => [\App\Controllers\StatistiqueController::class, 'getServiceBasedStats'],
    'GET /api/stats/financial-summary' => [\App\Controllers\StatistiqueController::class, 'getFinancialSummary'],
    'GET /api/stats/user-activity' => [\App\Controllers\StatistiqueController::class, 'getUserActivityStats'],

    // Admin - User Management
    'GET /api/admin/users' => [\App\Controllers\UserController::class, 'index'],
    'POST /api/admin/users' => [\App\Controllers\UserController::class, 'store'],
    'GET /api/admin/users/{id}' => [\App\Controllers\UserController::class, 'show'],
    'PUT /api/admin/users/{id}' => [\App\Controllers\UserController::class, 'update'],
    'DELETE /api/admin/users/{id}' => [\App\Controllers\UserController::class, 'destroy'],

    // Admin - Service Management
    'GET /api/admin/services' => [\App\Controllers\ServiceController::class, 'adminIndex'], // Use adminIndex for all services
    'POST /api/admin/services' => [\App\Controllers\ServiceController::class, 'store'],
    'GET /api/admin/services/{id}' => [\App\Controllers\ServiceController::class, 'show'], // Admin specific show
    'PUT /api/admin/services/{id}' => [\App\Controllers\ServiceController::class, 'update'],
    'DELETE /api/admin/services/{id}' => [\App\Controllers\ServiceController::class, 'destroy'],

    // Additional API routes can be added below
];
