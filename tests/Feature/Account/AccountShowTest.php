<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_account_detail(): void
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

        $response = $this->actingAs($user)
            ->getJson('/api/accounts/' . $account->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $account->id,
            'name' => 'Bareksa Spot',
            'type' => 'investment',
            'currency' => 'IDR',
        ]);
    }

    public function test_guest_cannot_view_account_detail(): void
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

        $response = $this->getJson('/api/accounts/' . $account->id);

        $response->assertStatus(401);
    }
}
