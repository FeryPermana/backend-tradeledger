<?php

namespace Tests\Feature\Asset;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_asset(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $payload = [
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/assets', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('assets', [
            'user_id' => $user->id,
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);
    }

    public function test_asset_creation_requires_required_fields(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/assets', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'symbol',
            'name',
            'market',
            'category',
        ]);
    }

    public function test_asset_creation_rejects_invalid_category(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'symbol' => 'BBCA',
                'name' => 'Bank Central Asia',
                'market' => 'STOCK IDX',
                'category' => 'stock',
            ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'category',
        ]);
    }

    public function test_asset_creation_rejects_invalid_tradingview_url(): void
    {
        $user = User::create([
            'name' => 'Hello Test',
            'email' => 'hellotest@example.com',
            'password' => bcrypt('password'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'symbol' => 'BBCA',
                'name' => 'Bank Central Asia',
                'market' => 'STOCK IDX',
                'category' => 'stock_idx',
                'tradingview_url' => 'bukan-url-valid',
            ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'tradingview_url',
        ]);
    }

    public function test_guest_cannot_create_asset(): void
    {
        $response = $this->postJson('/api/assets', [
            'symbol' => 'BBCA',
            'name' => 'Bank Central Asia',
            'market' => 'STOCK IDX',
            'category' => 'stock_idx',
        ]);

        $response->assertStatus(401);
    }
}
