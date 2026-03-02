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

        $payload      = json_decode($request->getContent(), true) ?? [];
        $input        = new CreateTaskInput();
        $input->title = trim((string) ($payload['title'] ?? ''));

        try {
            $task = $this->taskService->create($input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        return ApiResponse::created([
            'id'        => (string) $task->getId(),
            'title'     => $task->getTitle(),
            'status'    => $task->getStatus(),
            'priority'  => $task->getPriority(),
            'createdAt' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 'Task created successfully');
    }
}
