<?php

namespace App\Controller\Task;

use App\Dto\CreateTaskInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CreateTaskController extends AbstractController
{
    public function __construct(private readonly TaskService $taskService) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];

        $input        = new CreateTaskInput();
        $input->title = trim((string) ($payload['title'] ?? ''));

        if (isset($payload['description'])) {
            $input->description = trim((string) $payload['description']) ?: null;
        }

        if (isset($payload['status'])) {
            $input->status = (string) $payload['status'];
        }

        if (isset($payload['priority'])) {
            $input->priority = (string) $payload['priority'];
        }

        if (isset($payload['estimated_minutes'])) {
            $input->estimatedMinutes = (int) $payload['estimated_minutes'];
        }

        if (isset($payload['due_date'])) {
            $input->dueDate = (string) $payload['due_date'];
        }

        try {
            $task = $this->taskService->create($input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        return ApiResponse::created([
            'id'               => (string) $task->getId(),
            'title'            => $task->getTitle(),
            'description'      => $task->getDescription(),
            'status'           => $task->getStatus(),
            'priority'         => $task->getPriority(),
            'estimatedMinutes' => $task->getEstimatedMinutes(),
            'dueDate'          => $task->getDueDate()?->format(\DateTimeInterface::ATOM),
            'createdAt'        => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 'Task created successfully');
    }
}
