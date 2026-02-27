<?php

namespace App\Service;

use App\Dto\LoginInput;
use App\Dto\RegisterInput;
use App\Entity\User;
use App\Exception\InvalidCredentialsException;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {}

    public function register(RegisterInput $input): User
    {
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        if ($this->userRepository->findByEmail($input->email) !== null) {
            $list = new ConstraintViolationList([
                new ConstraintViolation(
                    message: 'This email is already registered.',
                    messageTemplate: '',
                    parameters: [],
                    root: $input,
                    propertyPath: 'email',
                    invalidValue: $input->email,
                ),
            ]);
            throw new ValidationException($list);
        }

        $user = new User();
        $user->setName($input->name);
        $user->setEmail($input->email);
        $user->setPassword($this->hasher->hashPassword($user, $input->password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function verifyCredentials(LoginInput $input): User
    {
        $user = $this->userRepository->findByEmail($input->email);

        if ($user === null || !$this->hasher->isPasswordValid($user, $input->password)) {
            throw new InvalidCredentialsException();
        }

        return $user;
    }
}
