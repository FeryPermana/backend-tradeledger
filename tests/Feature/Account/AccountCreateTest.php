<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_account(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $payload = [
            'name' => 'Bareksa Spot',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 0,
        ];

        $response = $this->actingAs($user)->postJson('/api/accounts', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Bareksa Spot',
            'type' => 'investment',
            'currency' => 'IDR',
        ]);
    }

    public function test_guest_cannot_create_account(): void
    {
        $response = $this->postJson('/api/accounts', [
            'name' => 'Bareksa Spot',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 0,
        ]);

        $response->assertStatus(401);
    }
}
