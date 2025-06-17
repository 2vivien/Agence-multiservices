<?php

namespace App\Controllers;

use App\Models\Operation;
use App\Models\Service;
use App\Models\OperationType;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OperationController extends Controller {

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
    }

    public function index(): void {
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();
        $operations = [];
        $page = (int)($this->get('page', 1));
        $limit = (int)($this->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $filters = [];
        if ($this->get('service_id')) $filters['service_id'] = (int)$this->get('service_id');
        if ($this->get('operation_type_id')) $filters['operation_type_id'] = (int)$this->get('operation_type_id');
        if ($this->get('date_from')) $filters['date_from'] = $this->get('date_from');
        if ($this->get('date_to')) $filters['date_to'] = $this->get('date_to');

        if ($userRole === 'admin') {
            if ($this->get('user_id')) $filters['user_id'] = (int)$this->get('user_id');
            $operations = Operation::findAllAdmin($filters, $limit, $offset);
        } elseif ($userRole === 'gerant') {
            $operations = Operation::findAllByUser($userId, $filters, $limit, $offset);
        } else {
            $this->jsonResponse(['error' => 'Unauthorized role.'], 403);
            return;
        }

        // This should ideally return an object with 'data' and 'pagination' keys
        // For now, Operation::findAllAdmin/ByUser return just the data array.
        // $totalRecords = Operation::countByCriteria($filters); // Assuming this method exists
        $this->jsonResponse($operations); // Or ['data' => $operations, 'pagination' => [...]]
    }

    public function store(): void {
        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
            return;
        }

        $errors = $this->validateOperationData($input);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
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
            'balance_id' => $input['balance_id'] ?? null,
        ];

        $operation = Operation::createOperation($data);
        if ($operation) {
            $this->jsonResponse($operation, 201);
        } else {
            if (isset($input['reference_id']) && Operation::query("SELECT id FROM operations WHERE reference_id = :ref_id", ['ref_id' => $input['reference_id']])) {
                 $this->jsonResponse(['errors' => ['reference_id' => 'Reference ID already exists.']], 409); // Conflict
                 return;
            }
            $this->jsonResponse(['error' => 'Failed to create operation. Check server logs.'], 500);
        }
    }

    public function show(int $id): void {
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

    public function update(int $id): void {
        $input = $this->getJsonInput();
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON input for update.'], 400);
            return;
        }

        $operation = Operation::findById($id);
        if (!$operation) {
            $this->jsonResponse(['error' => 'Operation not found to update.'], 404);
            return;
        }

        $userRole = $this->getCurrentUserRole();
        $currentUserId = $this->getCurrentUserId();

        if ($userRole !== 'admin' && $operation->user_id !== $currentUserId) {
            $this->jsonResponse(['error' => 'Forbidden. You do not own this resource or cannot update it.'], 403);
            return;
        }

        $errors = $this->validateOperationData($input, true, $operation->user_id);
        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }

        $updateData = [];
        $allowedUpdateFields = ['service_id', 'operation_type_id', 'amount', 'commission_applied', 'operation_time', 'description', 'reference_id', 'balance_id'];
        foreach ($allowedUpdateFields as $field) {
            if (array_key_exists($field, $input)) {
                 if (in_array($field, ['service_id', 'operation_type_id', 'balance_id']) && $input[$field] !== null) {
                    $updateData[$field] = (int)$input[$field];
                } elseif (in_array($field, ['amount', 'commission_applied']) && $input[$field] !== null) {
                    $updateData[$field] = (float)$input[$field];
                } else {
                    $updateData[$field] = $input[$field];
                }
            }
        }

        if (empty($updateData)) {
            $this->jsonResponse(['message' => 'No updatable fields provided.', 'data' => Operation::findById($id)], 200);
            return;
        }

        $success = Operation::updateOperation($id, $updateData);
        if ($success) {
            $updatedOperation = Operation::findById($id);
            $this->jsonResponse($updatedOperation);
        } else {
             if (isset($updateData['reference_id']) && $updateData['reference_id'] !== $operation->reference_id && Operation::query("SELECT id FROM operations WHERE reference_id = :ref_id", ['ref_id' => $updateData['reference_id']])) {
                 $this->jsonResponse(['errors' => ['reference_id' => 'Reference ID already exists.']], 409); // Conflict
                 return;
            }
            $this->jsonResponse(['error' => 'Failed to update operation. Check server logs.'], 500);
        }
    }

    public function destroy(int $id): void {
        $operation = Operation::findById($id);
        if (!$operation) {
            $this->jsonResponse(['error' => 'Operation not found to delete.'], 404);
            return;
        }

        $userRole = $this->getCurrentUserRole();
        $currentUserId = $this->getCurrentUserId();

        if ($userRole !== 'admin' && $operation->user_id !== $currentUserId) {
            $this->jsonResponse(['error' => 'Forbidden. You do not own this resource or cannot delete it.'], 403);
            return;
        }

        // Business logic: e.g., cannot delete if part of a closed balance
        if ($operation->balance_id !== null) {
            $balance = Balance::find($operation->balance_id);
            if ($balance && $balance->is_closed) {
                 $this->jsonResponse(['error' => 'Cannot delete operation linked to a closed balance.'], 403);
                 return;
            }
        }

        $deleted = Operation::deleteOperation($id);
        if ($deleted) {
            $this->jsonResponse(null, 204);
        } else {
            $this->jsonResponse(['error' => 'Failed to delete operation.'], 500);
        }
    }

    private function validateOperationData(array $data, bool $isUpdate = false, ?int $existingUserId = null): array {
        $errors = [];

        if (!empty($data['service_id'])) {
            if (!is_numeric($data['service_id']) || !Service::find((int)$data['service_id'])) {
                $errors['service_id'] = 'Invalid or non-existent Service ID.';
            }
        } elseif (!$isUpdate) {
            $errors['service_id'] = 'Service ID is required.';
        }

        if (!empty($data['operation_type_id'])) {
            if (!is_numeric($data['operation_type_id']) || !OperationType::find((int)$data['operation_type_id'])) {
                $errors['operation_type_id'] = 'Invalid or non-existent Operation Type ID.';
            }
        } elseif (!$isUpdate) {
            $errors['operation_type_id'] = 'Operation Type ID is required.';
        }

        if (isset($data['amount'])) {
            if (!is_numeric($data['amount']) || (float)$data['amount'] < 0) {
                $errors['amount'] = 'Amount must be a non-negative number.';
            }
        } elseif (!$isUpdate) {
            $errors['amount'] = 'Amount is required.';
        }

        if (isset($data['commission_applied']) && $data['commission_applied'] !== null) {
            if (!is_numeric($data['commission_applied']) || (float)$data['commission_applied'] < 0) {
                $errors['commission_applied'] = 'Commission must be a non-negative number.';
            }
        }

        if (!empty($data['operation_time'])) {
            $d = \DateTime::createFromFormat('Y-m-d H:i:s', $data['operation_time']);
            if (!$d || $d->format('Y-m-d H:i:s') !== $data['operation_time']) {
                $d = \DateTime::createFromFormat('Y-m-d', $data['operation_time']); // Allow date only
                 if (!$d || $d->format('Y-m-d') !== $data['operation_time']) {
                    $errors['operation_time'] = 'Operation time must be a valid datetime (YYYY-MM-DD HH:MM:SS or YYYY-MM-DD).';
                 }
            }
        }

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
            if ($isUpdate && $existingUserId && $this->getCurrentUserRole() === 'admin' && $data['user_id'] != $existingUserId) {
                // Potentially log this or add specific business rule checks if changing user_id is allowed for admin
            }
        }

        if (isset($data['balance_id']) && $data['balance_id'] !== null) {
            if (!is_numeric($data['balance_id'])) {
                 $errors['balance_id'] = 'Balance ID must be numeric if provided.';
            }
        }

        if (isset($data['description']) && !is_string($data['description'])) {
            $errors['description'] = 'Description must be a string.';
        }
        if (isset($data['reference_id']) && $data['reference_id'] !== null) {
             if(!is_string($data['reference_id']) && !is_numeric($data['reference_id'])){
                 $errors['reference_id'] = 'Reference ID must be a string or number.';
             }
             // Check uniqueness for reference_id, if it's meant to be unique
             // $op = Operation::query("SELECT id FROM operations WHERE reference_id = :ref_id", ['ref_id' => $data['reference_id']]);
             // if ($op && (!$isUpdate || $op[0]->id != $currentOperationIdBeingUpdated)) { $errors['reference_id'] = 'Reference ID already exists.';}
        }
        return $errors;
    }

    /**
     * Export operations list to PDF.
     */
    public function exportPDF(): void {
        // Add DomPDF use statements at the top of the file if not already there
        // use Dompdf\Dompdf;
        // use Dompdf\Options;

        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();
        $operations = [];

        $filters = []; // Collect filters from GET params
        if ($this->get('service_id')) $filters['service_id'] = (int)$this->get('service_id');
        if ($this->get('operation_type_id')) $filters['operation_type_id'] = (int)$this->get('operation_type_id');
        if ($this->get('date_from')) $filters['date_from'] = $this->get('date_from');
        if ($this->get('date_to')) $filters['date_to'] = $this->get('date_to');

        if ($userRole === 'admin') {
            if ($this->get('user_id')) $filters['user_id'] = (int)$this->get('user_id');
            $operations = Operation::findAllAdmin($filters, 10000, 0); // Fetch all relevant for export
        } elseif ($userRole === 'gerant') {
            $operations = Operation::findAllByUser($userId, $filters, 10000, 0);
        } else {
            http_response_code(403); echo "Forbidden"; exit;
        }

        if (empty($operations)) {
            http_response_code(404); echo "No operations found for the selected criteria."; exit;
        }

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
        $html .= "<style>body { font-family: DejaVu Sans, sans-serif; font-size: 10px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ccc; padding: 5px; text-align: left; } th { background-color: #eee; }</style>";
        $html .= "</head><body><h1>Liste des Opérations</h1>";
        // Display filters
        // ... (similar logic as before to display applied filters) ...

        $html .= "<table><thead><tr><th>Date</th><th>Description</th><th>Gérant</th><th>Service</th><th>Type</th><th>Montant</th><th>Commission</th></tr></thead><tbody>";
        foreach ($operations as $op) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($op->operation_time ?? '') . "</td>";
            $html .= "<td>" . htmlspecialchars($op->description ?? '') . "</td>";
            $html .= "<td>" . htmlspecialchars($op->user_username ?? User::find($op->user_id)->username ?? 'N/A') . "</td>";
            $html .= "<td>" . htmlspecialchars($op->service_name ?? Service::find($op->service_id)->name ?? 'N/A') . "</td>";
            $html .= "<td>" . htmlspecialchars($op->operation_type_name ?? OperationType::find($op->operation_type_id)->name ?? 'N/A') . "</td>";
            $html .= "<td style='text-align:right;'>" . number_format($op->amount ?? 0, 2, ',', ' ') . "</td>";
            $html .= "<td style='text-align:right;'>" . number_format($op->commission_applied ?? 0, 2, ',', ' ') . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table></body></html>";

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fileName = "operations_export_" . date('Y-m-d') . ".pdf";
        if (ob_get_level()) ob_end_clean();
        $dompdf->stream($fileName, ["Attachment" => true]);
        exit;
    }

    /**
     * Export operations list to Excel.
     */
    public function exportExcel(): void {
        // Add PhpSpreadsheet use statements at the top
        // use PhpOffice\PhpSpreadsheet\Spreadsheet;
        // use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();
        $operations = [];

        $filters = [];
        if ($this->get('service_id')) $filters['service_id'] = (int)$this->get('service_id');
        if ($this->get('operation_type_id')) $filters['operation_type_id'] = (int)$this->get('operation_type_id');
        if ($this->get('date_from')) $filters['date_from'] = $this->get('date_from');
        if ($this->get('date_to')) $filters['date_to'] = $this->get('date_to');

        if ($userRole === 'admin') {
            if ($this->get('user_id')) $filters['user_id'] = (int)$this->get('user_id');
            $operations = Operation::findAllAdmin($filters, 10000, 0);
        } elseif ($userRole === 'gerant') {
            $operations = Operation::findAllByUser($userId, $filters, 10000, 0);
        } else {
            http_response_code(403); echo "Forbidden"; exit;
        }

        if (empty($operations)) {
            http_response_code(404); echo "No operations found for the selected criteria to export."; exit;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); // Use FQCN if 'use' not at top
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Opérations');

        $headers = ['ID Opération', 'Date & Heure', 'Description', 'Gérant', 'Service', 'Type d\'Opération', 'Montant', 'Commission Appliquée', 'Référence ID', 'ID Clôture'];
        $sheet->fromArray($headers, NULL, 'A1');
        foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
            $sheet->getStyle($columnID . '1')->getFont()->setBold(true);
        }

        $rowNumber = 2;
        foreach ($operations as $op) {
            $sheet->setCellValue('A' . $rowNumber, $op->id);
            $sheet->setCellValue('B' . $rowNumber, $op->operation_time);
            $sheet->setCellValue('C' . $rowNumber, $op->description);
            $sheet->setCellValue('D' . $rowNumber, $op->user_username ?? User::find($op->user_id)->username ?? '');
            $sheet->setCellValue('E' . $rowNumber, $op->service_name ?? Service::find($op->service_id)->name ?? '');
            $sheet->setCellValue('F' . $rowNumber, $op->operation_type_name ?? OperationType::find($op->operation_type_id)->name ?? '');
            $sheet->setCellValue('G' . $rowNumber, $op->amount);
            $sheet->getStyle('G' . $rowNumber)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->setCellValue('H' . $rowNumber, $op->commission_applied);
            $sheet->getStyle('H' . $rowNumber)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->setCellValue('I' . $rowNumber, $op->reference_id);
            $sheet->setCellValue('J' . $rowNumber, $op->balance_id);
            $rowNumber++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet); // Use FQCN
        $fileName = "export_operations_" . date('Y-m-d_H-i-s') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        header('Cache-Control: max-age=0');
        if (ob_get_level()) ob_end_clean();
        $writer->save('php://output');
        exit;
    }
}
