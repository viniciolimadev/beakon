<?php

namespace App\Controller\Task;

use App\Dto\ReorderTaskInput;
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
    path: '/api/tasks/{id}/reorder',
    summary: 'Reorder a task within its status group',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['order'],
            properties: [new OA\Property(property: 'order', type: 'integer', minimum: 0)]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Reordered tasks list'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Task not found'),
    ]
)]
class ReorderTaskController extends AbstractController
{
    public function __construct(private readonly TaskService $taskService) {}

    public function __invoke(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];

        $input        = new ReorderTaskInput();
        $input->order = (int) ($payload['order'] ?? 0);

        try {
            $tasks = $this->taskService->reorder($id, $input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        return ApiResponse::success(['items' => array_map($this->serialize(...), $tasks)]);
    }

    private function serialize(Task $task): array
    {
        return [
            'id'          => (string) $task->getId(),
            'title'       => $task->getTitle(),
            'status'      => $task->getStatus(),
            'priority'    => $task->getPriority(),
            'sortOrder'   => $task->getSortOrder(),
            'createdAt'   => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
