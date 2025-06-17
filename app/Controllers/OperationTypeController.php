<?php

namespace App\Controllers;

use App\Models\OperationType;

class OperationTypeController extends Controller {

    public function __construct() {
        parent::__construct();
        // Listing operation types might be public or require basic auth.
        // For now, let's assume any authenticated user can list them for forms.
        $this->requireAuth();
    }

    /**
     * Display a listing of all active operation types.
     */
    public function index(): void {
        // Fetch active operation types, suitable for populating dropdowns
        $operationTypes = OperationType::getActiveOperationTypes();

        if ($operationTypes === null) {
            // This case implies an issue with the query or DB connection in the model.
            $this->jsonResponse(['error' => 'Could not retrieve operation types.'], 500);
            return;
        }

        $this->jsonResponse($operationTypes);
    }
}
