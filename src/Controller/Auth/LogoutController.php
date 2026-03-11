<?php

namespace App\Controller\Auth;

use App\Http\ApiResponse;
use App\Service\JwtService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Auth')]
#[OA\Post(
    path: '/api/auth/logout',
    summary: 'Logout and invalidate refresh token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [new OA\Property(property: 'refresh_token', type: 'string')]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Logged out successfully'),
    ]
)]
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
