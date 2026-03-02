<?php

namespace App\Controller\Auth;

use App\Http\ApiResponse;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class LogoutController extends AbstractController
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload      = json_decode($request->getContent(), true) ?? [];
        $refreshToken = (string) ($payload['refresh_token'] ?? '');

        $this->jwtService->logout($refreshToken);

        return ApiResponse::success(null, 'Logged out successfully.');
    }
}
