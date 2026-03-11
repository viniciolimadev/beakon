<?php

namespace App\Service;

use App\Dto\CreateRoutineInput;
use App\Entity\RoutineItem;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RoutineService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(CreateRoutineInput $input, User $user): RoutineItem
    {
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $item = new RoutineItem();
        $item->setTitle($input->title);
        $item->setTimeOfDay($input->timeOfDay);
        $item->setDaysOfWeek($input->daysOfWeek);
        $item->setSortOrder($input->order);
        $item->setUser($user);

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }
}
