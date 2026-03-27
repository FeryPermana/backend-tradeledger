<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPremium
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => [
                    'id' => 'Unauthorized',
                    'en' => 'Unauthorized',
                ],
            ], 401);
        }

        if (! $user->is_premium) {
            return response()->json([
                'message' => [
                    'id' => 'Fitur ini hanya untuk pengguna Pro.',
                    'en' => 'This feature is available for Pro users only.',
                ],
                'upgrade_required' => true,
            ], 403);
        }

        if (! $user->isPremiumActive()) {
            $user->update([
                'is_premium' => false,
                'premium_plan' => null,
                'premium_started_at' => null,
                'premium_expires_at' => null,
            ]);

            return response()->json([
                'message' => [
                    'id' => 'Langganan Pro sudah berakhir.',
                    'en' => 'Pro subscription has expired.',
                ],
                'upgrade_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
