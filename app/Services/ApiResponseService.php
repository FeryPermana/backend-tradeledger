<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ApiResponseService
{
    public function success(string $messageId, string $messageEn, mixed $data = null, int $status = 200): JsonResponse
    {
        $response = [
            'message' => [
                'id' => $messageId,
                'en' => $messageEn,
            ],
        ];

        if (!is_null($data)) {
            if (is_array($data) || is_object($data)) {
                $response = array_merge($response, (array) $data);
            } else {
                $response['result'] = $data;
            }
        }

        return response()->json($response, $status);
    }

    public function error(string $messageId, string $messageEn, mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'message' => [
                'id' => $messageId,
                'en' => $messageEn,
            ],
            'errors' => $errors
        ], $status);
    }
}
