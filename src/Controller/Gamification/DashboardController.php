<?php

namespace App\Controller\Gamification;

use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\AchievementRepository;
use App\Repository\PomodoroSessionRepository;
use App\Repository\UserAchievementRepository;
use App\Service\AchievementService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: 'Gamification')]
#[OA\Get(
    path: '/api/gamification/dashboard',
    summary: 'Get gamification dashboard summary',
    responses: [
        new OA\Response(response: 200, description: 'XP, streak, achievements, tasks today, minutes today, recent achievements'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AchievementRepository $achievementRepository,
        private readonly UserAchievementRepository $userAchievementRepository,
        private readonly PomodoroSessionRepository $pomodoroRepository,
        private readonly AchievementService $achievementService,
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user      = $this->getUser();
        $todayStart = new \DateTimeImmutable('today');

        $tasksCompletedToday = (int) $this->em->createQuery(
            'SELECT COUNT(t.id) FROM App\Entity\Task t
             WHERE t.user = :user AND t.status = :status AND t.completedAt >= :today'
        )->setParameters(['user' => $user, 'status' => 'done', 'today' => $todayStart])
         ->getSingleScalarResult();

        $pomodoroStats       = $this->pomodoroRepository->getStatsForUser($user);
        $minutesFocusedToday = $pomodoroStats['minutesToday'];

        $totalAchievements    = $this->achievementRepository->count([]);
        $unlockedAchievements = $this->userAchievementRepository->count(['user' => $user]);

        $recentUnlocked = $this->userAchievementRepository->findByUserOrderedByUnlockedAt($user, 3);
        $recentData     = array_map(fn($ua) => [
            'key'         => $ua->getAchievement()->getAchievementKey(),
            'name'        => $ua->getAchievement()->getName(),
            'xp_bonus'    => $ua->getAchievement()->getXpBonus(),
            'unlocked_at' => $ua->getUnlockedAt()->format(\DateTimeInterface::ATOM),
        ], $recentUnlocked);

        return ApiResponse::success([
            'xp'                    => $user->getXp(),
            'streak_days'           => $user->getStreakDays(),
            'achievements_unlocked' => $unlockedAchievements,
            'achievements_total'    => $totalAchievements,
            'tasks_completed_today' => $tasksCompletedToday,
            'minutes_focused_today' => $minutesFocusedToday,
            'recent_achievements'   => $recentData,
        ]);
    }
}
