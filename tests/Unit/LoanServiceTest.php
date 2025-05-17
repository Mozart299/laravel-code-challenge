<?php

namespace Tests\Unit;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->loanService = new LoanService();
    }

    public function testServiceCanCreateLoanOfForACustomer()
    {
        $terms = 3;
        $amount = 5000;
        $currencyCode = Loan::CURRENCY_VND;
        $processedAt = '2020-01-20 00:00:00';

        $loan = $this->loanService->createLoan($this->user, $amount, $currencyCode, $terms, $processedAt);


        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'user_id' => $this->user->id,
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'processed_at' => '2020-01-20 00:00:00',
            'status' => Loan::STATUS_DUE,
        ]);

        $this->assertCount($terms, $loan->scheduledRepayments);
        $this->assertDatabaseHas('scheduled_repayments', [
            'loan_id' => $loan->id,
            'amount' => 1666,
            'outstanding_amount' => 1666,
            'currency_code' => $currencyCode,
            'due_date' => '2020-02-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);
        $this->assertDatabaseHas('scheduled_repayments', [
            'loan_id' => $loan->id,
            'amount' => 1666,
            'outstanding_amount' => 1666,
            'currency_code' => $currencyCode,
            'due_date' => '2020-03-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);
        $this->assertDatabaseHas('scheduled_repayments', [
            'loan_id' => $loan->id,
            'amount' => 1668,
            'outstanding_amount' => 1668,
            'currency_code' => $currencyCode,
            'due_date' => '2020-04-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);
        $this->assertEquals($amount, $loan->scheduledRepayments()->sum('amount'));
    }

    public function testServiceCanRepayAScheduledRepayment()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'terms' => 3,
            'amount' => 5000,
            'outstanding_amount' => 5000,
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => '2020-01-20 00:00:00',
        ]);

        $scheduledRepaymentOne = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1666,
            'outstanding_amount' => 1666,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-02-20 00:00:00',
        ]);
        $scheduledRepaymentTwo = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1666,
            'outstanding_amount' => 1666,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-03-20 00:00:00',
        ]);
        $scheduledRepaymentThree = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1668,
            'outstanding_amount' => 1668,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-04-20 00:00:00',
        ]);

        $receivedRepayment = 1666;
        $currencyCode = Loan::CURRENCY_VND;
        $receivedAt = '2020-02-20';

        $receivedRepayment = $this->loanService->repayLoan($loan, $receivedRepayment, $currencyCode, $receivedAt);

        // Refresh the loan
        $loan->refresh();

        // Assert the loan has been updated correctly
        $this->assertEquals(3334, $loan->outstanding_amount);
        $this->assertEquals(Loan::STATUS_DUE, $loan->status);

        // Assert the scheduled repayment has been updated correctly
        $scheduledRepaymentOne->refresh();
        $this->assertEquals(0, $scheduledRepaymentOne->outstanding_amount);
        $this->assertEquals(ScheduledRepayment::STATUS_REPAID, $scheduledRepaymentOne->status);

        // Assert other repayments are unchanged
        $scheduledRepaymentTwo->refresh();
        $scheduledRepaymentThree->refresh();
        $this->assertEquals(ScheduledRepayment::STATUS_DUE, $scheduledRepaymentTwo->status);
        $this->assertEquals(ScheduledRepayment::STATUS_DUE, $scheduledRepaymentThree->status);

        // Assert received repayment was created
        $this->assertDatabaseHas('received_repayments', [
            'loan_id' => $loan->id,
            'amount' => 1666,
            'currency_code' => $currencyCode,
            'received_at' => '2020-02-20 00:00:00',
        ]);
    }

    public function testServiceCanRepayAScheduledRepaymentConsecutively()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'terms' => 3,
            'amount' => 5000,
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => '2020-01-20 00:00:00',
        ]);

        $scheduledRepaymentOne = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1666,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-02-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_REPAID,
        ]);
        $scheduledRepaymentTwo = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1666,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-03-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_REPAID,
        ]);

        $scheduledRepaymentThree = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1667,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-04-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);

        $receivedRepayment = 1667;
        $currencyCode = Loan::CURRENCY_VND;
        $receivedAt = '2020-04-20';

        $loan = $this->loanService->repayLoan($loan, $receivedRepayment, $currencyCode, $receivedAt);

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id, // Changed from 'id' => 2,
            'user_id' => $this->user->id,
            'amount' => 5000,
            'outstanding_amount' => 0,
            'currency_code' => $currencyCode,
            'status' => Loan::STATUS_REPAID,
            'processed_at' => '2020-01-20 00:00:00',
        ]);

        $this->assertDatabaseHas('scheduled_repayments', [
            'id' => $scheduledRepaymentThree->id,
            'loan_id' => $loan->id,
            'amount' => 1667,
            'outstanding_amount' => 0,
            'currency_code' => $currencyCode,
            'status' => ScheduledRepayment::STATUS_REPAID,
        ]);

        $this->assertDatabaseHas('received_repayments', [
            'loan_id' => $loan->id,
            'amount' => 1667,
            'currency_code' => $currencyCode,
            'received_at' => '2020-04-20 00:00:00',
        ]);
    }

    public function testServiceCanRepayMultipleScheduledRepayments()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'terms' => 3,
            'amount' => 5000,
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => '2020-01-20 00:00:00',
        ]);

        $scheduledRepaymentOne = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1666,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-02-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);
        $scheduledRepaymentTwo = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1666,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-03-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);
        $scheduledRepaymentThree = ScheduledRepayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => 1667,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => '2020-04-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);

        $receivedRepayment = 2000;
        $currencyCode = Loan::CURRENCY_VND;
        $receivedAt = '2020-02-20';

        $loan = $this->loanService->repayLoan($loan, $receivedRepayment, $currencyCode, $receivedAt);

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id, // Changed from 'id' => 2,
            'user_id' => $this->user->id,
            'amount' => 5000,
            'outstanding_amount' => 5000 - 2000,
            'currency_code' => $currencyCode,
            'status' => Loan::STATUS_DUE,
            'processed_at' => '2020-01-20 00:00:00',
        ]);

        $this->assertDatabaseHas('scheduled_repayments', [
            'id' => $scheduledRepaymentOne->id,
            'loan_id' => $loan->id,
            'amount' => 1666,
            'outstanding_amount' => 0,
            'currency_code' => $currencyCode,
            'status' => ScheduledRepayment::STATUS_REPAID,
        ]);

        $this->assertDatabaseHas('scheduled_repayments', [
            'id' => $scheduledRepaymentTwo->id,
            'loan_id' => $loan->id,
            'amount' => 1667,
            'outstanding_amount' => 333,
            'currency_code' => $currencyCode,
            'due_date' => '2020-03-20 00:00:00',
            'status' => ScheduledRepayment::STATUS_PARTIAL,
        ]);

        $this->assertDatabaseHas('received_repayments', [
            'loan_id' => $loan->id,
            'amount' => 2000,
            'currency_code' => $currencyCode,
            'received_at' => '2020-02-20 00:00:00',
        ]);
    }
}
