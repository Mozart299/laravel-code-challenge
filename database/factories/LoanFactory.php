<?php

namespace Database\Factories;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $amount = 5000; 

        return [
            'user_id' => fn() => User::factory()->create()->id,
            'amount' => $amount,
            'terms' => 3,
            'outstanding_amount' => $amount,
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => '2020-01-20 00:00:00',
            'status' => Loan::STATUS_DUE,
        ];
    }
}
