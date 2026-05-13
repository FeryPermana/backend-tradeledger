<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_manual_portfolio_position(): void
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
            'total_fees' => 1000,
        ]);

        $payload = [
            'account_id' => $account->id,
            'quantity' => 20,
            'avg_price' => 8500,
            'total_fees' => 1500,
            'target_price' => 12000,
            'horizon' => 'long_term',
            'conviction_level' => 'high',
            'thesis' => 'Updated thesis',
            'notes' => 'Updated notes',
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/portfolio-positions/' . $position->id, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('portfolio_positions', [
            'id' => $position->id,
            'quantity' => 20,
            'avg_price' => 8500,
            'target_price' => 12000,
            'horizon' => 'long_term',
            'conviction_level' => 'high',
        ]);
    }

    public function test_user_cannot_update_other_user_portfolio(): void
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
            'source_type' => 'manual',
            'quantity' => 10,
            'avg_price' => 9000,
        ]);

        $response = $this->actingAs($otherUser)
            ->putJson('/api/portfolio-positions/' . $position->id, [
                'account_id' => $account->id,
                'quantity' => 20,
                'avg_price' => 8500,
            ]);
        $response->assertStatus(422);
    }
}
