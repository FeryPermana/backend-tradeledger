<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountLimitForFreeUser
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

        $limit = 3;
        $accountCount = $user->accounts()->count();

        if ($accountCount >= $limit) {
            return response()->json([
                'message' => [
                    'id' => 'Batas akun untuk pengguna Free sudah tercapai. Upgrade ke Pro untuk menambah akun.',
                    'en' => 'Free account limit reached. Upgrade to Pro to add more accounts.',
                ],
                'upgrade_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
