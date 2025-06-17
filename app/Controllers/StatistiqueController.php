<?php

namespace App\Controllers;

use App\Models\Operation;
use App\Models\User;
use App\Models\Service;
// use App\Models\Balance; // If balance evolution stats are needed
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

class StatistiqueController extends Controller {

    public function __construct() {
        parent::__construct();
        $this->requireAuth(); // All stat methods require authentication
    }

    /**
     * Get overall system statistics.
     * Access: Admin only.
     */
    public function getOverallStats(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        // TODO: Fetch data from models
        // $totalOperations = Operation::countByCriteria([]); // Example
        // $totalTurnover = Operation::sumAmountByCriteria([]); // Example
        // $activeGerants = User::countActiveUsersByRole('gerant'); // Example
        // $activeServices = Service::count(['is_active' => true]); // Example, if Model has generic count
        $dateFrom = $this->get('date_from');
        $dateTo = $this->get('date_to');

        $criteria = [];
        if ($dateFrom) $criteria['date_from'] = $dateFrom . " 00:00:00";
        if ($dateTo) $criteria['date_to'] = $dateTo . " 23:59:59";

        // Validate dates
        if (($dateFrom && !$this->isValidDate($dateFrom)) || ($dateTo && !$this->isValidDate($dateTo))) {
            $this->jsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
            return;
        }

        $totalOperations = Operation::countByCriteria($criteria);
        $totalTurnover = Operation::sumAmountByCriteria($criteria, 'amount');
        $activeGerants = User::countActiveUsersByRole('gerant');
        $activeServicesModels = Service::getActiveServices(); // Returns array of Service objects
        $activeServicesCount = count($activeServicesModels);


        $this->jsonResponse([
            'message' => 'Overall stats retrieved successfully.',
            'data' => [
                'total_operations' => $totalOperations,
                'total_turnover' => $totalTurnover,
                'active_gerants' => $activeGerants,
                'active_services' => $activeServicesCount,
            ]
        ]);
    }

