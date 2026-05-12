<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'base_currency' => 'IDR',
        ]);

        $response->assertStatus(201);

        $response->assertJsonStructure([
            'message',
            'user',
            'access_token',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'base_currency' => 'IDR',
        ]);
    }

    public function test_register_requires_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'name',
            'email',
            'password',
            'base_currency',
        ]);
    }

    public function test_register_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ferry Test',
            'email' => 'email-salah',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'base_currency' => 'IDR',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'base_currency' => 'IDR',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ferry Duplicate',
            'email' => 'ferrytest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'base_currency' => 'IDR',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'passwordbeda',
            'base_currency' => 'IDR',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_invalid_base_currency(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ferry Test',
            'email' => 'ferrytest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'base_currency' => 'XXX',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['base_currency']);
    }
}
