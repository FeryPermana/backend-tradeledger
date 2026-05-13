<?php

namespace Tests\Feature\Trade;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_trade_note(): void
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
            'strategy_id' => null,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
            'notes' => 'Old note',
        ]);

        $payload = [
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'strategy_id' => null,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 10,
            'entry_date' => now()->toDateTimeString(),
            'notes' => 'Updated note',
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/trades/' . $trade->id, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('trades', [
            'id' => $trade->id,
            'notes' => 'Updated note',
        ]);
    }

    public function test_authenticated_user_can_partial_close_trade(): void
    {
        $user = User::create([
            'name' => 'Ferry',
            'email' => 'ferry2@example.com',
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
            'strategy_id' => null,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now(),
            'status' => 'open',
        ]);

        $payload = [
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'strategy_id' => null,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 1,
            'closed_quantity' => 0.4,
            'exit_price' => 65000,
            'exit_date' => now()->toDateTimeString(),
            'fees' => 5,
            'entry_date' => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/trades/' . $trade->id, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('trades', [
            'id' => $trade->id,
            'status' => 'partial',
        ]);

        $this->assertDatabaseHas('trades', [
            'position_type' => 'swing',
            'status' => 'closed',
            'quantity' => 0.4,
        ]);
    }

    public function test_user_cannot_update_other_user_trade(): void
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

        $payload = [
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'position_type' => 'swing',
            'entry_price' => 60000,
            'quantity' => 1,
            'closed_quantity' => 0,
            'fees' => 0,
            'entry_date' => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($otherUser)
            ->putJson('/api/trades/' . $trade->id, $payload);

        $response->assertStatus(422);
    }
}
