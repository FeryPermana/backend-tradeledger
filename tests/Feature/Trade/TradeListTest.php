<?php

namespace Tests\Feature\Trade;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_trade_list(): void
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
            'is_watchlist' => true,
        ]);

        Trade::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'strategy_id' => null,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 0.01,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/trades');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'position_type' => 'swing',
        ]);
    }

    public function test_guest_cannot_access_trade_list(): void
    {
        $response = $this->getJson('/api/trades');

        $response->assertStatus(401);
    }

    public function test_user_only_sees_own_trades(): void
    {
        $userA = User::create([
            'name' => 'User A',
            'email' => 'a@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $userB = User::create([
            'name' => 'User B',
            'email' => 'b@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $accountA = Account::create([
            'user_id' => $userA->id,
            'name' => 'A Account',
            'type' => 'investment',
            'currency' => 'USD',
            'initial_balance' => 1000,
        ]);

        $assetA = Asset::create([
            'user_id' => $userA->id,
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'market' => 'CRYPTO',
            'category' => 'crypto',
        ]);

        Trade::create([
            'user_id' => $userA->id,
            'account_id' => $accountA->id,
            'asset_id' => $assetA->id,
            'position_type' => 'swing',
            'entry_price' => 50000,
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($userB)
            ->getJson('/api/trades');

        $response->assertStatus(200);

        $response->assertJsonMissing([
            'position_type' => 'swing',
        ]);
    }
}
