<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse
{
    /**
     * Resposta de sucesso: { "data": ..., "message": "" }
     */
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    /**
     * Resposta de criação: 201 Created
     */
    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Resposta vazia: 204 No Content
     */
    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * Resposta de erro: { "error": "...", "code": 400 }
     */
    public static function error(string $error, int $status = 400): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'code'  => $status,
        ], $status);
    }

    public static function notFound(string $error = 'Resource not found'): JsonResponse
    {
        return self::error($error, 404);
    }

    public static function unauthorized(string $error = 'Unauthorized'): JsonResponse
    {
        return self::error($error, 401);
    }

    public static function forbidden(string $error = 'Forbidden'): JsonResponse
    {
        return self::error($error, 403);
    }

    public static function unprocessable(string $error = 'Unprocessable Entity'): JsonResponse
    {
        return self::error($error, 422);
    }
}
