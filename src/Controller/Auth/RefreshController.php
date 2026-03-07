<?php

namespace App\Controller\Auth;

use App\Exception\InvalidRefreshTokenException;
use App\Http\ApiResponse;
use App\Service\JwtService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Auth')]
#[OA\Post(
    path: '/api/auth/refresh',
    summary: 'Refresh access token using refresh token',
    security: [],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [new OA\Property(property: 'refresh_token', type: 'string')]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'New access and refresh tokens'),
        new OA\Response(response: 401, description: 'Invalid or expired refresh token'),
    ]
)]
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
