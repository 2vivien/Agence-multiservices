<?php

namespace App\Controllers;

use App\Models\Balance;
use App\Models\Operation;
use DateTime;

class ClotureController extends Controller {

    public function __construct() {
        parent::__construct();
        $this->requireAuth('gerant'); // Only 'gerant' can access cloture functions
    }

    /**
     * Get data needed for the daily closing (cloture) process for the current gerant.
     * - Last balance's final amount (as today's initial amount)
     * - Unclosed operations for today
     * - Calculated theoretical final balance
     */
    public function getClotureDataToday(): void {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            // Should be caught by requireAuth, but as a safeguard
            $this->jsonResponse(['error' => 'Authentication required.'], 401);
            return;
        }

        // 1. Get the last balance entry to determine today's initial amount
        $lastBalance = Balance::getCurrentForUser($userId);
        $initialAmountToday = 0.0;
        if ($lastBalance && $lastBalance->actual_final_amount !== null) {
            // If the last balance was for yesterday or earlier and is closed, use its actual_final_amount.
            // If it's for today and not closed, it means we are resuming, so use its initial_amount.
            $today = new DateTime('today');
            $lastBalanceDate = new DateTime($lastBalance->balance_date);

            if ($lastBalanceDate < $today && $lastBalance->is_closed) {
                $initialAmountToday = $lastBalance->actual_final_amount;
            } elseif ($lastBalanceDate == $today && !$lastBalance->is_closed) {
                // This means a cloture process was started today but not finished.
                // Or it's a fresh day and this is the very first balance record being created (initial_amount would be 0).
                $initialAmountToday = $lastBalance->initial_amount;
            } elseif ($lastBalanceDate >= $today && $lastBalance->is_closed) {
                // Last balance is for today or future and already closed - this is unusual, might indicate an issue or already closed for day.
                 $this->jsonResponse(['error' => 'La journée semble déjà clôturée ou une clôture future existe.', 'last_balance_date' => $lastBalance->balance_date, 'is_closed' => $lastBalance->is_closed], 400);
                 return;
            }
            // If last balance is much older, admin might need to create an initial balance.
        }
        // If no last balance, initialAmountToday remains 0.0 (first day scenario).

        // 2. Get unclosed operations for today
        $unclosedOperations = Operation::getUnclosedOperationsForUserToday($userId);

        // 3. Calculate theoretical final balance
        $sumUnclosedOperations = 0.0;
        $totalCommissionToday = 0.0;
        foreach ($unclosedOperations as $op) {
            // Summing amounts based on a simple debit/credit logic will be complex here
            // as operation types define this. For now, sum all amounts directly.
            // A more robust system would have a clear 'effect_on_balance' property for operation types.
            $sumUnclosedOperations += $op->amount; // This needs refinement based on operation type effects
            $totalCommissionToday += $op->commission_applied;
        }

        // Theoretical balance = initial + sum_of_operation_amounts - sum_of_commissions_if_deducted
        // Assuming commissions are earnings and increase the theoretical cash,
        // or are deducted from cash if they are fees paid out.
        // For now, let's assume sumUnclosedOperations is net cash movement (deposits - withdrawals)
        // And commissions are earned by the gerant, thus ADDED to their theoretical cash.
        // This part needs clear business logic for how 'amount' and 'commission_applied' affect balance.
        // Simplified: initial + sum_of_all_amounts_from_operations (assuming amount is signed or type implies effect)
        // Let's assume 'amount' in operations is always positive, and its effect (in/out) is determined by operation_type.
        // For this placeholder:
        $calculatedTheoreticalAmount = $initialAmountToday + $sumUnclosedOperations; // Highly simplified!

        $this->jsonResponse([
            'initial_amount_today' => $initialAmountToday,
            'unclosed_operations' => $unclosedOperations,
            'calculated_theoretical_amount' => $calculatedTheoreticalAmount,
            'total_commission_today_on_unclosed' => $totalCommissionToday, // For info
            'date_today' => date('Y-m-d')
        ]);
    }

    /**
     * Submit the daily closing (cloture) with the actual final balance.
     */
    public function submitCloture(): void {
        $userId = $this->getCurrentUserId();
        $input = $this->getJsonInput();

        if (!isset($input['actual_final_amount']) || !is_numeric($input['actual_final_amount'])) {
            $this->jsonResponse(['errors' => ['actual_final_amount' => 'Le solde réel final est requis et doit être numérique.']], 422);
            return;
        }
        $actualFinalAmount = (float)$input['actual_final_amount'];
        $notes = $input['notes'] ?? null;

        // Recalculate cloture data to ensure consistency / detect new ops (simplified here)
        // In a real app, might need locking or pass a timestamp/token from getClotureDataToday
        $lastBalance = Balance::getCurrentForUser($userId);
        $initialAmountToday = 0.0;
        // Logic for initialAmountToday (same as in getClotureDataToday - should be refactored to a private method)
        $todayDate = new DateTime('today');
        if ($lastBalance) {
            $lastBalanceDate = new DateTime($lastBalance->balance_date);
            if ($lastBalanceDate < $todayDate && $lastBalance->is_closed) {
                $initialAmountToday = $lastBalance->actual_final_amount;
            } elseif ($lastBalanceDate == $todayDate && !$lastBalance->is_closed) {
                $initialAmountToday = $lastBalance->initial_amount;
            } elseif ($lastBalanceDate == $todayDate && $lastBalance->is_closed) {
                 $this->jsonResponse(['error' => 'La journée est déjà clôturée.'], 400);
                 return;
            }
        }

        $unclosedOperations = Operation::getUnclosedOperationsForUserToday($userId);
        $sumUnclosedOperations = 0.0;
        $totalCommissionCalculated = 0.0;
        $operationIdsToClose = [];
        foreach ($unclosedOperations as $op) {
            $sumUnclosedOperations += $op->amount; // Simplified sum, needs business logic for +/- effect
            $totalCommissionCalculated += $op->commission_applied;
            $operationIdsToClose[] = $op->id;
        }
        $calculatedFinalAmount = $initialAmountToday + $sumUnclosedOperations; // Highly simplified!
        $differenceAmount = $actualFinalAmount - $calculatedFinalAmount;

        // Start DB Transaction
        $db = Balance::db(); // Assuming a static method to get DB connection from Model
        try {
            $db->beginTransaction();

            $balanceData = [
                'user_id' => $userId,
                'service_id' => null, // For global daily balance
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
}
