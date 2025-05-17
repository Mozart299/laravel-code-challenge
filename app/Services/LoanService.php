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
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE
        ]);

        $amountPerTerm = (int) ($amount / $terms);
        $remainder = $amount - ($amountPerTerm * $terms);

        $processedDate = Carbon::parse($processedAt);

        for ($i = 0; $i < $terms; $i++) {
            $repaymentAmount = $amountPerTerm;
            if ($i === $terms - 1) {
                $repaymentAmount += $remainder;
            }

            $dueDate = (clone $processedDate)->addMonths($i + 1);

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
  
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt
        ]);

 
        $scheduledRepayments = $loan->scheduledRepayments()
            ->whereIn('status', [ScheduledRepayment::STATUS_DUE, ScheduledRepayment::STATUS_PARTIAL])
            ->orderBy('due_date')
            ->get();

        $remainingAmount = $amount;

        foreach ($scheduledRepayments as $repayment) {
            if ($remainingAmount <= 0) {
                break;
            }

            $outstandingAmount = $repayment->outstanding_amount;

            if ($remainingAmount >= $outstandingAmount) {
                $repayment->outstanding_amount = 0;
                $repayment->status = ScheduledRepayment::STATUS_REPAID;
                $repayment->save();

                $remainingAmount -= $outstandingAmount;
            } else {
                $repayment->outstanding_amount = $outstandingAmount - $remainingAmount;
                $repayment->status = ScheduledRepayment::STATUS_PARTIAL;
                $repayment->save();

                $remainingAmount = 0;
            }
        }

        $loanOutstandingAmount = $loan->scheduledRepayments()->sum('outstanding_amount');

        $loanStatus = $loanOutstandingAmount <= 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE;

        $loan->outstanding_amount = $loanOutstandingAmount;
        $loan->status = $loanStatus;
        $loan->save();

        return $receivedRepayment;
    }
}
