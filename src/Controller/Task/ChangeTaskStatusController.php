<?php

namespace App\Controller\Task;

use App\Dto\ChangeTaskStatusInput;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
