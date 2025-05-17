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

        // Calculate the amount per term
        $amountPerTerm = (int) ($amount / $terms);
        $remainder = $amount % $terms;

        // Create scheduled repayments
        $processedDate = Carbon::parse($processedAt);

        for ($i = 0; $i < $terms; $i++) {
            $repaymentAmount = $amountPerTerm;
            // Add remainder to the last repayment
            if ($i === $terms - 1) {
                $repaymentAmount += $remainder;
            }

            // Calculate due date (one month after processed date)
            $dueDate = (clone $processedDate)->addMonths($i + 1)->format('Y-m-d');

            // Create scheduled repayment
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
        // Create the received repayment record
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt
        ]);

        // Get the scheduled repayments that are due or partially paid
        $scheduledRepayments = $loan->scheduledRepayments()
            ->whereIn('status', [ScheduledRepayment::STATUS_DUE, ScheduledRepayment::STATUS_PARTIAL])
            ->orderBy('due_date')
            ->get();

        $remainingAmount = $amount;

        // Apply the payment to each scheduled repayment
        foreach ($scheduledRepayments as $repayment) {
            if ($remainingAmount <= 0) {
                break;
            }

            $outstandingAmount = $repayment->outstanding_amount;

            // If we can fully pay this repayment
            if ($remainingAmount >= $outstandingAmount) {
                $repayment->update([
                    'outstanding_amount' => 0,
                    'status' => ScheduledRepayment::STATUS_REPAID
                ]);
                $remainingAmount -= $outstandingAmount;
            } else {
                // Partial payment
                $repayment->update([
                    'outstanding_amount' => $outstandingAmount - $remainingAmount,
                    'status' => ScheduledRepayment::STATUS_PARTIAL
                ]);
                $remainingAmount = 0;
            }
        }

        // Update the loan's outstanding amount
        $outstandingAmount = $loan->scheduledRepayments()->sum('outstanding_amount');
        
        // Update loan status
        $loanStatus = $outstandingAmount > 0 ? Loan::STATUS_DUE : Loan::STATUS_REPAID;
        
        $loan->update([
            'outstanding_amount' => $outstandingAmount,
            'status' => $loanStatus
        ]);

        return $receivedRepayment;
    }
}
