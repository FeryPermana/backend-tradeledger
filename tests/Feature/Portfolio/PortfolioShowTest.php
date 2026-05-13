<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_portfolio_detail(): void
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
            'quantity' => 10,
            'avg_price' => 9000,
            'total_fees' => 1000,
            'current_price' => 9500,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/portfolio-positions/' . $position->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'asset_id' => $asset->id,
            'account_id' => $account->id,
            'display_currency' => 'IDR',
        ]);
    }

    public function test_user_cannot_view_other_user_portfolio(): void
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
            'name' => 'Stock Account',
            'type' => 'investment',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
        ]);

        $asset = Asset::create([
            'user_id' => $owner->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);

        $position = PortfolioPosition::create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_price' => 9000,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson('/api/portfolio-positions/' . $position->id);

        $response->assertStatus(403);
    }
}
