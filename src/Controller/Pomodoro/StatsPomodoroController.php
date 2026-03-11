<?php

namespace App\Controller\Pomodoro;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\PomodoroSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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
