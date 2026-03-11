<?php

namespace App\Controller\Task;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\TaskService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Tasks')]
#[OA\Delete(
    path: '/api/tasks/{id}',
    summary: 'Delete a task',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    responses: [
        new OA\Response(response: 204, description: 'Task deleted'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Task not found'),
    ]
)]
class DeleteTaskController extends AbstractController
{
    public function __construct(private readonly TaskService $taskService) {}

    public function __invoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->taskService->delete($id, $user);

        return ApiResponse::noContent();
    }
}
