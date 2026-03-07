<?php

namespace App\Controller\Gamification;

use App\Entity\User;
use App\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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
