<?php

namespace App\Controllers;

use App\Models\Balance;
use App\Models\Operation;
use App\Models\User;
use DateTime;

class ClotureController extends Controller {

    public function __construct() {
        parent::__construct();
        // Role checks will be done per-method now, as this controller has admin and gerant methods
        $this->requireAuth();
    }

    private function _getInitialAmountToday(int $userId): float {
        $lastBalance = Balance::getCurrentForUser($userId);
        $initialAmountToday = 0.0;

        if ($lastBalance) {
            $today = new DateTime('today');
            $lastBalanceDate = new DateTime($lastBalance->balance_date);

            if ($lastBalanceDate < $today && $lastBalance->is_closed) {
                $initialAmountToday = $lastBalance->actual_final_amount ?? 0.0;
            } elseif ($lastBalanceDate == $today && !$lastBalance->is_closed) {
                $initialAmountToday = $lastBalance->initial_amount;
            } elseif ($lastBalanceDate >= $today && $lastBalance->is_closed) {
                return -1; // Special value for already closed today or future
            }
        }
        return $initialAmountToday;
    }

    private function _calculateTheoreticalBalanceData(int $userId, float $initialAmountToday): array {
        $unclosedOperations = Operation::getUnclosedOperationsForUserToday($userId);

        $netCashFromOperations = 0.0;
        $totalCommissionToday = 0.0;
        $operationIdsToClose = [];

        foreach ($unclosedOperations as $op) {
            if (isset($op->balance_effect)) {
                switch ($op->balance_effect) {
                    case 'positive':
                        $netCashFromOperations += $op->amount;
                        break;
                    case 'negative':
                        $netCashFromOperations -= $op->amount;
                        break;
                    case 'neutral':
                        break;
                }
            }
            $totalCommissionToday += $op->commission_applied;
            $operationIdsToClose[] = $op->id;
        }

        $calculatedTheoreticalAmount = $initialAmountToday + $netCashFromOperations;

        return [
            'unclosed_operations' => $unclosedOperations,
            'calculated_theoretical_amount' => $calculatedTheoreticalAmount,
            'total_commission_today' => $totalCommissionToday,
            'operation_ids_to_close' => $operationIdsToClose
        ];
    }

    public function getClotureDataToday(): void {
        if ($this->getCurrentUserRole() !== 'gerant') {
            $this->jsonResponse(['error' => 'Forbidden. Gérant access required.'], 403);
            return;
        }
        $userId = $this->getCurrentUserId();

        $initialAmountToday = $this->_getInitialAmountToday($userId);
        if ($initialAmountToday === -1.0) {
            $this->jsonResponse(['error' => 'La journée semble déjà clôturée ou une clôture future existe.'], 400);
            return;
        }

        $clotureData = $this->_calculateTheoreticalBalanceData($userId, $initialAmountToday);

        $this->jsonResponse([
            'initial_amount_today' => $initialAmountToday,
            'unclosed_operations' => $clotureData['unclosed_operations'],
            'calculated_theoretical_amount' => $clotureData['calculated_theoretical_amount'],
            'total_commission_today_on_unclosed' => $clotureData['total_commission_today'],
            'date_today' => date('Y-m-d')
        ]);
    }

    public function submitCloture(): void {
        if ($this->getCurrentUserRole() !== 'gerant') {
            $this->jsonResponse(['error' => 'Forbidden. Gérant access required.'], 403);
            return;
        }
        $userId = $this->getCurrentUserId();
        $input = $this->getJsonInput();

        if (!isset($input['actual_final_amount']) || !is_numeric($input['actual_final_amount'])) {
            $this->jsonResponse(['errors' => ['actual_final_amount' => 'Le solde réel final est requis et doit être numérique.']], 422);
            return;
        }
        $actualFinalAmount = (float)$input['actual_final_amount'];
        $notes = $input['notes'] ?? null;

        $initialAmountToday = $this->_getInitialAmountToday($userId);
        if ($initialAmountToday === -1.0) {
             $this->jsonResponse(['error' => 'La journée est déjà clôturée.'], 400);
             return;
        }

        $clotureCalculationData = $this->_calculateTheoreticalBalanceData($userId, $initialAmountToday);
        $calculatedFinalAmount = $clotureCalculationData['calculated_theoretical_amount'];
        $totalCommissionCalculated = $clotureCalculationData['total_commission_today'];
        $operationIdsToClose = $clotureCalculationData['operation_ids_to_close'];

        $differenceAmount = $actualFinalAmount - $calculatedFinalAmount;

        $db = Balance::db();
        try {
            $db->beginTransaction();

            $balanceData = [
                'user_id' => $userId,
                'service_id' => null,
                'balance_date' => date('Y-m-d'),
                'initial_amount' => $initialAmountToday,
                'calculated_final_amount' => $calculatedFinalAmount,
                'actual_final_amount' => $actualFinalAmount,
                'difference_amount' => $differenceAmount,
                'total_commission_calculated' => $totalCommissionCalculated,
                'notes' => $notes,
                'is_closed' => true,
                'closed_at' => date('Y-m-d H:i:s'),
            ];

            $newBalance = Balance::createBalanceEntry($balanceData);
            if (!$newBalance) {
                throw new \Exception("Failed to create balance entry.");
            }

            if (!empty($operationIdsToClose)) {
                $markedClosed = Operation::markOperationsAsClosed($operationIdsToClose, $newBalance->id);
                if (!$markedClosed) {
                    throw new \Exception("Failed to mark operations as closed.");
                }
            }

            $db->commit();
            $this->jsonResponse([
                'message' => 'Clôture soumise avec succès.',
                'balance' => $newBalance
            ], 201);

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->jsonResponse(['error' => 'Erreur lors de la soumission de la clôture: ' . $e->getMessage()], 500);
        }
    }

