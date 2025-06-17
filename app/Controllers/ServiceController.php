<?php

namespace App\Controllers;

use App\Models\Service;

class ServiceController extends Controller {

    public function __construct() {
        parent::__construct();
        // Listing services might be public or require basic auth, adjust as needed.
        // For now, let's assume any authenticated user can list them for forms.
        $this->requireAuth();
    }

    /**
     * Display a listing of all active services.
     */
    public function index(): void {
        // Fetch active services, suitable for populating dropdowns in forms
        $services = Service::getActiveServices();

        if ($services === null) {
            // This case implies an issue with the query or DB connection in the model.
            $this->jsonResponse(['error' => 'Could not retrieve services.'], 500);
            return;
        }

        $this->jsonResponse($services);
    }

    /**
     * Display a listing of all services for admin (active and inactive).
     * Access: Admin only.
     */
    public function adminIndex(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }
        // $services = Service::getServices([]);
        $filters = [];
        if ($this->get('is_active') !== null) {
            $filters['is_active'] = filter_var($this->get('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $page = (int)($this->get('page', 1));
        $limit = (int)($this->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $result = Service::getServices($filters, $limit, $offset);

        $this->jsonResponse([
            'message' => 'Admin service list retrieved successfully.',
            'filters_applied' => $filters,
            'data' => $result['data'],
            'pagination' => [
                'total_records' => $result['total'],
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => ceil($result['total'] / $limit)
            ]
        ]);
    }

    /**
     * Store a newly created service.
     * Access: Admin only.
     */
    public function store(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
            return;
        }

        // TODO: Validation (name unique, description, default_commission_rate, is_active)
        // $errors = $this->validateServiceData($input);
        // if (!empty($errors)) {
        //     $this->jsonResponse(['errors' => $errors], 422);
        //     return;
        // }

        // $serviceData = [
        //     'name' => $input['name'],
        //     'description' => $input['description'] ?? null,
        //     'default_commission_rate' => $input['default_commission_rate'] ?? 0.0,
        //     'is_active' => $input['is_active'] ?? true,
        // ];
        // $newService = Service::createService($serviceData);
        // if ($newService) {
        //     $this->jsonResponse($newService, 201);
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to create service.'], 500);
        // }
        $errors = $this->validateServiceData($input);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }

        $serviceData = [
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'default_commission_rate' => isset($input['default_commission_rate']) ? (float)$input['default_commission_rate'] : null,
            'is_active' => $input['is_active'] ?? true,
        ];
        $newService = Service::createService($serviceData);
        if ($newService) {
            $this->jsonResponse($newService, 201);
        } else {
            // Check if it failed due to name uniqueness, if findByName was used in createService
            if (Service::findByName($input['name'])) {
                 $this->jsonResponse(['errors' => ['name' => 'Service name already exists.']], 409); // 409 Conflict
                 return;
            }
            $this->jsonResponse(['error' => 'Failed to create service.'], 500);
        }
    }

    /**
     * Display a specific service (for admin).
     * Access: Admin only.
     * @param int $id
     */
    public function show(int $id): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }
        // $service = Service::find($id); // Assuming base Model has find()
        // if (!$service) {
        //     $this->jsonResponse(['error' => 'Service not found.'], 404);
        //     return;
        // }
        // $this->jsonResponse($service);
        $this->jsonResponse(['message' => "Service show ID: $id (not fully implemented)"]);
    }

    /**
     * Update an existing service.
     * Access: Admin only.
     * @param int $id
     */
    public function update(int $id): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input for update.'], 400);
            return;
        }

        // $service = Service::find($id);
        // if (!$service) {
        //     $this->jsonResponse(['error' => 'Service not found to update.'], 404);
        //     return;
        // }

        // TODO: Validation (name unique if changed, etc.)
        // $errors = $this->validateServiceData($input, true, $id);
        // if (!empty($errors)) {
        //     $this->jsonResponse(['errors' => $errors], 422);
        //     return;
        // }

        // $updateData = [];
        // if (isset($input['name'])) $updateData['name'] = $input['name'];
        // if (isset($input['description'])) $updateData['description'] = $input['description'];
        // if (isset($input['default_commission_rate'])) $updateData['default_commission_rate'] = (float)$input['default_commission_rate'];
        // if (isset($input['is_active'])) $updateData['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);

        // if (empty($updateData)) {
        //     $this->jsonResponse(['message' => 'No updatable fields provided.', 'data' => $service], 200);
        //     return;
        // }

        // $success = Service::updateService($id, $updateData);
        // if ($success) {
        //     $this->jsonResponse(Service::find($id));
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to update service.'], 500);
        // }
        $service = Service::find($id); // Assuming base Model has find()
        if (!$service) {
            $this->jsonResponse(['error' => 'Service not found to update.'], 404);
            return;
        }

        $errors = $this->validateServiceData($input, true, $id);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }

        $updateData = [];
        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['description'])) $updateData['description'] = $input['description'];
        if (isset($input['default_commission_rate'])) $updateData['default_commission_rate'] = ($input['default_commission_rate'] !== null) ? (float)$input['default_commission_rate'] : null;
        if (isset($input['is_active'])) $updateData['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);

        if (empty($updateData)) {
            $this->jsonResponse(['message' => 'No updatable fields provided.', 'data' => $service], 200);
            return;
        }

        $success = Service::updateService($id, $updateData);
        if ($success) {
            $this->jsonResponse(Service::find($id));
        } else {
            // Check for unique constraint violation on name if name was part of updateData
            if (isset($updateData['name']) && Service::findByName($updateData['name']) && Service::findByName($updateData['name'])->id !== $id) {
                 $this->jsonResponse(['errors' => ['name' => 'Service name already exists.']], 409);
                 return;
            }
            $this->jsonResponse(['error' => 'Failed to update service.'], 500);
        }
    }

    /**
     * Delete a service (or mark as inactive).
     * Access: Admin only.
     * @param int $id
     */
    public function destroy(int $id): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        // $service = Service::find($id);
        // if (!$service) {
        //     $this->jsonResponse(['error' => 'Service not found to delete.'], 404);
        //     return;
        // }

        // Consider implications: operations linked to this service. Deactivation is safer.
        // $success = Service::deleteService($id); // Or $service->markAsInactive();
        // if ($success) {
        //     $this->jsonResponse(null, 204); // No Content
        // } else {
        //     $this->jsonResponse(['error' => 'Failed to delete service.'], 500);
        // }
        $service = Service::find($id);
        if (!$service) {
            $this->jsonResponse(['error' => 'Service not found to delete.'], 404);
            return;
        }
        // Basic check: if service has operations, prevent hard delete, suggest deactivation.
        // This requires Service model to have an operations relation count or similar check.
        // if ($service->operationsCount() > 0) {
        //     $this->jsonResponse(['error' => 'Cannot delete service with existing operations. Consider deactivating it instead.'], 409); // Conflict
        //     return;
        // }

        // $success = Service::deleteService($id); // Hard delete
        $success = Service::deactivateService($id); // Soft delete
        if ($success) {
             $updatedService = Service::find($id); // Fetch to show updated state
            $this->jsonResponse($updatedService ?? ['message' => 'Service deactivated successfully, but could not be refetched.'], 200);
        } else {
            $this->jsonResponse(['error' => 'Failed to deactivate service.'], 500);
        }
    }

    private function validateServiceData(array $data, bool $isUpdate = false, ?int $currentId = null): array {
        $errors = [];

        // Name: required, string, unique
        if (!empty($data['name'])) {
            if (!is_string($data['name']) || strlen($data['name']) < 2) {
                $errors['name'] = 'Service name must be a string of at least 2 characters.';
            }
            $existingService = Service::findByName($data['name']);
            if ($existingService && (!$isUpdate || $existingService->id !== $currentId)) {
                $errors['name'] = 'Service name already exists.';
            }
        } elseif (!$isUpdate) {
            $errors['name'] = 'Service name is required.';
        }

        // Description: optional, string
        if (isset($data['description']) && !is_string($data['description'])) {
            $errors['description'] = 'Description must be a string.';
        }

        // default_commission_rate: optional, numeric, non-negative
        if (isset($data['default_commission_rate']) && $data['default_commission_rate'] !== null) {
            if (!is_numeric($data['default_commission_rate']) || (float)$data['default_commission_rate'] < 0) {
                $errors['default_commission_rate'] = 'Default commission rate must be a non-negative number.';
            }
        }

        // is_active: boolean
        if (isset($data['is_active']) && !is_bool(filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
            $errors['is_active'] = 'Invalid value for is_active status.';
        }
        return $errors;
    }
}
