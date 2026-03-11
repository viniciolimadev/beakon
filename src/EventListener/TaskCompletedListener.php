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

        $xp = self::XP_TABLE[$priority] ?? self::XP_TABLE['medium'];

        if ($user->getStreakDays() >= 3) {
            $xp = (int) floor($xp * 1.5);
        }

        $user->addXp($xp);
        $this->em->flush();
    }
}
