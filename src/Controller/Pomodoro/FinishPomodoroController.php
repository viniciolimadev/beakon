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
#[OA\Patch(
    path: '/api/pomodoro/{id}/finish',
    summary: 'Finish a Pomodoro session',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'completed', type: 'boolean', default: false)]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Session finished with duration'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 404, description: 'Session not found'),
    ]
)]
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
