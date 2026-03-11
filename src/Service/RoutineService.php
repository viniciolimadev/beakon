<?php

namespace App\Service;

use App\Dto\CreateRoutineInput;
use App\Entity\RoutineItem;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
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

    public function toggle(string $id, User $user): RoutineItem
    {
        $item = $this->findOwnedOrFail($id, $user);
        $item->setIsActive(!$item->isActive());
        $this->em->flush();

        return $item;
    }

    private function findOwnedOrFail(string $id, User $user): RoutineItem
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Routine item not found.');
        }

        /** @var RoutineItem|null $item */
        $item = $this->em->find(RoutineItem::class, $uuid);

        if ($item === null) {
            throw new NotFoundHttpException('Routine item not found.');
        }

        if ($item->getUser() !== $user) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $item;
    }
}
