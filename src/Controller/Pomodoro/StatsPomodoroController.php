<?php

namespace App\Controller\Pomodoro;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\PomodoroSessionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Pomodoro')]
#[OA\Get(
    path: '/api/pomodoro/stats',
    summary: 'Get Pomodoro statistics for the authenticated user',
    responses: [
        new OA\Response(response: 200, description: 'Minutes today/week/month, sessions completed/interrupted, avg sessions/day'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
class StatsPomodoroController extends AbstractController
{
    public function __construct(private readonly PomodoroSessionRepository $sessionRepository)
    {
    }

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $stats = $this->sessionRepository->getStatsForUser($user);

        return ApiResponse::success($stats, 'Pomodoro stats');
    }
}
