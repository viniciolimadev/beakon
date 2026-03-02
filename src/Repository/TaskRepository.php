<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findByUserWithFilters(
        User $user,
        ?string $status = null,
        ?string $priority = null,
        ?string $dueDate = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        if ($priority !== null) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority);
        }

        if ($dueDate !== null) {
            $start = new \DateTimeImmutable($dueDate . 'T00:00:00+00:00');
            $end   = $start->modify('+1 day');
            $qb->andWhere('t.dueDate >= :start AND t.dueDate < :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * @return Task[]
     */
    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
