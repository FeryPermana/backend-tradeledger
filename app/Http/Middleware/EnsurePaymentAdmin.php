<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaymentAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedEmail = env('PAYMENT_ADMIN_EMAIL', 'akuntesting@gmail.com');

        if (! $request->user() || $request->user()->email !== $allowedEmail) {
            return response()->json([
                'message' => [
                    'id' => 'Akses admin payment ditolak.',
                    'en' => 'Payment admin access denied.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
