<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ApiAuthResponseService
{

    public function authSuccess(string $messageId, string $messageEn, mixed $user, string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => [
                'id' => $messageId,
                'en' => $messageEn,
            ],
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], $status);
    }

    public function success(string $messageId, string $messageEn, mixed $data = null, int $status = 200): JsonResponse
    {
        $response = [
            'message' => [
                'id' => $messageId,
                'en' => $messageEn,
            ],
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    public function error(string $messageId, string $messageEn, mixed $errors = null, int $status = 401): JsonResponse
    {
        $response = [
            'message' => [
                'id' => $messageId,
                'en' => $messageEn,
            ],
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
