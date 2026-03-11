<?php

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\AchievementRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AchievementService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AchievementRepository $achievementRepository,
        private readonly UserAchievementRepository $userAchievementRepository,
    ) {}

    /**
     * Check and unlock achievements after a task is completed.
     * Must be called AFTER XP and streak have already been updated.
     */
    public function checkAfterTaskCompleted(User $user): void
    {
        $tasksCompleted = $this->countCompletedTasks($user);

        $this->tryUnlock($user, 'first_task',  $tasksCompleted >= 1);
        $this->tryUnlock($user, 'tasks_10',    $tasksCompleted >= 10);
        $this->tryUnlock($user, 'tasks_50',    $tasksCompleted >= 50);
        $this->tryUnlock($user, 'streak_3',    $user->getStreakDays() >= 3);
        $this->tryUnlock($user, 'streak_7',    $user->getStreakDays() >= 7);
        $this->tryUnlock($user, 'streak_30',   $user->getStreakDays() >= 30);
        $this->tryUnlock($user, 'xp_100',      $user->getXp() >= 100);
        $this->tryUnlock($user, 'xp_500',      $user->getXp() >= 500);
    }

    /**
     * @return array{achievement: Achievement, userAchievement: UserAchievement}[]
     */
    public function getAchievementsWithStatus(User $user): array
    {
        $all      = $this->achievementRepository->findAll();
        $unlocked = $this->userAchievementRepository->findByUserOrderedByUnlockedAt($user);

        $unlockedMap = [];
        foreach ($unlocked as $ua) {
            $unlockedMap[(string) $ua->getAchievement()->getId()] = $ua;
        }

        $result = [];
        foreach ($all as $achievement) {
            $ua = $unlockedMap[(string) $achievement->getId()] ?? null;
            $result[] = [
                'key'         => $achievement->getAchievementKey(),
                'name'        => $achievement->getName(),
                'description' => $achievement->getDescription(),
                'xp_bonus'    => $achievement->getXpBonus(),
                'unlocked'    => $ua !== null,
                'unlocked_at' => $ua?->getUnlockedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $result;
    }

    private function tryUnlock(User $user, string $key, bool $condition): void
    {
        if (!$condition) {
            return;
        }

        $achievement = $this->achievementRepository->findOneBy(['achievementKey' => $key]);

        if ($achievement === null) {
            return;
        }

        if ($this->userAchievementRepository->isUnlocked($user, $achievement)) {
            return;
        }

        $ua = new UserAchievement();
        $ua->setUser($user);
        $ua->setAchievement($achievement);
        $ua->setUnlockedAt(new \DateTimeImmutable());

        $this->em->persist($ua);

        $user->addXp($achievement->getXpBonus());
    }

    private function countCompletedTasks(User $user): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(t.id) FROM App\Entity\Task t WHERE t.user = :user AND t.status = :status'
        )->setParameters(['user' => $user, 'status' => 'done'])
         ->getSingleScalarResult();
    }
}
