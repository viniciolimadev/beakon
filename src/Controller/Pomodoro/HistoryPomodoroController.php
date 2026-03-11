<?php

namespace App\Controller\Pomodoro;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\PomodoroSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class HistoryPomodoroController extends AbstractController
{
    public function __construct(private readonly PomodoroSessionRepository $sessionRepository)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $taskId   = $request->query->get('task_id');
        $dateFrom = $this->parseDate($request->query->get('date_from'));
        $dateTo   = $this->parseDate($request->query->get('date_to'));
        $page     = max(1, (int) $request->query->get('page', 1));
        $perPage  = min(100, max(1, (int) $request->query->get('per_page', 20)));

        $sessions = $this->sessionRepository->findByUserWithFilters($user, $taskId, $dateFrom, $dateTo, $page, $perPage);
        $total    = $this->sessionRepository->countByUserWithFilters($user, $taskId, $dateFrom, $dateTo);

        $data = array_map(fn ($s) => [
            'id'              => (string) $s->getId(),
            'taskId'          => (string) $s->getTask()->getId(),
            'startedAt'       => $s->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finishedAt'      => $s->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'completed'       => $s->getCompleted(),
            'durationMinutes' => $s->getDurationMinutes(),
        ], $sessions);

        $response              = ApiResponse::success($data, 'Pomodoro history');
        $responseData          = json_decode($response->getContent(), true);
        $responseData['meta']  = ['total' => $total, 'page' => $page, 'perPage' => $perPage];

        return new \Symfony\Component\HttpFoundation\JsonResponse($responseData);
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed ?: null;
    }
}
