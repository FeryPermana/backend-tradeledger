<?php

namespace Tests\Feature\Tag;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_tag(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/tags', [
                'name' => 'breakout',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'name' => 'breakout',
        ]);
    }

    public function test_tag_create_requires_name(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/tags', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'name',
        ]);
    }

    public function test_tag_create_rejects_duplicate_name_for_same_user(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        Tag::create([
            'user_id' => $user->id,
            'name' => 'breakout',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/tags', [
                'name' => 'breakout',
            ]);

        $response->assertStatus(422);

        $response->assertJsonFragment([
            'en' => 'Tag name already exists.',
        ]);
    }

    public function test_guest_cannot_create_tag(): void
    {
        $response = $this->postJson('/api/tags', [
            'name' => 'breakout',
        ]);

        $response->assertStatus(401);
    }
}
