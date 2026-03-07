<?php

namespace App\Repository;

use App\Entity\RoutineItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoutineItem>
 */
class RoutineItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutineItem::class);
    }

    /**
     * @return RoutineItem[]
     */
    public function findActiveByUserAndDay(User $user, int $dayOfWeek): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.isActive = true')
            ->andWhere('JSON_CONTAINS(r.daysOfWeek, :day) = 1')
            ->setParameter('user', $user)
            ->setParameter('day', (string) $dayOfWeek)
            ->orderBy('r.timeOfDay', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
