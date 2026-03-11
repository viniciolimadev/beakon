<?php

namespace App\Controller\Routine;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\RoutineItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class TodayRoutineController extends AbstractController
{
    public function __construct(private readonly RoutineItemRepository $routineItemRepository)
    {
    }

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $dayOfWeek = (int) (new \DateTimeImmutable())->format('w'); // 0=Sun, 6=Sat

        $items = $this->routineItemRepository->findActiveByUserAndDay($user, $dayOfWeek);

        $data = array_map(fn ($item) => [
            'id'         => (string) $item->getId(),
            'title'      => $item->getTitle(),
            'timeOfDay'  => $item->getTimeOfDay(),
            'daysOfWeek' => $item->getDaysOfWeek(),
            'order'      => $item->getSortOrder(),
            'isActive'   => $item->isActive(),
        ], $items);

        return ApiResponse::success($data, 'Today\'s routine items');
    }
}
