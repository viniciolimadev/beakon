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

class FinishPomodoroControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'pomodoro_finish@example.com';
    private string $accessToken = '';
    private User $user;
    private Task $task;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Finish User';
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

    private function seedSession(): PomodoroSession
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $session = new PomodoroSession();
        $session->setUser($this->user);
        $session->setTask($this->task);
        $session->setStartedAt(new \DateTimeImmutable('-25 minutes'));

        $em->persist($session);
        $em->flush();

        return $session;
    }

    private function finish(string $id, array $payload, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('PATCH', "/api/pomodoro/{$id}/finish", [], [], $headers, json_encode($payload));

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_finish_returns_200(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => true], $this->accessToken);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_finish_sets_finished_at(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => true], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertNotNull($body['data']['finishedAt']);
    }

    public function test_finish_sets_completed_true(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => true], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertTrue($body['data']['completed']);
    }

    public function test_finish_sets_completed_false(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => false], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertFalse($body['data']['completed']);
    }

    public function test_finish_calculates_duration(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => true], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertGreaterThanOrEqual(24, $body['data']['durationMinutes']);
        $this->assertLessThanOrEqual(26, $body['data']['durationMinutes']);
    }

    public function test_finish_response_structure(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => true], $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $body['data']);
        $this->assertArrayHasKey('taskId', $body['data']);
        $this->assertArrayHasKey('startedAt', $body['data']);
        $this->assertArrayHasKey('finishedAt', $body['data']);
        $this->assertArrayHasKey('completed', $body['data']);
        $this->assertArrayHasKey('durationMinutes', $body['data']);
    }

    // ── erros ─────────────────────────────────────────────────

    public function test_finish_unknown_id_returns_404(): void
    {
        $response = $this->finish('00000000-0000-0000-0000-000000000000', ['completed' => true], $this->accessToken);

        $this->assertSame(404, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $session  = $this->seedSession();
        $response = $this->finish((string) $session->getId(), ['completed' => true]);

        $this->assertSame(401, $response->getStatusCode());
    }
}
