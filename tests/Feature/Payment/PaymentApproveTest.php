<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_payment(): void
    {
        $user = User::create([
            'name' => 'Akun Testing',
            'email' => 'akuntesting@gmail.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan' => '3_months',
            'amount' => 60000,
            'method' => 'seabank',
            'proof_image' => 'payment-proofs/test.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/admin/payments/' . $payment->id . '/approve');

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_premium' => true,
            'premium_plan' => '3_months',
        ]);
    }

    public function test_cannot_approve_already_reviewed_payment(): void
    {
        $user = User::create([
            'name' => 'Ferry',
            'email' => 'ferry@example.com',
            'password' => bcrypt('password123'),
            'base_currency' => 'IDR',
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan' => '3_months',
            'amount' => 60000,
            'method' => 'seabank',
            'proof_image' => 'payment-proofs/test.jpg',
            'status' => 'approved',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/admin/payments/' . $payment->id . '/approve');

        $response->assertStatus(403);
    }
}
