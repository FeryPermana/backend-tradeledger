<?php

namespace Tests\Feature\Strategy;

use App\Models\Strategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_strategy_list(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        Strategy::create([
            'user_id' => $user->id,
            'name' => 'Breakout Strategy',
            'description' => 'EMA breakout strategy',
            'timeframe' => '4H',
            'setup_type' => 'breakout',
            'risk_model' => 'fixed_risk',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/strategies');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'name' => 'Breakout Strategy',
        ]);
    }

    public function test_guest_cannot_access_strategy_list(): void
    {
        $response = $this->getJson('/api/strategies');

        $response->assertStatus(401);
    }
}
