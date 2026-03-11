<?php

namespace App\Service;

use App\Entity\PomodoroSession;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ActivePomodoroSessionException;
use App\Repository\PomodoroSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class PomodoroService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PomodoroSessionRepository $sessionRepository,
    ) {}

    public function start(string $taskId, User $user): PomodoroSession
    {
        // Valida task_id
        try {
            $uuid = Uuid::fromString($taskId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Task not found.');
        }

        /** @var Task|null $task */
        $task = $this->em->find(Task::class, $uuid);

        if ($task === null || $task->getUser() !== $user) {
            throw new NotFoundHttpException('Task not found.');
        }

        // Impede sessão duplicada ativa
        if ($this->sessionRepository->findActiveByUser($user) !== null) {
            throw new ActivePomodoroSessionException();
        }

        $session = new PomodoroSession();
        $session->setUser($user);
        $session->setTask($task);
        $session->setStartedAt(new \DateTimeImmutable());

        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    public function finish(string $id, bool $completed, User $user): PomodoroSession
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Pomodoro session not found.');
        }

        /** @var PomodoroSession|null $session */
        $session = $this->em->find(PomodoroSession::class, $uuid);

        if ($session === null || $session->getUser() !== $user) {
            throw new NotFoundHttpException('Pomodoro session not found.');
        }

        $finishedAt = new \DateTimeImmutable();
        $duration   = (int) round(($finishedAt->getTimestamp() - $session->getStartedAt()->getTimestamp()) / 60);

        $session->setFinishedAt($finishedAt);
        $session->setCompleted($completed);
        $session->setDurationMinutes($duration);

        $this->em->flush();

        return $session;
    }
}
