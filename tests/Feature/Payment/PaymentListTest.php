<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_payment_list(): void
    {
        $user = User::create([
            'name' => 'Akun Testing',
            'email' => 'akuntesting@gmail.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        Payment::create([
            'user_id' => $user->id,
            'plan' => '3_months',
            'amount' => 60000,
            'method' => 'seabank',
            'proof_image' => 'payment-proofs/test.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/payments');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'plan' => '3_months',
            'status' => 'pending',
        ]);
    }

    public function test_guest_cannot_access_payment_list(): void
    {
        $response = $this->getJson('/api/payments');

        $response->assertStatus(401);
    }
}
