<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        // Create the loan
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE
        ]);

        // Calculate the amount per term (distributing any remainder to the last term)
        $amountPerTerm = intval($amount / $terms);
        $remainder = $amount % $terms;

        // Create scheduled repayments
        $processedDate = Carbon::parse($processedAt);

        for ($i = 0; $i < $terms; $i++) {
            $repaymentAmount = $amountPerTerm;

            // Add remainder to the last term
            if ($i == $terms - 1) {
                $repaymentAmount += $remainder;
            }

            // Calculate due date (one month after the previous date)
            $dueDate = (clone $processedDate)->addMonths($i + 1)->format('Y-m-d');

            // Create the scheduled repayment
            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $repaymentAmount,
                'outstanding_amount' => $repaymentAmount,
                'currency_code' => $currencyCode,
                'due_date' => $dueDate,
                'status' => ScheduledRepayment::STATUS_DUE
            ]);
        }

        return $loan->fresh(['scheduledRepayments']);
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        // Create the received repayment
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt
        ]);

        // Get all due or partial scheduled repayments
        $dueRepayments = $loan->scheduledRepayments()
            ->whereIn('status', [ScheduledRepayment::STATUS_DUE, ScheduledRepayment::STATUS_PARTIAL])
            ->orderBy('due_date')
            ->get();

        $remainingAmount = $amount;

        // Process each due repayment
        foreach ($dueRepayments as $scheduledRepayment) {
            if ($remainingAmount <= 0) {
                break;
            }

            $outstandingAmount = $scheduledRepayment->outstanding_amount;

            // If remaining amount covers the full outstanding amount
            if ($remainingAmount >= $outstandingAmount) {
                $scheduledRepayment->update([
                    'outstanding_amount' => 0,
                    'status' => ScheduledRepayment::STATUS_REPAID
                ]);

                $remainingAmount -= $outstandingAmount;
            }
            // If remaining amount covers part of the outstanding amount
            else {
                $newOutstandingAmount = $outstandingAmount - $remainingAmount;

                $scheduledRepayment->update([
                    'outstanding_amount' => $newOutstandingAmount,
                    'status' => ScheduledRepayment::STATUS_PARTIAL
                ]);

                $remainingAmount = 0;
            }
        }

        // Update loan outstanding amount and status
        $totalOutstanding = $loan->scheduledRepayments()->sum('outstanding_amount');
        $newStatus = $totalOutstanding > 0 ? Loan::STATUS_DUE : Loan::STATUS_REPAID;

        $loan->update([
            'outstanding_amount' => $totalOutstanding,
            'status' => $newStatus
        ]);

        return $receivedRepayment;
    }
}
