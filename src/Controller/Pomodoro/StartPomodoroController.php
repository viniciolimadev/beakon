<?php

namespace App\Controller\Pomodoro;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\PomodoroService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Pomodoro')]
#[OA\Post(
    path: '/api/pomodoro/start',
    summary: 'Start a Pomodoro session for a task',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['task_id'],
            properties: [new OA\Property(property: 'task_id', type: 'string', format: 'uuid')]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Pomodoro session started'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 404, description: 'Task not found'),
        new OA\Response(response: 409, description: 'Active session already exists'),
    ]
)]
class StartPomodoroController extends AbstractController
{
    public function __construct(private readonly PomodoroService $pomodoroService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $taskId  = trim((string) ($payload['task_id'] ?? ''));

        if ($taskId === '') {
            return ApiResponse::validationError(
                new \Symfony\Component\Validator\ConstraintViolationList([
                    new \Symfony\Component\Validator\ConstraintViolation(
                        'task_id is required.',
                        '',
                        [],
                        null,
                        'task_id',
                        ''
                    ),
                ])
            );
        }

        $session = $this->pomodoroService->start($taskId, $user);

        return ApiResponse::created([
            'id'              => (string) $session->getId(),
            'taskId'          => (string) $session->getTask()->getId(),
            'startedAt'       => $session->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finishedAt'      => null,
            'completed'       => null,
            'durationMinutes' => null,
        ], 'Pomodoro session started');
    }
}
