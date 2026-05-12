<?php

namespace Tests\Feature\Strategy;

use App\Models\Strategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_strategy(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $strategy = Strategy::create([
            'user_id' => $user->id,
            'name' => 'Old Strategy',
            'description' => 'Old desc',
            'timeframe' => '1H',
            'setup_type' => 'pullback',
            'risk_model' => 'fixed_risk',
        ]);

        $payload = [
            'name' => 'Updated Strategy',
            'description' => 'Updated desc',
            'timeframe' => '4H',
            'setup_type' => 'breakout',
            'risk_model' => 'dynamic_risk',
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/strategies/' . $strategy->id, $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('strategies', [
            'id' => $strategy->id,
            'name' => 'Updated Strategy',
        ]);
    }

    public function test_guest_cannot_update_strategy(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $strategy = Strategy::create([
            'user_id' => $user->id,
            'name' => 'Old Strategy',
        ]);

        $response = $this->putJson('/api/strategies/' . $strategy->id, [
            'name' => 'Updated Strategy',
        ]);

        $response->assertStatus(401);
    }
}
