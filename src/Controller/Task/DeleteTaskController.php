<?php

namespace App\Controller\Task;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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
