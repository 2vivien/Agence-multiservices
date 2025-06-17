<?php

namespace App\Controllers;

use App\Models\Operation;
// use App\Models\Service; // For validation
// use App\Models\OperationType; // For validation
use App\Models\User; // For validating user_id

class OperationController extends Controller {

    public function __construct() {
        parent::__construct();
        // Most actions here will require authentication. Specific role checks will be per method.
        $this->requireAuth();
    }

    /**
     * Display a listing of operations.
     * Access: Gérant (own operations), Admin (all operations with filters).
     */
    public function index(): void {
        // TODO: Implement role-based data fetching
        // TODO: Implement pagination and filtering

        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();
        $operations = [];
        $page = (int)($this->get('page', 1));
        $limit = (int)($this->get('limit', 10)); // Default limit to 10, can be overridden by query param
        $offset = ($page - 1) * $limit;

        // Basic filters from query parameters
        $filters = [];
        if ($this->get('service_id')) $filters['service_id'] = (int)$this->get('service_id');
        if ($this->get('operation_type_id')) $filters['operation_type_id'] = (int)$this->get('operation_type_id');
        if ($this->get('date_from')) $filters['date_from'] = $this->get('date_from'); // Expects YYYY-MM-DD
        if ($this->get('date_to')) $filters['date_to'] = $this->get('date_to');     // Expects YYYY-MM-DD

        if ($userRole === 'admin') {
            if ($this->get('user_id')) $filters['user_id'] = (int)$this->get('user_id'); // Admin can filter by user
            $operations = Operation::findAllAdmin($filters, $limit, $offset);
        } elseif ($userRole === 'gerant') {
            $operations = Operation::findAllByUser($userId, $filters, $limit, $offset);
        } else {
            $this->jsonResponse(['error' => 'Unauthorized role.'], 403);
            return;
        }

        // TODO: Add total count for pagination metadata if desired
        $this->jsonResponse($operations);
    }

    /**
     * Store a newly created operation in storage.
     * Access: Gérant (for self), Admin (potentially for any user if specified).
     */
    public function store(): void {
use App\Models\Service; // For validation
use App\Models\OperationType; // For validation

class OperationController extends Controller {

    public function __construct() {
        parent::__construct();
        // Most actions here will require authentication. Specific role checks will be per method.
        $this->requireAuth();
    }

    /**
     * Display a listing of operations.
     * Access: Gérant (own operations), Admin (all operations with filters).
     */
    public function index(): void {
        // TODO: Implement role-based data fetching
        // TODO: Implement pagination and filtering

        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        if ($userRole === 'admin') {
            // Admin: Fetch all operations, potentially with query params for filtering
            // $filters = $this->get(); // Example: ?user_id=X&service_id=Y
            // $operations = Operation::findAllAdmin($filters);
            $this->jsonResponse(['message' => "Admin: Fetch all operations (not implemented yet)", 'filters' => $this->get()]);
        } elseif ($userRole === 'gerant') {
            // Gerant: Fetch own operations
            // $operations = Operation::findAllByUser($userId);
            $this->jsonResponse(['message' => "Gerant: Fetch own operations (not implemented yet) for user_id: $userId"]);
        } else {
            $this->jsonResponse(['error' => 'Unauthorized role.'], 403);
            return;
        }
        // $this->jsonResponse($operations);
    }

    /**
     * Store a newly created operation in storage.
     * Access: Gérant (for self), Admin (potentially for any user if specified).
     */
    public function store(): void {
        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
            return;
        }

        $errors = $this->validateOperationData($input);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422); // Unprocessable Entity
            return;
        }

        $userId = $this->getCurrentUserId();
        $userRole = $this->getCurrentUserRole();
        $operationUserId = $userId;

        if ($userRole === 'admin' && isset($input['user_id'])) {
            if (!is_numeric($input['user_id']) || !User::find((int)$input['user_id'])) {
                $this->jsonResponse(['errors' => ['user_id' => 'Specified user_id is invalid or user does not exist.']], 422);
                return;
            }
            $operationUserId = (int)$input['user_id'];
        } elseif ($userRole !== 'gerant' && !($userRole === 'admin' && !isset($input['user_id']))) {
            // If not gerant, and not admin creating for self (user_id not set in input)
            $this->jsonResponse(['error' => 'Forbidden: Insufficient permissions to create operation for specified user.'], 403);
            return;
        }

        $data = [
            'user_id' => $operationUserId,
            'service_id' => (int)$input['service_id'],
            'operation_type_id' => (int)$input['operation_type_id'],
            'amount' => (float)$input['amount'],
            'commission_applied' => (float)($input['commission_applied'] ?? 0.0),
            'operation_time' => $input['operation_time'] ?? date('Y-m-d H:i:s'),
            'description' => $input['description'] ?? null,
            'reference_id' => $input['reference_id'] ?? null,
            'balance_id' => $input['balance_id'] ?? null, // Optional, might be set later
        ];

