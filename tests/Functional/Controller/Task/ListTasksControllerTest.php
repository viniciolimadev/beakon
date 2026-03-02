<?php

namespace App\Tests\Functional\Controller\Task;

use App\Dto\RegisterInput;
use App\Service\TaskService;
use App\Dto\CreateTaskInput;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ListTasksControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'list_task_test@example.com';
    private string $accessToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'List User';
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
            ->execute(['e' => 'other_list@example.com']);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_list@example.com']]);

        parent::tearDown();
    }

    private function createTaskViaService(array $overrides = []): void
    {
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $taskService  = static::getContainer()->get(TaskService::class);
        $input        = new CreateTaskInput();
        $input->title = $overrides['title'] ?? 'Tarefa padrão';

        if (isset($overrides['status'])) {
            $input->status = $overrides['status'];
        }
        if (isset($overrides['priority'])) {
            $input->priority = $overrides['priority'];
        }
        if (isset($overrides['due_date'])) {
            $input->dueDate = $overrides['due_date'];
        }

        $task = $taskService->create($input, $user);

        if (isset($overrides['sort_order'])) {
            $task->setSortOrder($overrides['sort_order']);
            $em->flush();
        }
    }

    private function listTasks(array $query = []): Response
    {
        $url = '/api/tasks';
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $this->client->request('GET', $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken,
        ]);

        return $this->client->getResponse();
    }

    // ── retorno básico ────────────────────────────────────────

    public function test_list_returns_200(): void
    {
        $response = $this->listTasks();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_list_response_structure(): void
    {
        $body = json_decode($this->listTasks()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('total', $body['data']);
        $this->assertArrayHasKey('page', $body['data']);
        $this->assertArrayHasKey('perPage', $body['data']);
        $this->assertArrayHasKey('totalPages', $body['data']);
    }

    public function test_list_returns_only_authenticated_user_tasks(): void
    {
        // Cria tarefa do usuário principal
        $this->createTaskViaService(['title' => 'Minha tarefa']);

        // Cria outro usuário com tarefa própria
        $userService = static::getContainer()->get(UserService::class);
        $other       = new RegisterInput();
        $other->name     = 'Other';
        $other->email    = 'other_list@example.com';
        $other->password = 'secret123';
        $userService->register($other);

        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $otherUser = $em->getRepository(User::class)->findOneBy(['email' => 'other_list@example.com']);
        $taskService  = static::getContainer()->get(TaskService::class);
        $otherInput   = new CreateTaskInput();
        $otherInput->title = 'Tarefa alheia';
        $taskService->create($otherInput, $otherUser);

        $body  = json_decode($this->listTasks()->getContent(), true);
        $titles = array_column($body['data']['items'], 'title');

        $this->assertContains('Minha tarefa', $titles);
        $this->assertNotContains('Tarefa alheia', $titles);
    }

    // ── filtros ───────────────────────────────────────────────

    public function test_filter_by_status(): void
    {
        $this->createTaskViaService(['title' => 'Inbox task', 'status' => 'inbox']);
        $this->createTaskViaService(['title' => 'Done task', 'status' => 'done']);

        $body  = json_decode($this->listTasks(['status' => 'done'])->getContent(), true);
        $titles = array_column($body['data']['items'], 'title');

        $this->assertContains('Done task', $titles);
        $this->assertNotContains('Inbox task', $titles);
    }

    public function test_filter_by_priority(): void
    {
        $this->createTaskViaService(['title' => 'High prio', 'priority' => 'high']);
        $this->createTaskViaService(['title' => 'Low prio', 'priority' => 'low']);

        $body  = json_decode($this->listTasks(['priority' => 'high'])->getContent(), true);
        $titles = array_column($body['data']['items'], 'title');

        $this->assertContains('High prio', $titles);
        $this->assertNotContains('Low prio', $titles);
    }

    public function test_filter_by_due_date(): void
    {
        $this->createTaskViaService(['title' => 'Due today', 'due_date' => '2026-12-31T12:00:00+00:00']);
        $this->createTaskViaService(['title' => 'Due tomorrow', 'due_date' => '2027-01-01T12:00:00+00:00']);

        $body  = json_decode($this->listTasks(['due_date' => '2026-12-31'])->getContent(), true);
        $titles = array_column($body['data']['items'], 'title');

        $this->assertContains('Due today', $titles);
        $this->assertNotContains('Due tomorrow', $titles);
    }

    // ── paginação ─────────────────────────────────────────────

    public function test_pagination_per_page(): void
    {
        $this->createTaskViaService(['title' => 'T1']);
        $this->createTaskViaService(['title' => 'T2']);
        $this->createTaskViaService(['title' => 'T3']);

        $body = json_decode($this->listTasks(['page' => 1, 'per_page' => 2])->getContent(), true);

        $this->assertCount(2, $body['data']['items']);
        $this->assertSame(3, $body['data']['total']);
        $this->assertSame(2, $body['data']['totalPages']);
    }

    public function test_default_per_page_is_20(): void
    {
        $body = json_decode($this->listTasks()->getContent(), true);

        $this->assertSame(20, $body['data']['perPage']);
    }

    // ── ordenação ─────────────────────────────────────────────

    public function test_tasks_ordered_by_sort_order(): void
    {
        $this->createTaskViaService(['title' => 'Terceira', 'sort_order' => 3]);
        $this->createTaskViaService(['title' => 'Primeira', 'sort_order' => 1]);
        $this->createTaskViaService(['title' => 'Segunda',  'sort_order' => 2]);

        $body   = json_decode($this->listTasks()->getContent(), true);
        $titles = array_column($body['data']['items'], 'title');

        $this->assertSame('Primeira', $titles[0]);
        $this->assertSame('Segunda',  $titles[1]);
        $this->assertSame('Terceira', $titles[2]);
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->client->request('GET', '/api/tasks');

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
