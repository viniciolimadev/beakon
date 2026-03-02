<?php

namespace App\Controller\Task;

use App\Entity\Task;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
