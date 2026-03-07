<?php

namespace App\Controller\Pomodoro;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\PomodoroSessionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Pomodoro')]
#[OA\Get(
    path: '/api/pomodoro/history',
    summary: 'List Pomodoro session history with filters',
    parameters: [
        new OA\Parameter(name: 'task_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated session history'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
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
