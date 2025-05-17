<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\DebitCard;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards

        $debitCards = DebitCard::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'number',
                        'type',
                        'expiration_date',
                        'is_active',
                    ]
                ]
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $userDebitCard = DebitCard::factory()->create([
            'user_id' => this->user->id
        ]);

        $response = $this->getJson('api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $userDebitCard->id])
            ->assertJsonMissing(['id' => $otherDebitCard->id]);

    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards

        $data = [
            'type' => 'Visa'
        ];

        $response = $this->postJson('api/deit-cards', 'data');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
                ]
            ]);
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'Visa'
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response = assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
                ]
            ])
            ->assertJsonFragment(['id' => $debitCard->id]);

    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson("/api/debit-cards/{$otherDebitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => now()
        ]);

        $data = [
            'is_active' => true
        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $data);

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => true]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}

        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        $data = [
            'is_active' => false
        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $data);

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseMissing('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null
        ]);

    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        $data = [

        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('debit_cards', ['id' => $debitCard->id]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('debit_cards', ['id' => $debitCard->id]);
    }

    // Extra bonus for extra tests :)

    public function testCustomerCannotCreateADebitCardWithoutRequiredType()
    {
        $data = [];

        $response = $this->postJson('/api/debit-cards', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        $this->assertEquals(0, DebitCard::where('user_id', $this->user->id)->count());
    }

    public function testCustomerCannotUpdateAnotherCustomerDebitCard()
{
    $otherUser = User::factory()->create();
    $otherDebitCard = DebitCard::factory()->create([
        'user_id' => $otherUser->id
    ]);
    
    $data = [
        'is_active' => false
    ];
    
    $response = $this->putJson("/api/debit-cards/{$otherDebitCard->id}", $data);
    
    $response->assertStatus(403);
    
    $this->assertDatabaseMissing('debit_cards', [
        'id' => $otherDebitCard->id,
        'disabled_at' => now()
    ]);
}
}
