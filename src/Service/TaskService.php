<?php

namespace App\Service;

use App\Dto\CreateTaskInput;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
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
        $task->setUser($user);

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }
}
