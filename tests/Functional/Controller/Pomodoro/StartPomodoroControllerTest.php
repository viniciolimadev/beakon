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

class StartPomodoroControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'pomodoro_start@example.com';
    private string $accessToken = '';
    private User $user;
    private Task $task;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Pomodoro User';
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
        $this->task->setTitle('Tarefa foco');
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

    private function start(array $payload, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('POST', '/api/pomodoro/start', [], [], $headers, json_encode($payload));

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_start_returns_201(): void
    {
        $response = $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_start_response_structure(): void
    {
        $response = $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertArrayHasKey('taskId', $body['data']);
        $this->assertArrayHasKey('startedAt', $body['data']);
        $this->assertArrayHasKey('finishedAt', $body['data']);
        $this->assertArrayHasKey('completed', $body['data']);
        $this->assertArrayHasKey('durationMinutes', $body['data']);
    }

    public function test_started_at_is_set(): void
    {
        $response = $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertNotNull($body['data']['startedAt']);
    }

    public function test_finished_at_is_null_on_start(): void
    {
        $response = $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertNull($body['data']['finishedAt']);
    }

    public function test_completed_is_null_on_start(): void
    {
        $response = $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertNull($body['data']['completed']);
    }

    // ── sessão ativa ──────────────────────────────────────────

    public function test_cannot_start_when_active_session_exists(): void
    {
        $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);
        $response = $this->start(['task_id' => (string) $this->task->getId()], $this->accessToken);

        $this->assertSame(409, $response->getStatusCode());
    }

    // ── validação ─────────────────────────────────────────────

    public function test_missing_task_id_returns_422(): void
    {
        $response = $this->start([], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_invalid_task_id_returns_404(): void
    {
        $response = $this->start(['task_id' => '00000000-0000-0000-0000-000000000000'], $this->accessToken);

        $this->assertSame(404, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->start(['task_id' => (string) $this->task->getId()]);

        $this->assertSame(401, $response->getStatusCode());
    }
}
