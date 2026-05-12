<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_account(): void
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
            ->deleteJson('/api/accounts/' . $account->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_guest_cannot_delete_account(): void
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

        $response = $this->deleteJson('/api/accounts/' . $account->id);

        $response->assertStatus(401);
    }
}