    /**
     * Get statistics grouped by service.
     * Access: Admin (all services), Gérant (own activity per service).
     */
    public function getServiceBasedStats(): void {
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
            'service_id' => $this->get('service_id') ? (int)$this->get('service_id') : null,
        ];
        $filters = array_filter($filters, fn($value) => $value !== null || is_int($value)); // Allow 0 for IDs if valid

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format. Use YYYY-MM-DD.'], 400);
            return;
        }
         if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format. Use YYYY-MM-DD.'], 400);
            return;
        }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";


        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
            // Allow admin to filter by a specific gérant for this stat view
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        $serviceStats = Operation::getStatsByService($criteria);

        $this->jsonResponse([
            'message' => 'Service-based stats retrieved successfully.',
            'role' => $userRole,
            'criteria_applied' => $criteria,
            'data' => $serviceStats
        ]);
    }

    /**
     * Get financial summary (deposits, withdrawals, commissions) over a period.
     * Access: Admin (all), Gérant (own activity).
     */
    public function getFinancialSummary(): void {
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format. Use YYYY-MM-DD.'], 400);
            return;
        }
         if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format. Use YYYY-MM-DD.'], 400);
            return;
        }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        // These need more specific criteria, e.g. based on operation_type names or a category field
        // For simplicity, we sum all 'amount' for deposit/withdrawal placeholders
        // This requires OperationType model to have a way to categorize types, e.g. 'deposit', 'withdrawal'
        // $depositTypes = OperationType::getIdsByCategory('deposit'); // Hypothetical
        // $withdrawalTypes = OperationType::getIdsByCategory('withdrawal'); // Hypothetical

        // $totalDeposits = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id_in' => $depositTypes]));
        // $totalWithdrawals = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id_in' => $withdrawalTypes]));
        // For placeholder:
        $totalDeposits = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 1])); // Assuming ID 1 is 'Dépôt'
        $totalWithdrawals = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 2])); // Assuming ID 2 is 'Retrait'
        $totalCommissionsEarned = Operation::sumAmountByCriteria($criteria, 'commission_applied');


        $this->jsonResponse([
            'message' => 'Financial summary retrieved successfully.',
            'role' => $userRole,
            'criteria_applied' => $criteria,
            'data' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'total_commissions_earned' => $totalCommissionsEarned,
            ]
        ]);
    }

    /**
     * Get user activity statistics (for gérants).
     * Access: Admin only.
     */
    public function getUserActivityStats(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format. Use YYYY-MM-DD.'], 400);
            return;
        }
         if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format. Use YYYY-MM-DD.'], 400);
            return;
        }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        // If a specific user_id is provided in query, filter for that user.
        $specificUserId = $this->get('user_id') ? (int)$this->get('user_id') : null;

        $gerants = User::query("SELECT id, full_name FROM users WHERE role = 'gerant' AND is_active = TRUE" . ($specificUserId ? " AND id = :specific_user_id" : ""), ($specificUserId ? ['specific_user_id' => $specificUserId] : []));

        $userActivity = [];
        foreach ($gerants as $gerant) {
            $criteria = array_merge($filters, ['user_id' => $gerant->id]);
            $opCount = Operation::countByCriteria($criteria);
            // last_activity_date would require querying operations table for MAX(operation_time)
            $lastOpStmt = Operation::db()->prepare("SELECT MAX(operation_time) as last_op FROM operations WHERE user_id = :user_id" .
                                                (isset($filters['date_from']) ? " AND operation_time >= :date_from" : "") .
                                                (isset($filters['date_to']) ? " AND operation_time <= :date_to" : ""));
            $lastOpParams = ['user_id' => $gerant->id];
            if(isset($filters['date_from'])) $lastOpParams['date_from'] = $filters['date_from'];
            if(isset($filters['date_to'])) $lastOpParams['date_to'] = $filters['date_to'];

            $lastOpStmt->execute($lastOpParams);
            $lastOpResult = $lastOpStmt->fetch(\PDO::FETCH_ASSOC);

            $userActivity[] = [
                'user_id' => $gerant->id,
                'user_full_name' => $gerant->full_name,
                'total_ops' => $opCount,
                'last_activity_date' => $lastOpResult['last_op'] ?? null
            ];
        }

        $this->jsonResponse([
            'message' => 'User activity stats retrieved successfully.',
            'criteria_applied' => array_merge($filters, $specificUserId ? ['user_id_filter' => $specificUserId] : []),
            'data' => $userActivity
        ]);
    }

    private function isValidDate(string $dateString, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $dateString);
        return $d && $d->format($format) === $dateString;
    }

    /**
     * Export service-based statistics to PDF.
     * Placeholder for future implementation.
     */
    public function exportServiceStatsPDF(): void {
        // TODO: Fetch data (similar to getServiceBasedStats)
        // TODO: Generate PDF
        // $this->jsonResponse(['message' => 'Service Stats PDF export endpoint (not implemented yet)']);
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
            'service_id' => $this->get('service_id') ? (int)$this->get('service_id') : null,
        ];
        $filters = array_filter($filters, fn($value) => $value !== null || is_int($value));

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
             $this->jsonResponse(['error' => 'Invalid date_from format.'],400); return; }
        if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
             $this->jsonResponse(['error' => 'Invalid date_to format.'],400); return; }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        $serviceStats = Operation::getStatsByService($criteria);

        if (empty($serviceStats)) {
            http_response_code(404);
            echo "Aucune statistique par service trouvée pour les critères sélectionnés.";
            exit;
        }

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
        $html .= "<style>body { font-family: DejaVu Sans, sans-serif; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";
        $html .= "</head><body><h1>Statistiques par Service</h1>";
        // Add filter information to PDF
        $html .= "<p>Filtres appliqués: ";
        $filterParts = [];
        if (!empty($criteria['date_from'])) $filterParts[] = "Du ".date('d/m/Y', strtotime($criteria['date_from']));
        if (!empty($criteria['date_to'])) $filterParts[] = "Au ".date('d/m/Y', strtotime($criteria['date_to']));
        if (!empty($criteria['user_id'])) {
            $gerant = User::find($criteria['user_id']);
            if ($gerant) $filterParts[] = "Gérant: ".$gerant->full_name;
        }
        if (!empty($criteria['service_id'])) {
            $service = Service::find($criteria['service_id']);
            if ($service) $filterParts[] = "Service: ".$service->name;
        }
        $html .= count($filterParts) > 0 ? implode(', ', $filterParts) : "Aucun";
        $html .= "</p>";

        $html .= "<table><thead><tr><th>ID Service</th><th>Nom du Service</th><th>Nombre d'Opérations</th><th>Montant Total</th><th>Commissions Totales</th></tr></thead><tbody>";

        foreach ($serviceStats as $stat) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($stat['service_id']) . "</td>";
            $html .= "<td>" . htmlspecialchars($stat['service_name']) . "</td>";
            $html .= "<td>" . htmlspecialchars($stat['operation_count']) . "</td>";
            $html .= "<td>" . htmlspecialchars(number_format($stat['total_amount'] ?? 0, 2, ',', ' ')) . " FCFA</td>";
            $html .= "<td>" . htmlspecialchars(number_format($stat['total_commission'] ?? 0, 2, ',', ' ')) . " FCFA</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table></body></html>";

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait'); // Landscape might be better if many services
        $dompdf->render();

        $fileName = "statistiques_services_" . date('Y-m-d') . ".pdf";
        $dompdf->stream($fileName, ["Attachment" => true]);
        exit;
    }

    /**
     * Export service-based statistics to Excel.
     * Placeholder for future implementation.
     */
    public function exportServiceStatsExcel(): void {
        // TODO: Fetch data
        // TODO: Generate Excel
        // $this->jsonResponse(['message' => 'Service Stats Excel export endpoint (not implemented yet)']);
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
            'service_id' => $this->get('service_id') ? (int)$this->get('service_id') : null,
        ];
        $filters = array_filter($filters, fn($value) => $value !== null || is_int($value));

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
             $this->jsonResponse(['error' => 'Invalid date_from format.'],400); return; }
        if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
             $this->jsonResponse(['error' => 'Invalid date_to format.'],400); return; }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        $serviceStats = Operation::getStatsByService($criteria);

        if (empty($serviceStats)) {
            http_response_code(404);
            echo "No service statistics found for the selected criteria to export.";
            exit;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Statistiques par Service');

        $sheet->setCellValue('A1', 'ID Service');
        $sheet->setCellValue('B1', 'Nom du Service');
        $sheet->setCellValue('C1', 'Nombre d\'Opérations');
        $sheet->setCellValue('D1', 'Montant Total');
        $sheet->setCellValue('E1', 'Commissions Totales');

        foreach (range('A', 'E') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $rowNumber = 2;
        foreach ($serviceStats as $stat) {
            $sheet->setCellValue('A' . $rowNumber, $stat['service_id']);
            $sheet->setCellValue('B' . $rowNumber, $stat['service_name']);
            $sheet->setCellValue('C' . $rowNumber, $stat['operation_count']);
            $sheet->setCellValue('D' . $rowNumber, $stat['total_amount']);
            $sheet->getStyle('D' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue('E' . $rowNumber, $stat['total_commission']);
            $sheet->getStyle('E' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
            $rowNumber++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = "export_stats_services_" . date('Y-m-d_H-i-s') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Export financial summary to PDF.
     * Placeholder for future implementation.
     */
    public function exportFinancialSummaryPDF(): void {
        // TODO: Fetch data (similar to getFinancialSummary)
        // TODO: Generate PDF
        // $this->jsonResponse(['message' => 'Financial Summary PDF export endpoint (not implemented yet)']);
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format.'],400); return; }
        if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format.'],400); return; }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        // Placeholder logic for fetching data, similar to getFinancialSummary
        $totalDeposits = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 1])); // Assuming ID 1 is 'Dépôt'
        $totalWithdrawals = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 2])); // Assuming ID 2 is 'Retrait'
        $totalCommissionsEarned = Operation::sumAmountByCriteria($criteria, 'commission_applied');

        if ($totalDeposits == 0 && $totalWithdrawals == 0 && $totalCommissionsEarned == 0) {
             http_response_code(404);
             echo "Aucune donnée pour le résumé financier pour les critères sélectionnés.";
             exit;
        }

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
        $html .= "<style>body { font-family: DejaVu Sans, sans-serif; } table { width: 50%; border-collapse: collapse; margin-top: 20px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";
        $html .= "</head><body><h1>Résumé Financier</h1>";
        $html .= "<p>Filtres appliqués: ";
        $filterParts = [];
        if (!empty($criteria['date_from'])) $filterParts[] = "Du ".date('d/m/Y', strtotime($criteria['date_from']));
        if (!empty($criteria['date_to'])) $filterParts[] = "Au ".date('d/m/Y', strtotime($criteria['date_to']));
        if (!empty($criteria['user_id'])) {
            $user = User::find($criteria['user_id']);
            if ($user) $filterParts[] = "Utilisateur: ".$user->full_name;
        }
        $html .= count($filterParts) > 0 ? implode(', ', $filterParts) : "Aucun";
        $html .= "</p>";

        $html .= "<table>";
        $html .= "<tr><td>Total Dépôts:</td><td>" . htmlspecialchars(number_format($totalDeposits, 2, ',', ' ')) . " FCFA</td></tr>";
        $html .= "<tr><td>Total Retraits:</td><td>" . htmlspecialchars(number_format($totalWithdrawals, 2, ',', ' ')) . " FCFA</td></tr>";
        $html .= "<tr><td>Total Commissions Gagnées:</td><td>" . htmlspecialchars(number_format($totalCommissionsEarned, 2, ',', ' ')) . " FCFA</td></tr>";
        $html .= "</table></body></html>";

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = "resume_financier_" . date('Y-m-d') . ".pdf";
        $dompdf->stream($fileName, ["Attachment" => true]);
        exit;
    }

    /**
     * Export financial summary to Excel.
     * Placeholder for future implementation.
     */
    public function exportFinancialSummaryExcel(): void {
        // TODO: Fetch data
        // TODO: Generate Excel
        // $this->jsonResponse(['message' => 'Financial Summary Excel export endpoint (not implemented yet)']);
        $userRole = $this->getCurrentUserRole();
        $userId = $this->getCurrentUserId();

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format.'],400); return; }
        if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format.'],400); return; }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $criteria = $filters;
        if ($userRole === 'gerant') {
            $criteria['user_id'] = $userId;
        } elseif ($userRole === 'admin' && $this->get('user_id')) {
             $criteria['user_id'] = (int)$this->get('user_id');
        }

        $totalDeposits = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 1]));
        $totalWithdrawals = Operation::sumAmountByCriteria(array_merge($criteria, ['operation_type_id' => 2]));
        $totalCommissionsEarned = Operation::sumAmountByCriteria($criteria, 'commission_applied');

        if ($totalDeposits == 0 && $totalWithdrawals == 0 && $totalCommissionsEarned == 0) {
             http_response_code(404);
             echo "Aucune donnée pour le résumé financier pour les critères sélectionnés.";
             exit;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Résumé Financier');

        // Headers
        $sheet->setCellValue('A1', 'Libellé');
        $sheet->setCellValue('B1', 'Montant (FCFA)');
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);

        // Data
        $sheet->setCellValue('A2', 'Total Dépôts');
        $sheet->setCellValue('B2', $totalDeposits);
        $sheet->setCellValue('A3', 'Total Retraits');
        $sheet->setCellValue('B3', $totalWithdrawals);
        $sheet->setCellValue('A4', 'Total Commissions Gagnées');
        $sheet->setCellValue('B4', $totalCommissionsEarned);

        $sheet->getStyle('B2:B4')->getNumberFormat()->setFormatCode('#,##0.00');

        // Add filter information
        $rowNumber = 6;
        $sheet->setCellValue('A'.$rowNumber, 'Filtres Appliqués:');
        $sheet->getStyle('A'.$rowNumber)->getFont()->setBold(true);
        $rowNumber++;
        if (!empty($criteria['date_from'])) { $sheet->setCellValue('A'.$rowNumber, 'Du:'); $sheet->setCellValue('B'.$rowNumber, date('d/m/Y', strtotime($criteria['date_from']))); $rowNumber++; }
        if (!empty($criteria['date_to'])) { $sheet->setCellValue('A'.$rowNumber, 'Au:'); $sheet->setCellValue('B'.$rowNumber, date('d/m/Y', strtotime($criteria['date_to']))); $rowNumber++; }
        if (!empty($criteria['user_id'])) {
            $user = User::find($criteria['user_id']);
            if ($user) { $sheet->setCellValue('A'.$rowNumber, 'Utilisateur:'); $sheet->setCellValue('B'.$rowNumber, $user->full_name); }
        }


        $writer = new Xlsx($spreadsheet);
        $fileName = "resume_financier_" . date('Y-m-d_H-i-s') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Export user activity statistics to PDF.
     * Access: Admin only.
     */
    public function exportUserActivityPDF(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format.'],400); return; }
        if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format.'],400); return; }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $specificUserId = $this->get('user_id') ? (int)$this->get('user_id') : null;
        $gerants = User::query("SELECT id, full_name, username FROM users WHERE role = 'gerant' AND is_active = TRUE" . ($specificUserId ? " AND id = :specific_user_id" : ""), ($specificUserId ? ['specific_user_id' => $specificUserId] : []));

        $userActivityData = [];
        foreach ($gerants as $gerant) {
            $criteria = array_merge($filters, ['user_id' => $gerant->id]);
            $opCount = Operation::countByCriteria($criteria);

            $lastOpStmtParams = ['user_id' => $gerant->id];
            $lastOpSql = "SELECT MAX(operation_time) as last_op FROM operations WHERE user_id = :user_id";
            if(isset($filters['date_from'])) { $lastOpSql .= " AND operation_time >= :date_from"; $lastOpStmtParams['date_from'] = $filters['date_from']; }
            if(isset($filters['date_to'])) { $lastOpSql .= " AND operation_time <= :date_to"; $lastOpStmtParams['date_to'] = $filters['date_to']; }
            $lastOpStmt = Operation::db()->prepare($lastOpSql);
            $lastOpStmt->execute($lastOpStmtParams);
            $lastOpResult = $lastOpStmt->fetch(\PDO::FETCH_ASSOC);

            $userActivityData[] = [
                'user_full_name' => $gerant->full_name,
                'username' => $gerant->username,
                'total_ops' => $opCount,
                'last_activity_date' => $lastOpResult['last_op'] ?? null
            ];
        }

        if (empty($userActivityData)) {
            http_response_code(404);
            echo "Aucune donnée d'activité utilisateur trouvée pour les critères sélectionnés.";
            exit;
        }

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
        $html .= "<style>body { font-family: DejaVu Sans, sans-serif; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";
        $html .= "</head><body><h1>Activité des Gérants</h1>";
        // Add filter information
        $html .= "<p>Filtres : ";
        $filterParts = [];
        if (!empty($filters['date_from'])) $filterParts[] = "Du ".date('d/m/Y', strtotime($filters['date_from']));
        if (!empty($filters['date_to'])) $filterParts[] = "Au ".date('d/m/Y', strtotime($filters['date_to']));
        if ($specificUserId) {
            $user = User::find($specificUserId);
            if ($user) $filterParts[] = "Gérant: ".$user->full_name;
        }
        $html .= count($filterParts) > 0 ? implode(', ', $filterParts) : "Tous les gérants, toutes dates";
        $html .= "</p>";

        $html .= "<table><thead><tr><th>Gérant (Nom complet)</th><th>Nom d'utilisateur</th><th>Nombre d'Opérations</th><th>Dernière Activité</th></tr></thead><tbody>";
        foreach ($userActivityData as $activity) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($activity['user_full_name']) . "</td>";
            $html .= "<td>" . htmlspecialchars($activity['username']) . "</td>";
            $html .= "<td>" . htmlspecialchars($activity['total_ops']) . "</td>";
            $html .= "<td>" . htmlspecialchars($activity['last_activity_date'] ? date('d/m/Y H:i:s', strtotime($activity['last_activity_date'])) : 'N/A') . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table></body></html>";

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = "activite_utilisateurs_" . date('Y-m-d') . ".pdf";
        $dompdf->stream($fileName, ["Attachment" => true]);
        exit;
    }

    /**
     * Export user activity statistics to Excel.
     * Access: Admin only.
     */
    public function exportUserActivityExcel(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $filters = [
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);

        if (($filters['date_from'] ?? null) && !$this->isValidDate($filters['date_from'])) {
            $this->jsonResponse(['error' => 'Invalid date_from format.'],400); return; }
        if (($filters['date_to'] ?? null) && !$this->isValidDate($filters['date_to'])) {
            $this->jsonResponse(['error' => 'Invalid date_to format.'],400); return; }
        if(isset($filters['date_from'])) $filters['date_from'] .= " 00:00:00";
        if(isset($filters['date_to'])) $filters['date_to'] .= " 23:59:59";

        $specificUserId = $this->get('user_id') ? (int)$this->get('user_id') : null;
        $gerants = User::query("SELECT id, full_name, username FROM users WHERE role = 'gerant' AND is_active = TRUE" . ($specificUserId ? " AND id = :specific_user_id" : ""), ($specificUserId ? ['specific_user_id' => $specificUserId] : []));

        $userActivityData = [];
        foreach ($gerants as $gerant) {
            $criteria = array_merge($filters, ['user_id' => $gerant->id]);
            $opCount = Operation::countByCriteria($criteria);

            $lastOpStmtParams = ['user_id' => $gerant->id];
            $lastOpSql = "SELECT MAX(operation_time) as last_op FROM operations WHERE user_id = :user_id";
            if(isset($filters['date_from'])) { $lastOpSql .= " AND operation_time >= :date_from"; $lastOpStmtParams['date_from'] = $filters['date_from']; }
            if(isset($filters['date_to'])) { $lastOpSql .= " AND operation_time <= :date_to"; $lastOpStmtParams['date_to'] = $filters['date_to']; }
            $lastOpStmt = Operation::db()->prepare($lastOpSql);
            $lastOpStmt->execute($lastOpStmtParams);
            $lastOpResult = $lastOpStmt->fetch(\PDO::FETCH_ASSOC);

            $userActivityData[] = [
                'user_full_name' => $gerant->full_name,
                'username' => $gerant->username,
                'total_ops' => $opCount,
                'last_activity_date' => $lastOpResult['last_op'] ?? null
            ];
        }

        if (empty($userActivityData)) {
            http_response_code(404);
            echo "Aucune donnée d'activité utilisateur trouvée pour les critères sélectionnés.";
            exit;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Activité Utilisateurs');

        $sheet->setCellValue('A1', 'Gérant (Nom complet)');
        $sheet->setCellValue('B1', 'Nom d\'utilisateur');
        $sheet->setCellValue('C1', 'Nombre d\'Opérations');
        $sheet->setCellValue('D1', 'Dernière Activité');

        foreach (range('A', 'D') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $rowNumber = 2;
        foreach ($userActivityData as $activity) {
            $sheet->setCellValue('A' . $rowNumber, $activity['user_full_name']);
            $sheet->setCellValue('B' . $rowNumber, $activity['username']);
            $sheet->setCellValue('C' . $rowNumber, $activity['total_ops']);
            $sheet->setCellValue('D' . $rowNumber, $activity['last_activity_date'] ? date('Y-m-d H:i:s', strtotime($activity['last_activity_date'])) : 'N/A');
            $rowNumber++;
        }

        // Add filter information
        $rowNumber += 2;
        $sheet->setCellValue('A'.$rowNumber, 'Filtres Appliqués:');
        $sheet->getStyle('A'.$rowNumber)->getFont()->setBold(true);
        $rowNumber++;
        if (!empty($filters['date_from'])) { $sheet->setCellValue('A'.$rowNumber, 'Du:'); $sheet->setCellValue('B'.$rowNumber, date('d/m/Y', strtotime($filters['date_from']))); $rowNumber++; }
        if (!empty($filters['date_to'])) { $sheet->setCellValue('A'.$rowNumber, 'Au:'); $sheet->setCellValue('B'.$rowNumber, date('d/m/Y', strtotime($filters['date_to']))); $rowNumber++; }
        if ($specificUserId) {
            $user = User::find($specificUserId);
            if ($user) { $sheet->setCellValue('A'.$rowNumber, 'Gérant:'); $sheet->setCellValue('B'.$rowNumber, $user->full_name); }
        }


        $writer = new Xlsx($spreadsheet);
        $fileName = "activite_utilisateurs_" . date('Y-m-d_H-i-s') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
