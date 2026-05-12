<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_asset_detail(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
            'is_watchlist' => true,
            'tradingview_url' => 'https://www.tradingview.com/symbols/IDX-BBCA/',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets/' . $asset->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);
    }

    public function test_guest_cannot_view_asset_detail(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
            'is_watchlist' => true,
        ]);

        $response = $this->getJson('/api/assets/' . $asset->id);

        $response->assertStatus(401);
    }

    public function test_user_cannot_view_other_user_asset(): void
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $asset = Asset::create([
            'user_id' => $owner->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
            'is_watchlist' => true,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson('/api/assets/' . $asset->id);

        $response->assertStatus(403);
    }
}
