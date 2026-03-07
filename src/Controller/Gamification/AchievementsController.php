<?php

namespace App\Controller\Gamification;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\AchievementService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Gamification')]
#[OA\Get(
    path: '/api/gamification/achievements',
    summary: 'List all achievements with unlocked status for the current user',
    responses: [
        new OA\Response(response: 200, description: 'Achievement list with key, name, description, xp_bonus, unlocked, unlocked_at'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
class AchievementsController extends AbstractController
{
    public function __construct(private readonly AchievementService $achievementService) {}

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return ApiResponse::success($this->achievementService->getAchievementsWithStatus($user));
    }
}
