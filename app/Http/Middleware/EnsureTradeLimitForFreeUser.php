<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTradeLimitForFreeUser
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

        if ($user->isPremiumActive()) {
            return $next($request);
        }

        $limit = 20;
        $tradeCount = $user->trades()->count();

        if ($tradeCount >= $limit) {
            return response()->json([
                'message' => [
                    'id' => 'Batas trade untuk pengguna Free sudah tercapai. Upgrade ke Pro untuk menambah trade.',
                    'en' => 'Free trade limit reached. Upgrade to Pro to add more trades.',
                ],
                'upgrade_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
