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

class ReorderTaskControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'reorder_task_test@example.com';
    private string $accessToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Reorder User';
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
            ->execute(['e' => 'other_reorder@example.com']);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_reorder@example.com']]);

        parent::tearDown();
    }

    private function createTaskViaService(array $overrides = []): Task
    {
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $taskService  = static::getContainer()->get(TaskService::class);
        $input        = new CreateTaskInput();
        $input->title = $overrides['title'] ?? 'Tarefa para reordenar';

        if (isset($overrides['status'])) {
            $input->status = $overrides['status'];
        }

        $task = $taskService->create($input, $user);

        if (isset($overrides['sort_order'])) {
            $task->setSortOrder($overrides['sort_order']);
            $em->flush();
        }

        return $task;
    }

    private function reorderTask(string $id, int $order, bool $withAuth = true): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($withAuth) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken;
        }

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $id . '/reorder',
            [],
            [],
            $headers,
            json_encode(['order' => $order])
        );

        return $this->client->getResponse();
    }

    // ── retorno básico ────────────────────────────────────────

    public function test_reorder_returns_200(): void
    {
        $task = $this->createTaskViaService();

        $response = $this->reorderTask((string) $task->getId(), 0);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_reorder_response_has_items(): void
    {
        $this->createTaskViaService(['title' => 'A', 'sort_order' => 0]);
        $taskB = $this->createTaskViaService(['title' => 'B', 'sort_order' => 1]);
        $this->createTaskViaService(['title' => 'C', 'sort_order' => 2]);

        $body = json_decode($this->reorderTask((string) $taskB->getId(), 0)->getContent(), true);

        $this->assertArrayHasKey('items', $body['data']);
        $this->assertCount(3, $body['data']['items']);
    }

    // ── lógica de reordenação ────────────────────────────────

    public function test_reorder_moves_task_to_requested_position(): void
    {
        $taskA = $this->createTaskViaService(['title' => 'A', 'sort_order' => 0]);
        $taskB = $this->createTaskViaService(['title' => 'B', 'sort_order' => 1]);
        $taskC = $this->createTaskViaService(['title' => 'C', 'sort_order' => 2]);

        // Move C to position 0 → esperado: [C, A, B]
        $body   = json_decode($this->reorderTask((string) $taskC->getId(), 0)->getContent(), true);
        $titles = array_column($body['data']['items'], 'title');

        $this->assertSame('C', $titles[0]);
        $this->assertSame('A', $titles[1]);
        $this->assertSame('B', $titles[2]);
    }

    public function test_reorder_persists_sort_order(): void
    {
        $taskA = $this->createTaskViaService(['title' => 'A', 'sort_order' => 0]);
        $taskB = $this->createTaskViaService(['title' => 'B', 'sort_order' => 1]);
        $taskC = $this->createTaskViaService(['title' => 'C', 'sort_order' => 2]);

        // Move A to position 2 → esperado: [B, C, A]
        $this->reorderTask((string) $taskA->getId(), 2);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $a = $em->getRepository(Task::class)->find($taskA->getId());
        $b = $em->getRepository(Task::class)->find($taskB->getId());
        $c = $em->getRepository(Task::class)->find($taskC->getId());

        $this->assertSame(2, $a->getSortOrder());
        $this->assertSame(0, $b->getSortOrder());
        $this->assertSame(1, $c->getSortOrder());
    }

    public function test_reorder_does_not_affect_other_status_tasks(): void
    {
        $inboxTask = $this->createTaskViaService(['title' => 'Inbox', 'status' => 'inbox', 'sort_order' => 5]);
        $doneTask  = $this->createTaskViaService(['title' => 'Done',  'status' => 'done',  'sort_order' => 5]);

        $this->reorderTask((string) $inboxTask->getId(), 0);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $done = $em->getRepository(Task::class)->find($doneTask->getId());
        $this->assertSame(5, $done->getSortOrder());
    }

    // ── erros de negócio ─────────────────────────────────────

    public function test_reorder_returns_404_when_not_found(): void
    {
        $response = $this->reorderTask('00000000-0000-0000-0000-000000000000', 0);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_reorder_returns_403_when_owned_by_other_user(): void
    {
        $userService = static::getContainer()->get(UserService::class);
        $other       = new RegisterInput();
        $other->name     = 'Other';
        $other->email    = 'other_reorder@example.com';
        $other->password = 'secret123';
        $userService->register($other);

        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $otherUser = $em->getRepository(User::class)->findOneBy(['email' => 'other_reorder@example.com']);
        $taskService   = static::getContainer()->get(TaskService::class);
        $otherInput    = new CreateTaskInput();
        $otherInput->title = 'Tarefa alheia';
        $otherTask = $taskService->create($otherInput, $otherUser);

        $response = $this->reorderTask((string) $otherTask->getId(), 0);

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── validação ────────────────────────────────────────────

    public function test_reorder_validates_negative_order(): void
    {
        $task     = $this->createTaskViaService();
        $response = $this->reorderTask((string) $task->getId(), -1);

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $task = $this->createTaskViaService();

        $response = $this->reorderTask((string) $task->getId(), 0, false);

        $this->assertSame(401, $response->getStatusCode());
    }
}
