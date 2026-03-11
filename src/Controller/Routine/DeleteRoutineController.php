<?php

namespace App\Controller\Routine;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Routines')]
#[OA\Delete(
    path: '/api/routines/{id}',
    summary: 'Delete a routine item',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    responses: [
        new OA\Response(response: 204, description: 'Routine item deleted'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Routine item not found'),
    ]
)]
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
