<?php

namespace App\Controller\Task;

use App\Dto\UpdateTaskInput;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UpdateTaskController extends AbstractController
{
    public function __construct(private readonly TaskService $taskService) {}

    public function __invoke(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];

        $input                   = new UpdateTaskInput();
        $input->title            = trim((string) ($payload['title'] ?? ''));
        $input->description      = array_key_exists('description', $payload) ? ($payload['description'] !== null ? (string) $payload['description'] : null) : null;
        $input->status           = (string) ($payload['status'] ?? 'inbox');
        $input->priority         = (string) ($payload['priority'] ?? 'medium');
        $input->estimatedMinutes = isset($payload['estimated_minutes']) ? (int) $payload['estimated_minutes'] : null;
        $input->dueDate          = array_key_exists('due_date', $payload) ? ($payload['due_date'] !== null ? (string) $payload['due_date'] : null) : null;
        $input->sortOrder        = (int) ($payload['sort_order'] ?? 0);

        try {
            $task = $this->taskService->update($id, $input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        return ApiResponse::success($this->serialize($task));
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
            'updatedAt'        => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
