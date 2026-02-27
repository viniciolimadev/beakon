<?php

namespace App\Tests\Unit\Service;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserServiceTest extends TestCase
{
    private UserService $service;
    private UserRepository&Stub $repo;
    private UserPasswordHasherInterface&Stub $hasher;
    private EntityManagerInterface&Stub $em;
    private ValidatorInterface&Stub $validator;

    protected function setUp(): void
    {
        $this->repo      = $this->createStub(UserRepository::class);
        $this->hasher    = $this->createStub(UserPasswordHasherInterface::class);
        $this->em        = $this->createStub(EntityManagerInterface::class);
        $this->validator = $this->createStub(ValidatorInterface::class);

        $this->service = new UserService(
            $this->repo,
            $this->hasher,
            $this->em,
            $this->validator,
        );
    }

    private function validInput(): RegisterInput
    {
        $input           = new RegisterInput();
        $input->name     = 'Alice';
        $input->email    = 'alice@example.com';
        $input->password = 'secret123';

        return $input;
    }

    // ── sucesso ───────────────────────────────────────────────

    #[Test]
    public function register_returns_user_entity(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->method('findByEmail')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('$2y$12$hashed');

        $user = $this->service->register($this->validInput());

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Alice', $user->getName());
        $this->assertSame('alice@example.com', $user->getEmail());
    }

    #[Test]
    public function register_stores_hashed_password(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->method('findByEmail')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('$2y$12$hashed');

        $user = $this->service->register($this->validInput());

        $this->assertSame('$2y$12$hashed', $user->getPassword());
        $this->assertNotSame('secret123', $user->getPassword());
    }

    // ── validação ─────────────────────────────────────────────

    #[Test]
    public function invalid_input_throws_validation_exception(): void
    {
        $violation  = $this->createStub(ConstraintViolation::class);
        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $this->expectException(ValidationException::class);

        $this->service->register($this->validInput());
    }

    #[Test]
    public function duplicate_email_throws_validation_exception(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->method('findByEmail')->willReturn(new User());

        $this->expectException(ValidationException::class);

        $this->service->register($this->validInput());
    }
}
