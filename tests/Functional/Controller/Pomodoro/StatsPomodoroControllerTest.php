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

class StatsPomodoroControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'pomodoro_stats@example.com';
    private string $accessToken = '';
    private User $user;
    private Task $task;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Stats User';
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
        $this->task->setTitle('Tarefa stats');
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

    private function seedFinished(int $minutes, bool $completed, string $ago = 'now'): void
    {
        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $startedAt = new \DateTimeImmutable($ago);

        $session = new PomodoroSession();
        $session->setUser($this->user);
        $session->setTask($this->task);
        $session->setStartedAt($startedAt);
        $session->setFinishedAt($startedAt->modify("+{$minutes} minutes"));
        $session->setCompleted($completed);
        $session->setDurationMinutes($minutes);

        $em->persist($session);
        $em->flush();
    }

    private function getStats(?string $token = null): Response
    {
        $headers = [];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('GET', '/api/pomodoro/stats', [], [], $headers);

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_stats_returns_200(): void
    {
        $response = $this->getStats($this->accessToken);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_stats_response_structure(): void
    {
        $response = $this->getStats($this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('minutesToday', $body['data']);
        $this->assertArrayHasKey('minutesWeek', $body['data']);
        $this->assertArrayHasKey('minutesMonth', $body['data']);
        $this->assertArrayHasKey('sessionsCompleted', $body['data']);
        $this->assertArrayHasKey('sessionsInterrupted', $body['data']);
        $this->assertArrayHasKey('avgSessionsPerDay', $body['data']);
    }

    public function test_stats_zeros_when_no_sessions(): void
    {
        $response = $this->getStats($this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(0, $body['data']['minutesToday']);
        $this->assertSame(0, $body['data']['minutesWeek']);
        $this->assertSame(0, $body['data']['minutesMonth']);
        $this->assertSame(0, $body['data']['sessionsCompleted']);
        $this->assertSame(0, $body['data']['sessionsInterrupted']);
    }

    public function test_stats_counts_minutes_today(): void
    {
        $this->seedFinished(25, true);
        $this->seedFinished(30, true);

        $response = $this->getStats($this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(55, $body['data']['minutesToday']);
    }

    public function test_stats_counts_completed_vs_interrupted(): void
    {
        $this->seedFinished(25, true);
        $this->seedFinished(25, true);
        $this->seedFinished(10, false);

        $response = $this->getStats($this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(2, $body['data']['sessionsCompleted']);
        $this->assertSame(1, $body['data']['sessionsInterrupted']);
    }

    public function test_stats_excludes_old_sessions_from_today(): void
    {
        $this->seedFinished(25, true, 'yesterday');
        $this->seedFinished(30, true);

        $response = $this->getStats($this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(30, $body['data']['minutesToday']);
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getStats();

        $this->assertSame(401, $response->getStatusCode());
    }
}
