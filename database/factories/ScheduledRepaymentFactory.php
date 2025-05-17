<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $amount = 1666;

        return [
            'loan_id' => null,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }

    public function forLoan(Loan $loan)
    {
        return $this->state(function (array $attributes) use ($loan) {
            return [
                'loan_id' => $loan->id,
                'currency_code' => $loan->currency_code,
            ];
        });
    }
}
