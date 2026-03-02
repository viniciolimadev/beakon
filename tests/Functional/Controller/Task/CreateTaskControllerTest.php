<?php

namespace App\Tests\Functional\Controller\Task;

use App\Dto\RegisterInput;
use App\Entity\Task;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CreateTaskControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail    = 'task_test@example.com';
    private string $accessToken  = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Task User';
        $input->email    = $this->testEmail;
        $input->password = 'secret123';
        $userService->register($input);

        // login para obter o access_token
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $this->testEmail, 'password' => 'secret123'])
        );

        $data               = json_decode($this->client->getResponse()->getContent(), true);
        $this->accessToken  = $data['data']['access_token'];
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\Task t WHERE t.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    private function createTask(array $payload, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('POST', '/api/tasks', [], [], $headers, json_encode($payload));

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_create_task_returns_201(): void
    {
        $response = $this->createTask(['title' => 'Comprar leite'], $this->accessToken);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_create_task_response_structure(): void
    {
        $response = $this->createTask(['title' => 'Estudar PHP'], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertArrayHasKey('title', $body['data']);
        $this->assertArrayHasKey('status', $body['data']);
        $this->assertArrayHasKey('priority', $body['data']);
        $this->assertArrayHasKey('createdAt', $body['data']);
    }

    public function test_default_status_is_inbox(): void
    {
        $response = $this->createTask(['title' => 'Inbox task'], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame('inbox', $body['data']['status']);
    }

    public function test_default_priority_is_medium(): void
    {
        $response = $this->createTask(['title' => 'Medium task'], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame('medium', $body['data']['priority']);
    }

    public function test_title_is_persisted(): void
    {
        $this->createTask(['title' => 'Tarefa persistida'], $this->accessToken);

        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $task = $em->getRepository(Task::class)->findOneBy(['title' => 'Tarefa persistida']);

        $this->assertNotNull($task);
        $this->assertSame('Tarefa persistida', $task->getTitle());
    }

    // ── validação ─────────────────────────────────────────────

    public function test_empty_title_returns_422(): void
    {
        $response = $this->createTask(['title' => ''], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_missing_title_returns_422(): void
    {
        $response = $this->createTask([], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->createTask(['title' => 'Sem token']);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ── tarefa completa (US-10) ───────────────────────────────

    public function test_create_task_with_all_fields_returns_201(): void
    {
        $response = $this->createTask([
            'title'             => 'Tarefa completa',
            'description'       => 'Detalhes da tarefa',
            'status'            => 'in_progress',
            'priority'          => 'high',
            'estimated_minutes' => 90,
            'due_date'          => '2026-12-31T23:59:59+00:00',
        ], $this->accessToken);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_response_includes_all_optional_fields(): void
    {
        $response = $this->createTask([
            'title'             => 'Com opcionais',
            'description'       => 'Desc',
            'estimated_minutes' => 30,
            'due_date'          => '2026-06-01T00:00:00+00:00',
        ], $this->accessToken);

        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('description', $body['data']);
        $this->assertArrayHasKey('estimatedMinutes', $body['data']);
        $this->assertArrayHasKey('dueDate', $body['data']);
        $this->assertSame('Desc', $body['data']['description']);
        $this->assertSame(30, $body['data']['estimatedMinutes']);
        $this->assertNotNull($body['data']['dueDate']);
    }

    public function test_null_optional_fields_appear_in_response(): void
    {
        $response = $this->createTask(['title' => 'Só título'], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('description', $body['data']);
        $this->assertArrayHasKey('estimatedMinutes', $body['data']);
        $this->assertArrayHasKey('dueDate', $body['data']);
        $this->assertNull($body['data']['description']);
        $this->assertNull($body['data']['estimatedMinutes']);
        $this->assertNull($body['data']['dueDate']);
    }

    public function test_invalid_status_returns_422(): void
    {
        $response = $this->createTask([
            'title'  => 'Status inválido',
            'status' => 'urgente',
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_invalid_priority_returns_422(): void
    {
        $response = $this->createTask([
            'title'    => 'Priority inválida',
            'priority' => 'crítica',
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_invalid_due_date_returns_422(): void
    {
        $response = $this->createTask([
            'title'    => 'Data inválida',
            'due_date' => 'ontem à tarde',
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }
}
