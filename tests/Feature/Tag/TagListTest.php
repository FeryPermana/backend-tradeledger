<?php

namespace Tests\Feature\Tag;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_tag_list(): void
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
            ->getJson('/api/tags');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'name' => 'breakout',
        ]);
    }

    public function test_guest_cannot_access_tag_list(): void
    {
        $response = $this->getJson('/api/tags');

        $response->assertStatus(401);
    }
}