        $operation = Operation::createOperation($data);
        if ($operation) {
            $this->jsonResponse($operation, 201); // 201 Created
        } else {
            $this->jsonResponse(['error' => 'Failed to create operation.'], 500);
        }
    }

    /**
     * Display the specified operation.
     * Access: Gérant (own operation), Admin (any operation).
     * @param int $id
     */
    public function show(int $id): void {
        // $operation = Operation::findById($id);
        // if (!$operation) {
        //     $this->jsonResponse(['error' => 'Operation not found.'], 404);
        //     return;
        // }

        // $userRole = $this->getCurrentUserRole();
        // $userId = $this->getCurrentUserId();

        // if ($userRole !== 'admin' && $operation->user_id !== $userId) {
        //     $this->jsonResponse(['error' => 'Forbidden. You do not own this resource.'], 403);
        //     return;
        // }
        // $this->jsonResponse($operation);
        $operation = Operation::findById($id);
        if (!$operation) {
            $this->jsonResponse(['error' => 'Operation not found.'], 404);
            return;
        }

        $userRole = $this->getCurrentUserRole();
        $currentUserId = $this->getCurrentUserId();

        if ($userRole !== 'admin' && $operation->user_id !== $currentUserId) {
            $this->jsonResponse(['error' => 'Forbidden. You do not own this resource.'], 403);
            return;
        }
        $this->jsonResponse($operation);
    }

    /**
     * Update the specified operation in storage.
     * Access: Gérant (own operation, limited fields/conditions), Admin (any operation).
     * @param int $id
     */
    public function update(int $id): void {
        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
            return;
        }

        $operation = Operation::findById($id);
        if (!$operation) {
            $this->jsonResponse(['error' => 'Operation not found.'], 404);
            return;
        }

        $userRole = $this->getCurrentUserRole();
        $currentUserId = $this->getCurrentUserId();

        if ($userRole !== 'admin' && $operation->user_id !== $currentUserId) {
            $this->jsonResponse(['error' => 'Forbidden. You do not own this resource or cannot update it.'], 403);
            return;
        }

        // Validate only the fields that are present in the input
        $errors = $this->validateOperationData($input, true, $operation->user_id); // isUpdate=true
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }

        // Prepare data for update, only including fields that are allowed to be updated
        $updateData = [];
        foreach (['service_id', 'operation_type_id', 'amount', 'commission_applied', 'operation_time', 'description', 'reference_id', 'balance_id'] as $key) {
            if (isset($input[$key])) {
                // Apply specific type casting for safety
                if (in_array($key, ['service_id', 'operation_type_id', 'balance_id']) && $input[$key] !== null) {
                    $updateData[$key] = (int)$input[$key];
                } elseif (in_array($key, ['amount', 'commission_applied']) && $input[$key] !== null) {
                    $updateData[$key] = (float)$input[$key];
                } else {
                    $updateData[$key] = $input[$key];
                }
            }
        }
        // user_id cannot be changed by gérant. Admin might, but that logic should be explicit if needed.
        // if ($userRole === 'admin' && isset($input['user_id']) && $input['user_id'] != $operation->user_id) {
        //    // Handle change of user_id by admin if allowed by business rules
        // }


        if (empty($updateData)) {
             $this->jsonResponse(['message' => 'No updatable fields provided.', 'data' => Operation::findById($id)], 200);
             return;
        }

        $updated = Operation::updateOperation($id, $updateData);
        if ($updated) {
            $this->jsonResponse(Operation::findById($id)); // Return updated resource
        } else {
            $this->jsonResponse(['error' => 'Failed to update operation.'], 500);
        }
    }

    /**
     * Remove the specified operation from storage.
     * Access: Gérant (own operation, specific conditions), Admin (any operation).
     * @param int $id
     */
    public function destroy(int $id): void {
        // TODO: Fetch operation by ID (Operation::findById($id))
        // TODO: Check ownership if user is 'gerant'
        // TODO: Check if operation can be deleted (e.g., not too old, not part of a closed balance)
        // TODO: Call Operation::deleteOperation($id)

        // $operation = Operation::findById($id);
        // if (!$operation) {
        //     $this->jsonResponse(['error' => 'Operation not found.'], 404);
        //     return;
        // }

        // $userRole = $this->getCurrentUserRole();
        // $userId = $this->getCurrentUserId();

        // if ($userRole !== 'admin' && $operation->user_id !== $userId) {
        //     $this->jsonResponse(['error' => 'Forbidden. You do not own this resource or cannot delete it.'], 403);
        //     return;
        // }
        // Additional business logic for deletion constraints (e.g., cannot delete if balance closed)

        // $deleted = Operation::deleteOperation($id);
        // if ($deleted) {
        //     $this->jsonResponse(null, 204); // 204 No Content
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to delete operation.'], 500);
        // }
        $operation = Operation::findById($id);
        if (!$operation) {
            $this->jsonResponse(['error' => 'Operation not found.'], 404);
            return;
        }

        $userRole = $this->getCurrentUserRole();
        $currentUserId = $this->getCurrentUserId();

        if ($userRole !== 'admin' && $operation->user_id !== $currentUserId) {
            $this->jsonResponse(['error' => 'Forbidden. You do not own this resource or cannot delete it.'], 403);
            return;
        }

        // TODO: Add any additional business logic for deletion constraints
        // (e.g., cannot delete if balance is closed and this operation is part of it).

        $deleted = Operation::deleteOperation($id);
        if ($deleted) {
            $this->jsonResponse(null, 204); // 204 No Content
        } else {
            $this->jsonResponse(['error' => 'Failed to delete operation.'], 500);
        }
    }

    /**
     * Validates operation data for store and update actions.
     * @param array $data The input data.
     * @param bool $isUpdate Whether this is an update operation ( relaxes some 'required' rules).
     * @param ?int $existingUserId The user_id of the operation being updated (for ownership checks if user_id is in $data).
     * @return array Array of errors, empty if valid.
     */
    private function validateOperationData(array $data, bool $isUpdate = false, ?int $existingUserId = null): array {
        $errors = [];

        // service_id: required, must exist
        if (!empty($data['service_id'])) {
            if (!is_numeric($data['service_id']) || !Service::find((int)$data['service_id'])) {
                $errors['service_id'] = 'Invalid or non-existent Service ID.';
            }
        } elseif (!$isUpdate) {
            $errors['service_id'] = 'Service ID is required.';
        }

        // operation_type_id: required, must exist
        if (!empty($data['operation_type_id'])) {
            if (!is_numeric($data['operation_type_id']) || !OperationType::find((int)$data['operation_type_id'])) {
                $errors['operation_type_id'] = 'Invalid or non-existent Operation Type ID.';
            }
        } elseif (!$isUpdate) {
            $errors['operation_type_id'] = 'Operation Type ID is required.';
        }

        // amount: required, numeric, positive
        if (isset($data['amount'])) { // Allow 0 amount if it's a valid scenario
            if (!is_numeric($data['amount']) || (float)$data['amount'] < 0) {
                $errors['amount'] = 'Amount must be a non-negative number.';
            }
        } elseif (!$isUpdate) {
            $errors['amount'] = 'Amount is required.';
        }

        // commission_applied: optional, numeric, non-negative
        if (isset($data['commission_applied']) && $data['commission_applied'] !== null) {
            if (!is_numeric($data['commission_applied']) || (float)$data['commission_applied'] < 0) {
                $errors['commission_applied'] = 'Commission must be a non-negative number.';
            }
        }

        // operation_time: optional (defaults to now), but if provided, must be valid datetime
        if (!empty($data['operation_time'])) {
            $d = \DateTime::createFromFormat('Y-m-d H:i:s', $data['operation_time']);
            if (!$d || $d->format('Y-m-d H:i:s') !== $data['operation_time']) {
                $d = \DateTime::createFromFormat('Y-m-d', $data['operation_time']);
                 if (!$d || $d->format('Y-m-d') !== $data['operation_time']) {
                    $errors['operation_time'] = 'Operation time must be a valid datetime (YYYY-MM-DD HH:MM:SS or YYYY-MM-DD).';
                 }
            }
        }

        // user_id (relevant if admin is setting it, or for ownership checks on update)
        if (isset($data['user_id'])) {
            if (!is_numeric($data['user_id'])) {
                $errors['user_id'] = 'User ID must be numeric.';
            } elseif ($this->getCurrentUserRole() === 'admin') {
                 if (!User::find((int)$data['user_id'])) {
                     $errors['user_id'] = 'Specified User ID does not exist.';
                 }
            } elseif ($this->getCurrentUserRole() === 'gerant' && (int)$data['user_id'] !== $this->getCurrentUserId()) {
                 $errors['user_id'] = 'Gérant cannot assign operation to another user.';
            }
            // If isUpdate and user_id is being changed by admin
            if ($isUpdate && $existingUserId && $this->getCurrentUserRole() === 'admin' && $data['user_id'] != $existingUserId) {
                // Potentially log this or add specific business rule checks
            }
        }

        // balance_id: optional, numeric if present
        if (isset($data['balance_id']) && $data['balance_id'] !== null) {
            if (!is_numeric($data['balance_id'])) {
                 $errors['balance_id'] = 'Balance ID must be numeric if provided.';
            }
            // Could also check if Balance::find((int)$data['balance_id']) exists
        }

        // description, reference_id: usually string, max length checks could be added
        if (isset($data['description']) && !is_string($data['description'])) {
            $errors['description'] = 'Description must be a string.';
        }
        if (isset($data['reference_id']) && !is_string($data['reference_id'])) {
            $errors['reference_id'] = 'Reference ID must be a string.';
        }

        return $errors;
    }
}
