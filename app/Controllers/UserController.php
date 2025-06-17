<?php

namespace App\Controllers;

use App\Models\User;

class UserController extends Controller {

    public function __construct() {
        parent::__construct();
        // All methods in this controller are admin-only
        $this->requireAuth('admin');
    }

    /**
     * List all users with optional filters.
     * Filters: role, is_active
     * Access: Admin only.
     */
    public function index(): void {
        $filters = [];
        if ($this->get('role') !== null) {
            $filters['role'] = $this->get('role');
        }
        if ($this->get('is_active') !== null) {
            $filters['is_active'] = filter_var($this->get('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        // TODO: Implement pagination (limit, offset)
        // $users = User::getUsers($filters); // Assumes a getUsers method in User model

        $this->jsonResponse([
            'message' => 'User list (not fully implemented)',
            'filters_applied' => $filters,
            'data' => [] // Placeholder for users list
        ]);
    }

    /**
     * Store a newly created user.
     * Access: Admin only.
     */
    public function store(): void {
        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
            return;
        }

        // TODO: Comprehensive validation (username unique, email unique, password strong, role valid)
        $errors = $this->validateUserData($input);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }

        $userData = [
            'username' => $input['username'],
            'full_name' => $input['full_name'],
            'email' => $input['email'] ?? null, // Assuming email is optional or handled by User model
            'role' => $input['role'] ?? 'gerant', // Default role if not provided
            'is_active' => $input['is_active'] ?? true,
            // Password will be set using setPassword method in the model
        ];

        // $newUser = User::createUser($userData, $input['password']); // Model method needs to handle setPassword
        // if ($newUser) {
        //     $this->jsonResponse($newUser, 201);
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to create user.'], 500);
        // }
        // Ensure email is handled correctly if it's not part of $userData yet (depends on User model properties)
        if (array_key_exists('email', $input) && !array_key_exists('email', $userData) && property_exists(User::class, 'email')) {
            $userData['email'] = $input['email'];
        }

        $newUser = User::createUser($userData, $input['password']);
        if ($newUser) {
            // Unset password hash before sending response for security, even if it's not directly on $newUser object from createUser.
            // If $newUser is an array, unset $newUser['password_hash']. If object, ensure it's not serialized.
            // Best practice: User model's toArray() or similar method should omit sensitive fields.
            // For now, assume $newUser (if an object) doesn't directly expose password_hash or is handled by model's find.
            $this->jsonResponse($newUser, 201);
        } else {
            // Check for uniqueness constraint violations if not caught by validator (e.g. race conditions or validator missing a check)
            if (User::findByUsername($input['username'])) {
                 $this->jsonResponse(['errors' => ['username' => 'Username already exists.']], 409); // Conflict
                 return;
            }
            if (!empty($input['email']) && User::findByEmail($input['email'])) {
                 $this->jsonResponse(['errors' => ['email' => 'Email already exists.']], 409);
                 return;
            }
            $this->jsonResponse(['error' => 'Failed to create user. Check server logs.'], 500);
        }
    }

    /**
     * Display a specific user.
     * Access: Admin only.
     * @param int $id
     */
    public function show(int $id): void {
        // $user = User::find($id); // Assuming base Model has find()
        // if (!$user) {
        //     $this->jsonResponse(['error' => 'User not found.'], 404);
        //     return;
        // }
        // $this->jsonResponse($user);
        $this->jsonResponse(['message' => "User show ID: $id (not fully implemented)"]);
    }

    /**
     * Update an existing user.
     * Access: Admin only.
     * @param int $id
     */
    public function update(int $id): void {
        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input for update.'], 400);
            return;
        }

        // $user = User::find($id);
        // if (!$user) {
        //     $this->jsonResponse(['error' => 'User not found to update.'], 404);
        //     return;
        // }

        // TODO: Validate data (username unique if changed, email unique if changed, role valid)
        // $errors = $this->validateUserData($input, true, $id); // isUpdate=true, currentUserId=$id
        // if (!empty($errors)) {
        //     $this->jsonResponse(['errors' => $errors], 422);
        //     return;
        // }

        $updateData = [];
        if (isset($input['full_name'])) $updateData['full_name'] = $input['full_name'];
        if (isset($input['username'])) $updateData['username'] = $input['username']; // Must validate uniqueness if changed
        if (isset($input['email'])) $updateData['email'] = $input['email']; // Must validate uniqueness if changed
        if (isset($input['role'])) $updateData['role'] = $input['role']; // Must validate role value
        if (isset($input['is_active'])) $updateData['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);
        // Password change should be a separate, dedicated endpoint/method.

        // if (empty($updateData)) {
        //     $this->jsonResponse(['message' => 'No updatable fields provided.', 'data' => $user], 200);
        //     return;
        // }

        // $success = User::updateUser($id, $updateData);
        // if ($success) {
        //     $this->jsonResponse(User::find($id));
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to update user.'], 500);
        // }
        $user = User::find($id); // Assuming base Model has find()
        if (!$user) {
            $this->jsonResponse(['error' => 'User not found to update.'], 404);
            return;
        }

        $errors = $this->validateUserData($input, true, $id);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }

        $updateData = [];
        // Fields allowed for update (excluding password, which should have its own flow)
        $allowedUpdateFields = ['full_name', 'email', 'role', 'is_active', 'username'];
        foreach ($allowedUpdateFields as $field) {
            if (array_key_exists($field, $input)) { // Use array_key_exists for boolean false values
                 if ($field === 'is_active') {
                    $updateData[$field] = filter_var($input[$field], FILTER_VALIDATE_BOOLEAN);
                } else {
                    $updateData[$field] = $input[$field];
                }
            }
        }

        if (empty($updateData)) {
            $this->jsonResponse(['message' => 'No updatable fields provided.', 'data' => $user], 200); // Or 304 Not Modified
            return;
        }

        $success = User::updateUser($id, $updateData);
        if ($success) {
            $updatedUser = User::find($id);
            // Similar to store, ensure password_hash is not exposed.
            $this->jsonResponse($updatedUser);
        } else {
            // Check for uniqueness constraint violations if not caught by validator
             if (isset($updateData['username']) && User::findByUsername($updateData['username']) && User::findByUsername($updateData['username'])->id !== $id) {
                 $this->jsonResponse(['errors' => ['username' => 'Username already exists.']], 409);
                 return;
            }
            if (isset($updateData['email']) && !empty($updateData['email']) && User::findByEmail($updateData['email']) && User::findByEmail($updateData['email'])->id !== $id) {
                 $this->jsonResponse(['errors' => ['email' => 'Email already exists.']], 409);
                 return;
            }
            $this->jsonResponse(['error' => 'Failed to update user. Check server logs.'], 500);
        }
    }

