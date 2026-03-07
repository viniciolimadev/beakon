<?php

namespace App\Controller\Gamification;

use App\Entity\User;
use App\Http\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Gamification')]
#[OA\Get(
    path: '/api/gamification/streak',
    summary: 'Get current streak information',
    responses: [
        new OA\Response(response: 200, description: 'streak_days and last_activity_date'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
class StreakController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return ApiResponse::success([
            'streak_days'        => $user->getStreakDays(),
            'last_activity_date' => $user->getLastActivityDate()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
