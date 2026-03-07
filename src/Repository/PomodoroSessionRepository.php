<?php

namespace App\Repository;

use App\Entity\PomodoroSession;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PomodoroSession>
 */
class PomodoroSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PomodoroSession::class);
    }

    public function findActiveByUser(User $user): ?PomodoroSession
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.finishedAt IS NULL')
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PomodoroSession[]
     */
    public function findByUserWithFilters(
        User $user,
        ?string $taskId = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.startedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($taskId !== null) {
            try {
                $taskUuid = Uuid::fromString($taskId);
                $task     = $this->getEntityManager()->find(Task::class, $taskUuid);
                if ($task !== null) {
                    $qb->andWhere('p.task = :task')->setParameter('task', $task);
                } else {
                    $qb->andWhere('1 = 0'); // nenhum resultado
                }
            } catch (\InvalidArgumentException) {
                $qb->andWhere('1 = 0');
            }
        }

        if ($dateFrom !== null) {
            $qb->andWhere('p.startedAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('p.startedAt <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByUserWithFilters(
        User $user,
        ?string $taskId = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): int {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->setParameter('user', $user);

        if ($taskId !== null) {
            try {
                $taskUuid = Uuid::fromString($taskId);
                $task     = $this->getEntityManager()->find(Task::class, $taskUuid);
                if ($task !== null) {
                    $qb->andWhere('p.task = :task')->setParameter('task', $task);
                } else {
                    $qb->andWhere('1 = 0'); // nenhum resultado
                }
            } catch (\InvalidArgumentException) {
                $qb->andWhere('1 = 0');
            }
        }

        if ($dateFrom !== null) {
            $qb->andWhere('p.startedAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('p.startedAt <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
