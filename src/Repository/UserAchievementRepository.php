<?php

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\User;
use App\Entity\UserAchievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    public function isUnlocked(User $user, Achievement $achievement): bool
    {
        return $this->count(['user' => $user, 'achievement' => $achievement]) > 0;
    }

    /**
     * @return UserAchievement[]
     */
    public function findByUserOrderedByUnlockedAt(User $user, int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('ua')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
