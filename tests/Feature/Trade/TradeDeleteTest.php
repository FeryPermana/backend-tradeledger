<?php

namespace Tests\Feature\Trade;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_trade(): void
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
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/trades/' . $trade->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('trades', [
            'id' => $trade->id,
        ]);
    }

    public function test_user_cannot_delete_other_user_trade(): void
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
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($otherUser)
            ->deleteJson('/api/trades/' . $trade->id);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_delete_trade(): void
    {
        $response = $this->deleteJson('/api/trades/1');

        $response->assertStatus(401);
    }
}
