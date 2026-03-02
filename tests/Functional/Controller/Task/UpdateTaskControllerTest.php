<?php

namespace App\Tests\Functional\Controller\Task;

use App\Dto\CreateTaskInput;
use App\Dto\RegisterInput;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UpdateTaskControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'update_task_test@example.com';
    private string $accessToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Update User';
        $input->email    = $this->testEmail;
        $input->password = 'secret123';
        $userService->register($input);

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $this->testEmail, 'password' => 'secret123'])
        );

        $this->accessToken = json_decode($this->client->getResponse()->getContent(), true)['data']['access_token'];
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\Task t WHERE t.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :e)')
            ->execute(['e' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\Task t WHERE t.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :e)')
            ->execute(['e' => 'other_update@example.com']);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_update@example.com']]);

        parent::tearDown();
    }

    private function createTaskViaService(array $overrides = []): Task
    {
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $taskService  = static::getContainer()->get(TaskService::class);
        $input        = new CreateTaskInput();
        $input->title = $overrides['title'] ?? 'Tarefa original';

        if (isset($overrides['description'])) {
            $input->description = $overrides['description'];
        }
        if (isset($overrides['due_date'])) {
            $input->dueDate = $overrides['due_date'];
        }

        return $taskService->create($input, $user);
    }

    private function updateTask(string $id, array $payload, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        } elseif ($token === null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken;
        }

        $this->client->request('PUT', '/api/tasks/' . $id, [], [], $headers, json_encode($payload));

        return $this->client->getResponse();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'    => 'Título atualizado',
            'status'   => 'in_progress',
            'priority' => 'high',
        ], $overrides);
    }

    // ── retorno básico ────────────────────────────────────────

    public function test_update_returns_200(): void
    {
        $task = $this->createTaskViaService();

        $response = $this->updateTask((string) $task->getId(), $this->validPayload());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_update_response_has_updated_data(): void
    {
        $task = $this->createTaskViaService();

        $body = json_decode(
            $this->updateTask((string) $task->getId(), $this->validPayload(['title' => 'Novo título']))->getContent(),
            true
        );

        $this->assertSame('Novo título', $body['data']['title']);
        $this->assertSame('in_progress', $body['data']['status']);
        $this->assertSame('high', $body['data']['priority']);
    }

    public function test_update_persists_changes(): void
    {
        $task = $this->createTaskViaService();
        $id   = (string) $task->getId();

        $this->updateTask($id, $this->validPayload(['title' => 'Persistido']));

        /** @var EntityManagerInterface $em */
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updated = $em->getRepository(Task::class)->find($task->getId());

        $this->assertSame('Persistido', $updated->getTitle());
        $this->assertSame('in_progress', $updated->getStatus());
    }

    public function test_update_clears_nullable_fields(): void
    {
        $task = $this->createTaskViaService([
            'description' => 'Original description',
            'due_date'    => '2026-12-31T12:00:00+00:00',
        ]);

        $this->updateTask((string) $task->getId(), $this->validPayload([
            'description' => null,
            'due_date'    => null,
        ]));

        /** @var EntityManagerInterface $em */
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updated = $em->getRepository(Task::class)->find($task->getId());

        $this->assertNull($updated->getDescription());
        $this->assertNull($updated->getDueDate());
    }

    // ── erros de negócio ─────────────────────────────────────

    public function test_update_returns_404_when_not_found(): void
    {
        $fakeId  = '00000000-0000-0000-0000-000000000000';
        $response = $this->updateTask($fakeId, $this->validPayload());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_update_returns_403_when_owned_by_other_user(): void
    {
        // cria outro usuário e uma tarefa dele
        $userService = static::getContainer()->get(UserService::class);
        $other       = new RegisterInput();
        $other->name     = 'Other';
        $other->email    = 'other_update@example.com';
        $other->password = 'secret123';
        $userService->register($other);

        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $otherUser = $em->getRepository(User::class)->findOneBy(['email' => 'other_update@example.com']);
        $taskService   = static::getContainer()->get(TaskService::class);
        $otherInput    = new CreateTaskInput();
        $otherInput->title = 'Tarefa alheia';
        $otherTask = $taskService->create($otherInput, $otherUser);

        $response = $this->updateTask((string) $otherTask->getId(), $this->validPayload());

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── validação ────────────────────────────────────────────

    public function test_update_validates_empty_title(): void
    {
        $task     = $this->createTaskViaService();
        $response = $this->updateTask((string) $task->getId(), $this->validPayload(['title' => '']));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_update_validates_invalid_status(): void
    {
        $task     = $this->createTaskViaService();
        $response = $this->updateTask((string) $task->getId(), $this->validPayload(['status' => 'invalid']));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_update_validates_invalid_priority(): void
    {
        $task     = $this->createTaskViaService();
        $response = $this->updateTask((string) $task->getId(), $this->validPayload(['priority' => 'urgent']));

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $task = $this->createTaskViaService();

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->validPayload())
        );

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
