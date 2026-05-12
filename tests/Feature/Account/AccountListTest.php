<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_account_list(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => 'password',
            'base_currency' => 'IDR',
            'password_confirmation' => 'password',
        ]);

        Sanctum::actingAs($user);

        Account::create([
            "user_id" => $user->id,
            "name" => "Bareksa Spot",
            "type" => "investment",
            "currency" => "IDR",
            "initial_balance" => 0
        ]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'name' => 'Bareksa Spot',
        ]);
    }

    public function test_guest_cannot_access_account_list(): void
    {
        $response = $this->getJson('/api/accounts');

        $response->assertStatus(401);
    }
}
