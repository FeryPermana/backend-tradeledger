<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioPartialCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_partial_close_portfolio_position(): void
    {
        $user = User::create([
            'name' => 'Ferry',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Stock Account',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
        ]);

        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);

        $position = PortfolioPosition::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'source_type' => 'manual',
            'quantity' => 10,
            'avg_price' => 9000,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/portfolio-positions/' . $position->id . '/partial-close', [
                'quantity' => 4,
                'exit_price' => 10000,
                'exit_fee' => 1000,
                'exit_date' => now()->toDateString(),
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('portfolio_positions', [
            'id' => $position->id,
            'quantity' => 6,
        ]);

        $this->assertDatabaseHas('trades', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'position_type' => 'investment',
            'status' => 'closed',
            'quantity' => 4,
            'closed_quantity' => 4,
            'profit_loss' => 3000,
            'notes' => 'Partial Sell (Investment)',
        ]);
    }

    public function test_partial_close_rejects_quantity_greater_than_position(): void
    {
        $user = User::create([
            'name' => 'Ferry',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Stock Account',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
        ]);

        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);

        $position = PortfolioPosition::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'source_type' => 'manual',
            'quantity' => 10,
            'avg_price' => 9000,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/portfolio-positions/' . $position->id . '/partial-close', [
                'quantity' => 11,
                'exit_price' => 10000,
                'exit_fee' => 1000,
                'exit_date' => now()->toDateString(),
            ]);

        $response->assertStatus(400);
    }
}
