<?php

namespace App\Controller\Task;

use App\Entity\Task;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\TaskRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Tasks')]
#[OA\Get(
    path: '/api/tasks',
    summary: 'List tasks with optional filters and pagination',
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'due_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated task list'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
class ListTasksController extends AbstractController
{
    public function __construct(private readonly TaskRepository $taskRepository) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $status   = $request->query->get('status');
        $priority = $request->query->get('priority');
        $dueDate  = $request->query->get('due_date');
        $page     = max(1, (int) ($request->query->get('page') ?? 1));
        $perPage  = max(1, min(100, (int) ($request->query->get('per_page') ?? 20)));

        $result = $this->taskRepository->findByUserWithFilters(
            $user,
            $status !== '' ? $status : null,
            $priority !== '' ? $priority : null,
            $dueDate !== '' ? $dueDate : null,
            $page,
            $perPage,
        );

        $result['items'] = array_map($this->serialize(...), $result['items']);

        return ApiResponse::success($result);
    }

    private function serialize(Task $task): array
    {
        return [
            'id'               => (string) $task->getId(),
            'title'            => $task->getTitle(),
            'description'      => $task->getDescription(),
            'status'           => $task->getStatus(),
            'priority'         => $task->getPriority(),
            'estimatedMinutes' => $task->getEstimatedMinutes(),
            'dueDate'          => $task->getDueDate()?->format(\DateTimeInterface::ATOM),
            'sortOrder'        => $task->getSortOrder(),
            'createdAt'        => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
