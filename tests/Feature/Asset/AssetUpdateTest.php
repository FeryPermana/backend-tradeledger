<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_asset(): void
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
            'is_watchlist' => false,
            'tradingview_url' => 'https://www.tradingview.com/symbols/IDX-BBCA/',
        ]);

        $payload = [
            'symbol' => 'BBRI',
            'name' => 'Bank Rakyat Indonesia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
            'is_watchlist' => true,
            'tradingview_url' => 'https://www.tradingview.com/symbols/IDX-BBRI/',
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/assets/' . $asset->id, $payload);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'symbol' => 'BBRI',
            'name' => 'Bank Rakyat Indonesia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'user_id' => $user->id,
            'symbol' => 'BBRI',
            'name' => 'Bank Rakyat Indonesia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
            'is_watchlist' => 1,
        ]);
    }

    public function test_asset_update_rejects_invalid_category(): void
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
            'is_watchlist' => false,
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/assets/' . $asset->id, [
                'symbol' => 'BBRI',
                'name' => 'Bank Rakyat Indonesia',
                'market' => 'STOCK IDX',
                'category' => 'stock',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category']);
    }

    public function test_guest_cannot_update_asset(): void
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
            'is_watchlist' => false,
        ]);

        $response = $this->putJson('/api/assets/' . $asset->id, [
            'symbol' => 'BBRI',
            'name' => 'Bank Rakyat Indonesia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);

        $response->assertStatus(401);
    }
}
