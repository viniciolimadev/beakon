<?php

namespace App\Event;

use App\Entity\Task;
use App\Entity\User;

final class TaskCompletedEvent
{
    public function __construct(
        public readonly Task $task,
        public readonly User $user,
    ) {}
}
