<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ActivePomodoroSessionException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('You already have an active Pomodoro session.');
    }
}
