<?php

namespace App\Controller\Auth;

use App\Dto\RegisterInput;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\JwtService;
use App\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Auth')]
#[OA\Post(
    path: '/api/auth/register',
    summary: 'Register a new user',
    security: [],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'secret123'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'User registered successfully'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
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

        return ApiResponse::created([
            'id'        => (string) $user->getId(),
            'name'      => $user->getName(),
            'email'     => $user->getEmail(),
            'xp'        => $user->getXp(),
            'streakDays' => $user->getStreakDays(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 'User registered successfully');
    }
}