    public function getDailySummariesForAdmin(): void {
        if ($this->getCurrentUserRole() !== 'admin') {
            $this->jsonResponse(['error' => 'Forbidden. Admin access required.'], 403);
            return;
        }

        $filters = [];
        $specificDate = $this->get('date');
        $dateFrom = $this->get('date_from');
        $dateTo = $this->get('date_to');
        $userIdFilter = $this->get('user_id') ? (int)$this->get('user_id') : null;

        if ($specificDate) {
            if (!$this->isValidDate($specificDate)) {
                 $this->jsonResponse(['error' => 'Invalid date format for "date". Use YYYY-MM-DD.'], 400); return; }
            $filters['date_specific'] = $specificDate;
        } elseif ($dateFrom || $dateTo) {
            if ($dateFrom && !$this->isValidDate($dateFrom)) {
                 $this->jsonResponse(['error' => 'Invalid date format for "date_from". Use YYYY-MM-DD.'], 400); return; }
            if ($dateTo && !$this->isValidDate($dateTo)) {
                 $this->jsonResponse(['error' => 'Invalid date format for "date_to". Use YYYY-MM-DD.'], 400); return; }
            if ($dateFrom) $filters['date_from'] = $dateFrom;
            if ($dateTo) $filters['date_to'] = $dateTo;
        }

        if ($userIdFilter) {
            $filters['user_id'] = $userIdFilter;
        }

        $page = (int)($this->get('page', 1));
        $limit = (int)($this->get('limit', 15));
        $offset = ($page - 1) * $limit;

        $result = Balance::getSummaries($filters, $limit, $offset);

        $this->jsonResponse([
            'message' => 'Daily summaries for admin retrieved successfully.',
            'filters_applied' => $filters,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => $result['total'],
                'total_pages' => ceil($result['total'] / $limit)
            ],
            'data' => $result['data']
        ]);
    }

    private function isValidDate(string $dateString, string $format = 'Y-m-d'): bool {
        $d = \DateTime::createFromFormat($format, $dateString);
        return $d && $d->format($format) === $dateString;
    }

    public function getGerantDailySummaries(): void {
        // Added explicit role check although constructor now only does requireAuth()
        if ($this->getCurrentUserRole() !== 'gerant') {
            $this->jsonResponse(['error' => 'Forbidden. Gérant access required.'], 403);
            return;
        }
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['error' => 'User not authenticated properly.'], 401); // Should be caught by requireAuth
            return;
        }

        $filters = ['user_id' => $userId];
        $specificDate = $this->get('date');
        $dateFrom = $this->get('date_from');
        $dateTo = $this->get('date_to');

        if ($specificDate) {
            if (!$this->isValidDate($specificDate)) {
                 $this->jsonResponse(['error' => 'Invalid date format for "date". Use YYYY-MM-DD.'], 400);
                 return;
            }
            $filters['date_specific'] = $specificDate;
        } elseif ($dateFrom || $dateTo) {
            if ($dateFrom && !$this->isValidDate($dateFrom)) {
                 $this->jsonResponse(['error' => 'Invalid date format for "date_from". Use YYYY-MM-DD.'], 400);
                 return;
            }
            if ($dateTo && !$this->isValidDate($dateTo)) {
                 $this->jsonResponse(['error' => 'Invalid date format for "date_to". Use YYYY-MM-DD.'], 400);
                 return;
            }
            if ($dateFrom) $filters['date_from'] = $dateFrom;
            if ($dateTo) $filters['date_to'] = $dateTo;
        }

        $page = (int)($this->get('page', 1));
        $limit = (int)($this->get('limit', 15));
        $offset = ($page - 1) * $limit;

        $result = Balance::getSummaries($filters, $limit, $offset);

        $this->jsonResponse([
            'message' => 'Daily summaries for gérant retrieved successfully.',
            'filters_applied' => $filters,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => $result['total'],
                'total_pages' => ceil($result['total'] / $limit)
            ],
            'data' => $result['data']
        ]);
    }
}
