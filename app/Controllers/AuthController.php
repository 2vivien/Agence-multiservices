<?php

namespace App\Controllers;

use App\Models\User;

class AuthController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Display login page (if we had one for a web interface)
     * For API, this might not be used directly.
     */
    public function showLoginForm(): void {
        // In a web app, this would render a login view.
        // For an API, clients would typically POST to /login.
        $this->jsonResponse(['message' => 'Please POST username and password to /login to authenticate.'], 200);
    }

    /**
     * Handle login attempt.
     */
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Invalid request method. Use POST.'], 405);
            return;
        }

        $input = $this->getJsonInput();
        $username = $input['username'] ?? $this->post('username');
        $password = $input['password'] ?? $this->post('password');

        if (empty($username) || empty($password)) {
            $this->jsonResponse(['error' => 'Username and password are required.'], 400);
            return;
        }

        $user = User::findByUsername($username);

        if ($user && $user->is_active && $user->verifyPassword($password)) {
            // Password verification successful (placeholder logic in User model for now)
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['user_role'] = $user->role; // Store role for authorization

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Audit Log: User Login
            // AuditLog::log($user->id, 'login', 'user', $user->id, ['status' => 'success']);


            $this->jsonResponse([
                'message' => 'Login successful.',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'role' => $user->role
                ],
                'session_id' => session_id() // For client-side session management if needed
            ], 200);
        } else {
             // Audit Log: Failed Login Attempt
            // AuditLog::log(null, 'login_failed', 'user', null, ['username' => $username, 'status' => 'failed']);
            $this->jsonResponse(['error' => 'Invalid username or password, or account inactive.'], 401);
        }
    }

    /**
     * Handle logout.
     */
    public function logout(): void {
        $userId = $this->getCurrentUserId();

        $_SESSION = []; // Unset all session variables
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // Audit Log: User Logout
        // if($userId) {
        //    AuditLog::log($userId, 'logout', 'user', $userId, ['status' => 'success']);
        // }

        $this->jsonResponse(['message' => 'Logout successful.'], 200);
    }

    /**
     * Check current authentication status.
     */
    public function status(): void {
        if ($this->isAuthenticated()) {
            $user = User::find($this->getCurrentUserId());
            if ($user) {
                 $this->jsonResponse([
                    'authenticated' => true,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'full_name' => $user->full_name,
                        'role' => $user->role
                    ]
                ], 200);
            } else {
                // Should not happen if session user_id is valid
                 $this->jsonResponse(['authenticated' => false, 'error' => 'User not found for session.'], 404);
            }
        } else {
            $this->jsonResponse(['authenticated' => false], 200);
        }
    }
}
