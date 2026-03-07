<?php

namespace App\Controller\Routine;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class DeleteRoutineController extends AbstractController
{
    public function __construct(private readonly RoutineService $routineService)
    {
    }

    public function __invoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->routineService->delete($id, $user);

        return ApiResponse::noContent();
    }
}
