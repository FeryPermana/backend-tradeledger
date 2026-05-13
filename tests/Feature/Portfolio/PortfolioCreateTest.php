<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_portfolio_position(): void
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

        $payload = [
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_price' => 9000,
            'total_fees' => 1000,
            'target_price' => 12000,
            'horizon' => 'long_term',
            'conviction_level' => 'high',
            'thesis' => 'Growth thesis',
            'notes' => 'Core position',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/portfolio-positions', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('portfolio_positions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_price' => 9000,
            'horizon' => 'long_term',
            'conviction_level' => 'high',
        ]);
    }

    public function test_portfolio_create_rejects_duplicate_asset_and_account(): void
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

        PortfolioPosition::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_price' => 9000,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/portfolio-positions', [
                'account_id' => $account->id,
                'asset_id' => $asset->id,
                'quantity' => 5,
                'avg_price' => 9500,
            ]);
        $response->assertStatus(400);
    }

    public function test_guest_cannot_create_portfolio_position(): void
    {
        $response = $this->postJson('/api/portfolio-positions', []);

        $response->assertStatus(401);
    }
}
