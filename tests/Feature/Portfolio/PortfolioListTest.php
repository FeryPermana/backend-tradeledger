<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_portfolio_list(): void
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
            'total_fees' => 1000,
            'current_price' => 9500,
            'horizon' => 'long_term',
            'conviction_level' => 'high',
            'thesis' => 'Growth thesis',
            'notes' => 'Core position',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/portfolio-positions');
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'asset_symbol' => 'BBCA',
            'asset_name' => 'Bank Central Asia',
        ]);
    }

    public function test_guest_cannot_access_portfolio_list(): void
    {
        $response = $this->getJson('/api/portfolio-positions');

        $response->assertStatus(401);
    }
}
