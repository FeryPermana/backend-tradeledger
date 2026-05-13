<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRejectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reject_payment(): void
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
            ->postJson('/api/admin/payments/' . $payment->id . '/reject');

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_cannot_reject_already_reviewed_payment(): void
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
            'status' => 'approved',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/admin/payments/' . $payment->id . '/reject');

        $response->assertStatus(422);
    }
}
