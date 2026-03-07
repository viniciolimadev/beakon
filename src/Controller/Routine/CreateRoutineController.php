<?php

namespace App\Controller\Routine;

use App\Dto\CreateRoutineInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag(name: 'Routines')]
#[OA\Post(
    path: '/api/routines',
    summary: 'Create a routine item',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'time_of_day', 'days_of_week'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Morning workout'),
                new OA\Property(property: 'time_of_day', type: 'string', example: '07:00'),
                new OA\Property(property: 'days_of_week', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3, 4, 5]),
                new OA\Property(property: 'order', type: 'integer', default: 0),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Routine item created'),
        new OA\Response(response: 401, description: 'Unauthorized'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
class CreateRoutineController extends AbstractController
{
    public function __construct(private readonly RoutineService $routineService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];

        $input              = new CreateRoutineInput();
        $input->title       = trim((string) ($payload['title'] ?? ''));
        $input->timeOfDay   = trim((string) ($payload['time_of_day'] ?? ''));
        $input->daysOfWeek  = is_array($payload['days_of_week'] ?? null) ? $payload['days_of_week'] : [];
        $input->order       = isset($payload['order']) ? (int) $payload['order'] : 0;

        try {
            $item = $this->routineService->create($input, $user);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getViolations());
        }

        return ApiResponse::created([
            'id'          => (string) $item->getId(),
            'title'       => $item->getTitle(),
            'timeOfDay'   => $item->getTimeOfDay(),
            'daysOfWeek'  => $item->getDaysOfWeek(),
            'order'       => $item->getSortOrder(),
            'isActive'    => $item->isActive(),
            'createdAt'   => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 'Routine item created successfully');
    }
}
