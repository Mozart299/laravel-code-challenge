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
        // TODO: Complete factory
        $amount = $this->faker->numberBetween(1000, 10000);

        return [
            'user_id' => fn () => User::factory()->create()->id,
            'amount' => $amount,
            'terms' => $this->faker->randomElement([3, 6]),
            'outstanding_amount' => $amount, 
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'processed_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => Loan::STATUS_DUE,
        ];
    }
}
