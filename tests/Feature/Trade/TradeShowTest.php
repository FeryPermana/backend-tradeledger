<?php

namespace Tests\Feature\Trade;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_trade_detail(): void
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

        $trade = Trade::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 0.01,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/trades/' . $trade->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'position_type' => 'swing',
        ]);
    }

    public function test_user_cannot_view_other_user_trade(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $account = Account::create([
            'user_id' => $owner->id,
            'name' => 'Binance',
            'type' => 'investment',
            'currency' => 'USD',
            'initial_balance' => 1000,
        ]);

        $asset = Asset::create([
            'user_id' => $owner->id,
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'market' => 'CRYPTO',
            'category' => 'crypto',
        ]);

        $trade = Trade::create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 0.01,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson('/api/trades/' . $trade->id);

        $response->assertStatus(403);
    }
}
