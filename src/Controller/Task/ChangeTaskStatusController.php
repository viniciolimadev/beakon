<?php

namespace App\Controller\Task;

use App\Dto\ChangeTaskStatusInput;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\TaskService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Tasks')]
#[OA\Patch(
    path: '/api/tasks/{id}/status',
    summary: 'Change task status',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['inbox', 'today', 'in_progress', 'done']),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Status updated, XP awarded if done'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Task not found'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
class ChangeTaskStatusController extends AbstractController
{
    public function __construct(private readonly TaskService $taskService) {}

    public function __invoke(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];

        $input         = new ChangeTaskStatusInput();
        $input->status = (string) ($payload['status'] ?? '');

        try {
            $task = $this->taskService->changeStatus($id, $input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        return ApiResponse::success($this->serialize($task));
    }

    private function serialize(Task $task): array
    {
        return [
            'id'          => (string) $task->getId(),
            'title'       => $task->getTitle(),
            'status'      => $task->getStatus(),
            'priority'    => $task->getPriority(),
            'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt'   => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
