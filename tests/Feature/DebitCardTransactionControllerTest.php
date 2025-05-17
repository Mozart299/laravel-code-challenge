<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use App\Models\DebitCardTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions

        DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['amount', 'currency_code']
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        DebitCardTransaction::factory()->count(2)->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $otherDebitCard->id);

        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions

        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'IDR'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'amount',
                'currency_code'
            ])
            ->assertJsonFragment([
                'amount' => 1000,
                'currency_code' => 'IDR'
            ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'IDR'
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 1000,
            'currency_code' => 'IDR'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 1000
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}

        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1500,
            'currency_code' => 'SGD'
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'amount',
                'currency_code'
            ])
            ->assertJsonFragment([
                'amount' => '1500',
                'currency_code' => 'SGD'
            ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $otherTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$otherTransaction->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
    public function testCustomerCannotCreateATransactionWithInvalidCurrencyCode()
    {
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'INVALID_CODE'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency_code']);

        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'INVALID_CODE'
        ]);
    }

    public function testCustomerCannotAccessTransactionsWithoutProvidingDebitCardId()
    {
        DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $this->debitCard->id
        ]);
        
        $response = $this->getJson('/api/debit-card-transactions');
        
        $response->assertStatus(403);
    }
}
