<?php

namespace App\Controller\Auth;

use App\Dto\RegisterInput;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\JwtService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly JwtService $jwtService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $input = new RegisterInput();
        $input->name = trim((string) ($payload['name'] ?? ''));
        $input->email = trim((string) ($payload['email'] ?? ''));
        $input->password = (string) ($payload['password'] ?? '');

        try {
            $user = $this->userService->register($input);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        $tokens = $this->jwtService->createTokensForUser($user);

        return ApiResponse::created([
            'token' => $tokens['access_token'],
            'user' => [
                'id' => (string) $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'xp' => $user->getXp(),
                'streakDays' => $user->getStreakDays(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]
        ], 'User registered successfully');
    }
}
