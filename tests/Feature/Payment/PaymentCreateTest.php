<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_payment(): void
    {
        Storage::fake('public');

         $user = User::create([
            'name' => 'Akun Testing',
            'email' => 'akuntesting@gmail.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $payload = [
            'plan' => '3_months',
            'proof_image' => UploadedFile::fake()->create('payment.jpg', 100, 'image/jpeg'),
        ];

        $response = $this->actingAs($user)
            ->post('/api/payments', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'plan' => '3_months',
            'amount' => 60000,
            'status' => 'pending',
        ]);
    }

    public function test_payment_requires_proof_image(): void
    {
         $user = User::create([
            'name' => 'Akun Testing',
            'email' => 'akuntesting@gmail.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments', [
                'plan' => '3_months',
            ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'proof_image',
        ]);
    }

    public function test_guest_cannot_create_payment(): void
    {
        $response = $this->postJson('/api/payments', []);

        $response->assertStatus(401);
    }
}
