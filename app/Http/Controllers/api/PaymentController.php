<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->latest()
            ->get();

        return response()->json([
            'data' => $payments,
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = Payment::with('user')->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan') && $request->plan !== 'all') {
            $query->where('plan', $request->plan);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate($request->integer('per_page', 10));

        return response()->json($payments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:3_months,8_months,12_months'],
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $amount = match ($validated['plan']) {
            '3_months' => 60000,
            '8_months' => 150000,
            '12_months' => 250000,
        };

        $path = $request->file('proof_image')->store('payment-proofs', 'public');

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'plan' => $validated['plan'],
            'amount' => $amount,
            'method' => 'seabank',
            'proof_image' => $path,
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => [
                'id' => 'Bukti pembayaran berhasil dikirim.',
                'en' => 'Payment proof submitted successfully.',
            ],
            'data' => $payment,
        ], 201);
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => [
                    'id' => 'Payment ini sudah direview.',
                    'en' => 'This payment has already been reviewed.',
                ],
            ], 422);
        }

        DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);

            $user = $payment->user;
            $startsAt = now();

            $expiresAt = match ($payment->plan) {
                '3_months' => now()->addMonths(3),
                '8_months' => now()->addMonths(8),
                '12_months' => now()->addYear(),
                default => now()->addMonths(3),
            };

            $user->update([
                'is_premium' => true,
                'premium_plan' => $payment->plan,
                'premium_started_at' => $startsAt,
                'premium_expires_at' => $expiresAt,
            ]);
        });

        return response()->json([
            'message' => [
                'id' => 'Payment berhasil disetujui.',
                'en' => 'Payment approved successfully.',
            ],
        ]);
    }

    public function reject(Payment $payment): JsonResponse
    {
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => [
                    'id' => 'Payment ini sudah direview.',
                    'en' => 'This payment has already been reviewed.',
                ],
            ], 422);
        }

        $payment->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => [
                'id' => 'Payment ditolak.',
                'en' => 'Payment rejected.',
            ],
        ]);
    }
}
