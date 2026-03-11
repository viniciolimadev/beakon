<?php

namespace App\Controller\Routine;

use App\Dto\UpdateRoutineInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\RoutineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
