<?php

namespace Tests\Feature\Trade;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_trade(): void
    {
        $user = User::create([
            'name' => 'Ferry',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Binance',
            'type' => 'investment',
            'currency' => 'USD',
            'initial_balance' => 1000,
        ]);

        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'market' => 'CRYPTO',
            'category' => 'crypto',
        ]);

        $payload = [
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'strategy_id' => null,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 0.01,
            'fees' => 0,
            'entry_date' => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/trades', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('trades', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'position_type' => 'swing',
        ]);
    }

    public function test_trade_create_requires_account_id(): void
    {
        $user = User::create([
            'name' => 'Ferry',
            'email' => 'ferry2@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/trades', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'account_id',
        ]);
    }

    public function test_guest_cannot_create_trade(): void
    {
        $response = $this->postJson('/api/trades', []);
        $response->assertStatus(401);
    }
}
