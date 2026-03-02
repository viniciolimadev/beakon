<?php

namespace App\Controller\Auth;

use App\Dto\LoginInput;
use App\Exception\InvalidCredentialsException;
use App\Http\ApiResponse;
use App\Service\JwtService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
            'token' => $tokens['access_token'],
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
