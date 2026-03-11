<?php

namespace App\Controller\Routine;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ToggleRoutineController extends AbstractController
{
    public function __construct(private readonly RoutineService $routineService)
    {
    }

    public function __invoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $item = $this->routineService->toggle($id, $user);

        return ApiResponse::success([
            'id'         => (string) $item->getId(),
            'title'      => $item->getTitle(),
            'timeOfDay'  => $item->getTimeOfDay(),
            'daysOfWeek' => $item->getDaysOfWeek(),
            'order'      => $item->getSortOrder(),
            'isActive'   => $item->isActive(),
        ], 'Routine item updated');
    }
}
