<?php

namespace App\Tests\Unit\Service;

use App\Dto\CreateTaskInput;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\TaskService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TaskServiceTest extends TestCase
{
    private TaskService $service;
    private ValidatorInterface&Stub $validator;
    private EntityManagerInterface&Stub $em;
    private EventDispatcherInterface&Stub $dispatcher;

    protected function setUp(): void
    {
        $this->validator  = $this->createStub(ValidatorInterface::class);
        $this->em         = $this->createStub(EntityManagerInterface::class);
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->service    = new TaskService($this->validator, $this->em, $this->dispatcher);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('$2y$04$hashed');

        return $user;
    }

    #[Test]
    public function creates_task_with_title_and_defaults(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $input        = new CreateTaskInput();
        $input->title = 'Comprar leite';

        $task = $this->service->create($input, $this->makeUser());

        $this->assertSame('Comprar leite', $task->getTitle());
        $this->assertSame('inbox', $task->getStatus());
        $this->assertSame('medium', $task->getPriority());
    }

    #[Test]
    public function task_is_associated_with_user(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $input        = new CreateTaskInput();
        $input->title = 'Minha tarefa';
        $user         = $this->makeUser();

        $task = $this->service->create($input, $user);

        $this->assertSame($user, $task->getUser());
    }

    #[Test]
    public function empty_title_throws_validation_exception(): void
    {
        $violations = $this->createStub(\Symfony\Component\Validator\ConstraintViolationListInterface::class);
        $violations->method('count')->willReturn(1);

        $this->validator->method('validate')->willReturn($violations);

        $input        = new CreateTaskInput();
        $input->title = '';

        $this->expectException(ValidationException::class);
        $this->service->create($input, $this->makeUser());
    }

    #[Test]
    public function creates_task_with_all_optional_fields(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $input                   = new CreateTaskInput();
        $input->title            = 'Tarefa completa';
        $input->description      = 'Uma descrição detalhada';
        $input->status           = 'in_progress';
        $input->priority         = 'high';
        $input->estimatedMinutes = 90;
        $input->dueDate          = '2026-12-31T23:59:59+00:00';

        $task = $this->service->create($input, $this->makeUser());

        $this->assertSame('Tarefa completa', $task->getTitle());
        $this->assertSame('Uma descrição detalhada', $task->getDescription());
        $this->assertSame('in_progress', $task->getStatus());
        $this->assertSame('high', $task->getPriority());
        $this->assertSame(90, $task->getEstimatedMinutes());
        $this->assertNotNull($task->getDueDate());
    }

    #[Test]
    public function invalid_due_date_throws_validation_exception(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $input          = new CreateTaskInput();
        $input->title   = 'Tarefa';
        $input->dueDate = 'not-a-date-at-all';

        $this->expectException(ValidationException::class);
        $this->service->create($input, $this->makeUser());
    }
}
