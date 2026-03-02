<?php

namespace App\Service;

use App\Dto\CreateTaskInput;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TaskService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(CreateTaskInput $input, User $user): Task
    {
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $task = new Task();
        $task->setTitle($input->title);
        $task->setStatus($input->status);
        $task->setPriority($input->priority);
        $task->setUser($user);

        if ($input->description !== null) {
            $task->setDescription($input->description);
        }

        if ($input->estimatedMinutes !== null) {
            $task->setEstimatedMinutes($input->estimatedMinutes);
        }

        if ($input->dueDate !== null) {
            $dueDate = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $input->dueDate)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', $input->dueDate)
                ?: false;

            if ($dueDate === false) {
                $list = new ConstraintViolationList([
                    new ConstraintViolation(
                        'Invalid date format. Use ISO 8601 (e.g. 2026-03-15T10:00:00+00:00).',
                        '',
                        [],
                        $input,
                        'dueDate',
                        $input->dueDate,
                    ),
                ]);
                throw new ValidationException($list);
            }

            $task->setDueDate($dueDate);
        }

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }
}
