<?php

namespace App\Tests\Functional\Controller\Pomodoro;

use App\Dto\RegisterInput;
use App\Entity\PomodoroSession;
use App\Entity\Task;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HistoryPomodoroControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'pomodoro_history@example.com';
    private string $accessToken = '';
    private User $user;
    private Task $task;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'History User';
        $input->email    = $this->testEmail;
        $input->password = 'secret123';
        $this->user      = $userService->register($input);

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $this->testEmail, 'password' => 'secret123'])
        );

        $data              = json_decode($this->client->getResponse()->getContent(), true);
        $this->accessToken = $data['data']['access_token'];

        /** @var EntityManagerInterface $em */
        $em         = static::getContainer()->get(EntityManagerInterface::class);
        $this->task = new Task();
        $this->task->setTitle('Tarefa histórico');
        $this->task->setUser($this->user);
        $em->persist($this->task);
        $em->flush();
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\PomodoroSession p WHERE p.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\Task t WHERE t.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    private function seedSession(array $overrides = []): PomodoroSession
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $session = new PomodoroSession();
        $session->setUser($this->user);
        $session->setTask($overrides['task'] ?? $this->task);
        $session->setStartedAt($overrides['startedAt'] ?? new \DateTimeImmutable());
        if (isset($overrides['finishedAt'])) {
            $session->setFinishedAt($overrides['finishedAt']);
            $session->setCompleted($overrides['completed'] ?? true);
            $session->setDurationMinutes($overrides['durationMinutes'] ?? 25);
        }

        $em->persist($session);
        $em->flush();

        return $session;
    }

    private function getHistory(array $params = [], ?string $token = null): Response
    {
        $headers = [];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $query = $params ? '?' . http_build_query($params) : '';
        $this->client->request('GET', '/api/pomodoro/history' . $query, [], [], $headers);

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_history_returns_200(): void
    {
        $response = $this->getHistory([], $this->accessToken);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_history_returns_array_with_meta(): void
    {
        $response = $this->getHistory([], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('total', $body['meta']);
        $this->assertArrayHasKey('page', $body['meta']);
        $this->assertArrayHasKey('perPage', $body['meta']);
    }

    public function test_history_empty_when_no_sessions(): void
    {
        $response = $this->getHistory([], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(0, $body['data']);
        $this->assertSame(0, $body['meta']['total']);
    }

    public function test_history_returns_sessions(): void
    {
        $this->seedSession(['finishedAt' => new \DateTimeImmutable()]);
        $this->seedSession(['finishedAt' => new \DateTimeImmutable()]);

        $response = $this->getHistory([], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(2, $body['data']);
    }

    public function test_history_item_structure(): void
    {
        $this->seedSession(['finishedAt' => new \DateTimeImmutable()]);

        $response = $this->getHistory([], $this->accessToken);
        $body     = json_decode($response->getContent(), true);
        $item     = $body['data'][0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('taskId', $item);
        $this->assertArrayHasKey('startedAt', $item);
        $this->assertArrayHasKey('finishedAt', $item);
        $this->assertArrayHasKey('completed', $item);
        $this->assertArrayHasKey('durationMinutes', $item);
    }

    public function test_history_filter_by_task_id(): void
    {
        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $otherTask = new Task();
        $otherTask->setTitle('Outra tarefa');
        $otherTask->setUser($this->user);
        $em->persist($otherTask);
        $em->flush();

        $this->seedSession(['finishedAt' => new \DateTimeImmutable()]);
        $this->seedSession(['task' => $otherTask, 'finishedAt' => new \DateTimeImmutable()]);

        $response = $this->getHistory(['task_id' => (string) $this->task->getId()], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(1, $body['data']);
        $this->assertSame((string) $this->task->getId(), $body['data'][0]['taskId']);
    }

    public function test_history_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedSession(['finishedAt' => new \DateTimeImmutable()]);
        }

        $response = $this->getHistory(['page' => 1, 'per_page' => 3], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(3, $body['data']);
        $this->assertSame(5, $body['meta']['total']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(3, $body['meta']['perPage']);
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getHistory();

        $this->assertSame(401, $response->getStatusCode());
    }
}
