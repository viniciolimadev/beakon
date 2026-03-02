<?php

namespace App\Controller\Auth;

use App\Exception\InvalidRefreshTokenException;
use App\Http\ApiResponse;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RefreshController extends AbstractController
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload      = json_decode($request->getContent(), true) ?? [];
        $refreshToken = (string) ($payload['refresh_token'] ?? '');

        try {
            $tokens = $this->jwtService->refreshAccessToken($refreshToken);
        } catch (InvalidRefreshTokenException) {
            return ApiResponse::unauthorized('Invalid or expired refresh token.');
        }

        return ApiResponse::success($tokens);
    }
}
