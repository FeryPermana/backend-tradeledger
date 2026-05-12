<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_account(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Bareksa Spot',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 0,
        ]);

        $payload = [
            'name' => 'Updated Bareksa',
            'type' => 'investment',
            'currency' => 'USD',
            'initial_balance' => 1000,
        ];

        $response = $this->actingAs($user)
            ->putJson("/api/accounts/" . $account->id, $payload);

        // dd($response->status(), $response->json());

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Bareksa',
            'currency' => 'USD',
            'initial_balance' => 1000,
        ]);
    }

    public function test_guest_cannot_update_account(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Bareksa Spot',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 0,
        ]);

        $response = $this->putJson("/api/accounts/". $account->id, [
            'name' => 'Updated Bareksa',
        ]);

        $response->assertStatus(401);
    }
}
