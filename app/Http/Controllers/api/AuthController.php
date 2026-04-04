<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use App\Services\ApiAuthResponseService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected $authResponse;

    public function __construct(ApiAuthResponseService $authResponse)
    {
        $this->authResponse = $authResponse;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'base_currency' => $validated['base_currency'],
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->authResponse->authSuccess(
            'Register berhasil.',
            'Registration successful.',
            $user,
            $token,
            201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->authResponse->error(
                'Email atau password salah.',
                'Invalid email or password.',
                null,
                422
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->authResponse->authSuccess(
            'Login berhasil.',
            'Login successful.',
            $user,
            $token
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => [
                    'id' => 'Link reset password berhasil dikirim.',
                    'en' => 'Password reset link sent successfully.',
                ],
            ]);
        }

        return response()->json([
            'message' => [
                'id' => 'Gagal mengirim link reset password.',
                'en' => 'Failed to send password reset link.',
            ],
        ], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => [
                    'id' => 'Password berhasil direset.',
                    'en' => 'Password has been reset successfully.',
                ],
            ]);
        }

        return response()->json([
            'message' => [
                'id' => 'Token reset password tidak valid atau sudah kedaluwarsa.',
                'en' => 'Password reset token is invalid or expired.',
            ],
        ], 422);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->authResponse->success(
                'Email sudah terverifikasi.',
                'Email is already verified.'
            );
        }

        $user->sendEmailVerificationNotification();

        return $this->authResponse->success(
            'Link verifikasi berhasil dikirim ulang.',
            'Verification link sent successfully.'
        );
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.frontend_url') . '/email-verification/error?reason=invalid');
        }

        if (!$request->hasValidSignature()) {
            return redirect(config('app.frontend_url') . '/email-verification/error?reason=expired');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url') . '/email-verification/success');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        return response()->json([
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'max:10'],
            'price_sync_enabled' => ['nullable', 'boolean'],
            'price_sync_times' => ['nullable', 'array', 'max:2'],
            'price_sync_times.*' => ['string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $user = $request->user();

        $priceSyncEnabled = (bool) ($data['price_sync_enabled'] ?? false);
        $priceSyncTimes = $data['price_sync_times'] ?? [];

        $priceSyncTimes = collect($priceSyncTimes)
            ->filter(fn ($time) => !empty($time))
            ->map(fn ($time) => trim($time))
            ->unique()
            ->values()
            ->all();

        if (count($priceSyncTimes) > 2) {
            return $this->authResponse->error(
                'Maksimal hanya 2 jam sinkronisasi.',
                'You can only set up to 2 sync times.',
                null,
                422
            );
        }

        if ($priceSyncEnabled && empty($priceSyncTimes)) {
            return $this->authResponse->error(
                'Pilih minimal 1 jam sinkronisasi.',
                'Please select at least 1 sync time.',
                null,
                422
            );
        }

        $user->update([
            'name' => $data['name'],
            'base_currency' => strtoupper($data['base_currency']),
            'price_sync_enabled' => $priceSyncEnabled,
            'price_sync_times' => $priceSyncEnabled ? $priceSyncTimes : [],
        ]);

        return response()->json([
            'message' => [
                'id' => 'Profil berhasil diperbarui.',
                'en' => 'Profile updated successfully.',
            ],
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return $this->authResponse->error(
                'Password saat ini salah.',
                'Current password is incorrect.',
                null,
                422
            );
        }

        $user->update([
            'password' => $data['new_password'],
        ]);

        return response()->json([
            'message' => [
                'id' => 'Password berhasil diperbarui.',
                'en' => 'Password updated successfully.',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->authResponse->success(
            'Logout berhasil.',
            'Logged out successfully.'
        );
    }

    public function planStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $user->isPremiumActive();

        return response()->json([
            'data' => [
                'plan' => $isPremium ? 'pro' : 'free',
                'is_premium' => $isPremium,
                'premium_expires_at' => $user->premium_expires_at,
                'limits' => [
                    'accounts_used' => $user->accounts()->count(),
                    'accounts_limit' => $isPremium ? null : 3,
                    'trades_used' => $user->trades()->count(),
                    'trades_limit' => $isPremium ? null : 20,
                ],
            ],
        ]);
    }
}
