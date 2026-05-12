<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_successfully(): void
    {
        $user = User::create([
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ferrytest@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'user',
            'access_token',
        ]);

        $response->assertJsonFragment([
            'email' => 'ferrytest@example.com',
        ]);
    }

    public function test_login_requires_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'email',
            'password',
        ]);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'email-salah',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::create([
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ferrytest@example.com',
            'password' => 'password-salah',
        ]);

        $response->assertStatus(422);

        $response->assertJsonFragment([
            'en' => 'Invalid email or password.',
        ]);
    }

    public function test_login_rejects_unregistered_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);

        $response->assertJsonFragment([
            'en' => 'Invalid email or password.',
        ]);
    }
}
