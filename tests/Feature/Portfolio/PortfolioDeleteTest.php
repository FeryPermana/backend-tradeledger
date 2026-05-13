<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_manual_portfolio_position(): void
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
            ->deleteJson('/api/portfolio-positions/' . $position->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('portfolio_positions', [
            'id' => $position->id,
        ]);
    }
}
