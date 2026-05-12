<?php

namespace Tests\Feature\Strategy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_strategy(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $payload = [
            'name' => 'Breakout Strategy',
            'description' => 'EMA breakout strategy',
            'timeframe' => '4H',
            'setup_type' => 'breakout',
            'risk_model' => 'fixed_risk',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/strategies', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('strategies', [
            'user_id' => $user->id,
            'name' => 'Breakout Strategy',
        ]);
    }

    public function test_strategy_create_requires_name(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/strategies', []);

        // dd($response->status(), $response->json());

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'name',
        ]);
    }

    public function test_guest_cannot_create_strategy(): void
    {
        $response = $this->postJson('/api/strategies', []);

        $response->assertStatus(401);
    }
}
