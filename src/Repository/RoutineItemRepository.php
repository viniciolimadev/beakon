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
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            SELECT id
            FROM routine_items
            WHERE user_id = :userId
              AND is_active = true
              AND days_of_week::jsonb @> :day::jsonb
            ORDER BY time_of_day ASC
            SQL;

        $ids = $conn->fetchFirstColumn($sql, [
            'userId' => (string) $user->getId(),
            'day'    => json_encode([$dayOfWeek]),
        ]);

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('r.timeOfDay', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
