<?php

namespace App\EventListener;

use App\Event\TaskCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class TaskCompletedListener
{
    private const XP_TABLE = [
        'low'    => 10,
        'medium' => 25,
        'high'   => 50,
    ];

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(TaskCompletedEvent $event): void
    {
        $task     = $event->task;
        $user     = $event->user;
        $priority = $task->getPriority();
        $today    = new \DateTimeImmutable('today');

        // ── Streak ────────────────────────────────────────────
        $lastActivity = $user->getLastActivityDate();

        if ($lastActivity === null || $lastActivity->format('Y-m-d') !== $today->format('Y-m-d')) {
            $yesterday = $today->modify('-1 day');

            if ($lastActivity !== null && $lastActivity->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                $user->setStreakDays($user->getStreakDays() + 1);
            } else {
                $user->setStreakDays(1);
            }

            $user->setLastActivityDate($today);
        }

        // ── XP ────────────────────────────────────────────────
        $xp = self::XP_TABLE[$priority] ?? self::XP_TABLE['medium'];

        if ($user->getStreakDays() >= 3) {
            $xp = (int) floor($xp * 1.5);
        }

        $user->addXp($xp);
        $this->em->flush();
    }
}
