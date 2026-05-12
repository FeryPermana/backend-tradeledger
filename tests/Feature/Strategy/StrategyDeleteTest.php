<?php

namespace Tests\Feature\Strategy;

use App\Models\Strategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_strategy(): void
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
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/strategies/' . $strategy->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('strategies', [
            'id' => $strategy->id,
        ]);
    }

    public function test_guest_cannot_delete_strategy(): void
    {
        $response = $this->deleteJson('/api/strategies/1');

        $response->assertStatus(401);
    }
}
