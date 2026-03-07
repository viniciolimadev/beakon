<?php

namespace App\Controller\Routine;

use App\Dto\UpdateRoutineInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Routines')]
#[OA\Put(
    path: '/api/routines/{id}',
    summary: 'Update a routine item',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'time_of_day', 'days_of_week'],
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'time_of_day', type: 'string', example: '07:00'),
                new OA\Property(property: 'days_of_week', type: 'array', items: new OA\Items(type: 'integer')),
                new OA\Property(property: 'order', type: 'integer'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Routine item updated'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Routine item not found'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
class UpdateRoutineController extends AbstractController
{
    public function __construct(private readonly RoutineService $routineService)
    {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];

        $input             = new UpdateRoutineInput();
        $input->title      = trim((string) ($payload['title'] ?? ''));
        $input->timeOfDay  = trim((string) ($payload['time_of_day'] ?? ''));
        $input->daysOfWeek = is_array($payload['days_of_week'] ?? null) ? $payload['days_of_week'] : [];
        $input->order      = isset($payload['order']) ? (int) $payload['order'] : 0;

        try {
            $item = $this->routineService->update($id, $input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

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
