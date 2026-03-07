<?php

namespace App\Controller\Routine;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Routines')]
#[OA\Patch(
    path: '/api/routines/{id}/toggle',
    summary: 'Toggle routine item active/inactive',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    responses: [
        new OA\Response(response: 200, description: 'Routine item updated'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Routine item not found'),
    ]
)]
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
