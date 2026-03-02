<?php

namespace App\EventListener;

use App\Event\TaskCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class TaskCompletedListener
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(TaskCompletedEvent $event): void
    {
        $event->user->addXp(10);
        $this->em->flush();
    }
}
