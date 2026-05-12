<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_asset_list(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'stock_idx',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
        ]);
    }

    public function test_guest_cannot_access_asset_list(): void
    {
        $response = $this->getJson('/api/assets');

        $response->assertStatus(401);
    }
}
