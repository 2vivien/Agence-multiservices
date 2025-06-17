<?php

namespace App\Controllers;

abstract class Controller {

    public function __construct() {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Basic CSRF protection for state-changing methods
        if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                // Allow if Content-Type is application/json, as this is also a common indicator of non-form AJAX
                // This is a weaker check but common for APIs that might not always send X-Requested-With
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (stripos($contentType, 'application/json') === false) {
                    $this->jsonResponse(['error' => 'Invalid request. Possible CSRF attempt or non-AJAX request.'], 403);
                    exit;
                }
            }
        }
    }

    /**
     * Render a view file.
     * For this project, views are minimal. This is a basic helper.
     *
     * @param string $viewName The name of the view file (e.g., 'dashboard/index')
     * @param array $data Data to pass to the view
     */
    protected function view(string $viewName, array $data = []): void {
        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $viewName) . '.php';

        if (file_exists($viewPath)) {
            extract($data); // Make $data keys available as variables in the view
            require $viewPath;
        } else {
            // Handle view not found, perhaps throw an exception or show a generic error
            $this->jsonResponse(['error' => "View not found: {$viewName}"], 404);
        }
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to be encoded as JSON
     * @param int $statusCode HTTP status code
     */
    protected function jsonResponse(mixed $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit; // Terminate script execution after sending JSON response
    }

    /**
     * Redirect to a given URL.
     *
     * @param string $url
     */
    protected function redirect(string $url): void {
        header("Location: {$url}");
        exit;
    }

    /**
     * Get input from POST request.
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function post(string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Get input from GET request.
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function get(string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Get JSON input from request body.
     * @return array|null
     */
    protected function getJsonInput(): ?array {
        $input = file_get_contents('php://input');
        if ($input) {
            return json_decode($input, true);
        }
        return null;
    }

    /**
     * Check if user is authenticated.
     * @return bool
     */
    protected function isAuthenticated(): bool {
        return isset($_SESSION['user_id']);
    }

    /**
     * Require authentication for a controller action.
     * Redirects to login if not authenticated.
     * Can also check for specific roles.
     *
     * @param array|string|null $requiredRoles Role(s) required to access.
     *                                         Null means any authenticated user.
     *                                         String for a single role.
     *                                         Array for multiple possible roles.
     */
    protected function requireAuth(array|string|null $requiredRoles = null): void {
        if (!$this->isAuthenticated()) {
            // Store the intended URL
            // $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            $this->jsonResponse(['error' => 'Authentication required.'], 401);
            // Or redirect to a login page for web context:
            // $this->redirect('/login.php'); // Assuming a login route/script
        }

        if ($requiredRoles !== null) {
            $userRole = $_SESSION['user_role'] ?? null;
            if (is_string($requiredRoles) && $userRole !== $requiredRoles) {
                $this->jsonResponse(['error' => 'Forbidden. Insufficient permissions.'], 403);
            } elseif (is_array($requiredRoles) && !in_array($userRole, $requiredRoles)) {
                $this->jsonResponse(['error' => 'Forbidden. Insufficient permissions.'], 403);
            }
        }
    }

    /**
     * Get current authenticated user's ID.
     * @return int|null
     */
    protected function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current authenticated user's role.
     * @return string|null
     */
    protected function getCurrentUserRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }
}