    /**
     * Delete a user (or mark as inactive).
     * Access: Admin only.
     * @param int $id
     */
    public function destroy(int $id): void {
        // $user = User::find($id);
        // if (!$user) {
        //     $this->jsonResponse(['error' => 'User not found to delete.'], 404);
        //     return;
        // }

        // Cannot delete currently logged-in admin (self-deletion prevention)
        // if ($id === $this->getCurrentUserId()) {
        //     $this->jsonResponse(['error' => 'Cannot delete currently logged-in user.'], 403);
        //     return;
        // }

        // $success = User::deleteUser($id); // Or $user->markAsInactive();
        // if ($success) {
        //     $this->jsonResponse(null, 204); // No Content
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to delete user.'], 500);
        // }
        $user = User::find($id);
        if (!$user) {
            $this->jsonResponse(['error' => 'User not found to delete.'], 404);
            return;
        }

        // Prevent self-deletion
        if ($id === $this->getCurrentUserId()) {
            $this->jsonResponse(['error' => 'Cannot delete your own user account.'], 403);
            return;
        }

        // Add any other business logic before deletion (e.g., cannot delete last admin)

        // $success = User::deleteUser($id); // Hard delete
        $success = User::deactivateUser($id); // Soft delete by marking inactive
        if ($success) {
            // Return 200 with the updated user data (now inactive) or 204
            $updatedUser = User::find($id); // Fetch to show updated state
            $this->jsonResponse($updatedUser ?? ['message' => 'User deactivated successfully, but could not be refetched immediately.'], 200);
        } else {
            $this->jsonResponse(['error' => 'Failed to deactivate user.'], 500);
        }
    }

    /**
     * Validates user data for store and update actions.
     * @param array $data The input data.
     * @param bool $isUpdate Whether this is an update operation.
     * @param ?int $currentUserId For updates, the ID of the user being updated (to ignore self in uniqueness checks).
     * @return array Array of errors, empty if valid.
     */
    private function validateUserData(array $data, bool $isUpdate = false, ?int $currentUserId = null): array {
        $errors = [];

        // Username: required, alphanumeric, min_length, unique
        if (!empty($data['username'])) {
            if (!ctype_alnum($data['username'])) {
                $errors['username'] = 'Username must be alphanumeric.';
            }
            if (strlen($data['username']) < 3) {
                $errors['username'] = 'Username must be at least 3 characters.';
            }
            // Check uniqueness
            $existingUserByUsername = User::findByUsername($data['username']);
            if ($existingUserByUsername && (!$isUpdate || $existingUserByUsername->id !== $currentUserId)) {
                $errors['username'] = 'Username already taken.';
            }
        } elseif (!$isUpdate) {
            $errors['username'] = 'Username is required.';
        }

        // Full Name: required, string
        if (!empty($data['full_name'])) {
            if (!is_string($data['full_name']) || strlen($data['full_name']) < 3) {
                $errors['full_name'] = 'Full name must be a string of at least 3 characters.';
            }
        } elseif (!$isUpdate) {
            $errors['full_name'] = 'Full name is required.';
        }

        // Email: optional, valid email format, unique
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format.';
            } else {
                // Check uniqueness
                $existingUserByEmail = User::findByEmail($data['email']);
                if ($existingUserByEmail && (!$isUpdate || $existingUserByEmail->id !== $currentUserId)) {
                    $errors['email'] = 'Email already taken.';
                }
            }
        }

        // Password: required for create, strong
        if (!$isUpdate) { // Password required only on create via this method
            if (empty($data['password']) || strlen($data['password']) < 6) {
                $errors['password'] = 'Password is required and must be at least 6 characters.';
            }
            // Add more password strength rules if desired (uppercase, number, symbol)
        }

        // Role: required, must be a valid role (e.g., 'admin', 'gerant')
        if (!empty($data['role'])) {
            if (!in_array($data['role'], ['admin', 'gerant'])) {
                $errors['role'] = 'Invalid role specified.';
            }
        } elseif (!$isUpdate) {
            $errors['role'] = 'Role is required.';
        }

        // is_active: boolean
        if (isset($data['is_active']) && !is_bool(filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
            $errors['is_active'] = 'Invalid value for is_active status.';
        }

        return $errors;
    }
}
