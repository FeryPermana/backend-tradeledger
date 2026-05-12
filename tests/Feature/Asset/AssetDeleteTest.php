<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_asset(): void
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

        $response = $this->actingAs($user)
            ->deleteJson('/api/assets/' . $asset->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('assets', [
            'id' => $asset->id,
        ]);
    }

    public function test_guest_cannot_delete_asset(): void
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

        $response = $this->deleteJson('/api/assets/' . $asset->id);

        $response->assertStatus(401);
    }

    public function test_user_cannot_delete_other_user_asset(): void
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
            ->deleteJson('/api/assets/' . $asset->id);

        $response->assertStatus(403);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
        ]);
    }
}
