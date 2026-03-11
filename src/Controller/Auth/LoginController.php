<?php

namespace App\Controller\Auth;

use App\Dto\LoginInput;
use App\Exception\InvalidCredentialsException;
use App\Http\ApiResponse;
use App\Service\JwtService;
use App\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Auth')]
#[OA\Post(
    path: '/api/auth/login',
    summary: 'Authenticate user and get JWT token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'secret123'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Returns JWT access_token and user data'),
        new OA\Response(response: 401, description: 'Invalid credentials'),
    ]
)]
class LoginController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly JwtService $jwtService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $input = new LoginInput();
        $input->email = trim((string) ($payload['email'] ?? ''));
        $input->password = (string) ($payload['password'] ?? '');

        try {
            $user = $this->userService->verifyCredentials($input);
            $tokens = $this->jwtService->createTokensForUser($user);
        } catch (InvalidCredentialsException) {
            return ApiResponse::unauthorized('Invalid credentials.');
        }

        return ApiResponse::success([
            'access_token' => $tokens['access_token'],
            'user' => [
                'id' => (string) $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'xp' => $user->getXp(),
                'streakDays' => $user->getStreakDays(),
            ]
        ]);
    }
}
