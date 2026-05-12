<?php

namespace Tests\Feature\Strategy;

use App\Models\Strategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_strategy_detail(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $strategy = Strategy::create([
            'user_id' => $user->id,
            'name' => 'Breakout Strategy',
            'description' => 'EMA breakout',
            'timeframe' => '4H',
            'setup_type' => 'breakout',
            'risk_model' => 'fixed_risk',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/strategies/' . $strategy->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'name' => 'Breakout Strategy',
        ]);
    }

    public function test_guest_cannot_view_strategy_detail(): void
    {
        $response = $this->getJson('/api/strategies/1');

        $response->assertStatus(401);
    }
}
