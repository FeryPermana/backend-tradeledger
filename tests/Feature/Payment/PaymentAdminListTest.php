<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAdminListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_payment_admin_list(): void
    {
        $user = User::create([
            'name' => 'Akun Testing',
            'email' => 'akuntesting@gmail.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        Payment::create([
            'user_id' => $user->id,
            'plan' => '12_months',
            'amount' => 250000,
            'method' => 'seabank',
            'proof_image' => 'payment-proofs/test.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/payments');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'plan' => '12_months',
            'status' => 'pending',
        ]);
    }
}
