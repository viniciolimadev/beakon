<?php

namespace App\Controller\Gamification;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Service\AchievementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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
