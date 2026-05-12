<?php

namespace Tests\Feature\Tag;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_tag(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $tag = Tag::create([
            'user_id' => $user->id,
            'name' => 'breakout',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/tags/' . $tag->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_guest_cannot_delete_tag(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $tag = Tag::create([
            'user_id' => $user->id,
            'name' => 'breakout',
        ]);

        $response = $this->deleteJson('/api/tags/' . $tag->id);

        $response->assertStatus(401);
    }

    public function test_user_cannot_delete_other_user_tag(): void
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $tag = Tag::create([
            'user_id' => $owner->id,
            'name' => 'breakout',
        ]);

        $response = $this->actingAs($otherUser)
            ->deleteJson('/api/tags/' . $tag->id);

        $response->assertStatus(403);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);
    }
}
