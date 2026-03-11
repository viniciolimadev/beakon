<?php

namespace App\Controller\Pomodoro;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\PomodoroService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FinishPomodoroController extends AbstractController
{
    public function __construct(private readonly PomodoroService $pomodoroService)
    {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload   = json_decode($request->getContent(), true) ?? [];
        $completed = (bool) ($payload['completed'] ?? false);

        $session = $this->pomodoroService->finish($id, $completed, $user);

        return ApiResponse::success([
            'id'              => (string) $session->getId(),
            'taskId'          => (string) $session->getTask()->getId(),
            'startedAt'       => $session->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finishedAt'      => $session->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'completed'       => $session->getCompleted(),
            'durationMinutes' => $session->getDurationMinutes(),
        ], 'Pomodoro session finished');
    }
}
