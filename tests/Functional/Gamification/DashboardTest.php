<?php

namespace App\Tests\Functional\Gamification;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'dashboard_test@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Dashboard User';
        $input->email    = $this->testEmail;
        $input->password = 'secret123';
        $this->user      = $userService->register($input);

        $this->client->request(
            'POST', '/api/auth/login', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $this->testEmail, 'password' => 'secret123'])
        );

        $data              = json_decode($this->client->getResponse()->getContent(), true);
        $this->accessToken = $data['data']['access_token'];
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\UserAchievement ua WHERE ua.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\PomodoroSession p WHERE p.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\Task t WHERE t.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    public function test_dashboard_returns_expected_structure(): void
    {
        $this->client->request(
            'GET', '/api/gamification/dashboard', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $this->assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $data = $body['data'];

        $this->assertArrayHasKey('xp', $data);
        $this->assertArrayHasKey('streak_days', $data);
        $this->assertArrayHasKey('achievements_unlocked', $data);
        $this->assertArrayHasKey('achievements_total', $data);
        $this->assertArrayHasKey('tasks_completed_today', $data);
        $this->assertArrayHasKey('minutes_focused_today', $data);
        $this->assertArrayHasKey('recent_achievements', $data);
        $this->assertIsArray($data['recent_achievements']);
    }

    public function test_dashboard_initial_values_are_zero(): void
    {
        $this->client->request(
            'GET', '/api/gamification/dashboard', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $data = $body['data'];

        $this->assertSame(0, $data['xp']);
        $this->assertSame(0, $data['streak_days']);
        $this->assertSame(0, $data['achievements_unlocked']);
        $this->assertGreaterThan(0, $data['achievements_total']);
        $this->assertSame(0, $data['tasks_completed_today']);
        $this->assertSame(0, $data['minutes_focused_today']);
        $this->assertEmpty($data['recent_achievements']);
    }

    public function test_dashboard_updates_after_completing_task(): void
    {
        $this->client->request(
            'POST', '/api/tasks', [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['title' => 'Dashboard task'])
        );
        $taskId = json_decode($this->client->getResponse()->getContent(), true)['data']['id'];
        $this->client->request(
            'PATCH', "/api/tasks/{$taskId}/status", [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['status' => 'done'])
        );

        $this->client->request(
            'GET', '/api/gamification/dashboard', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $data = $body['data'];

        $this->assertGreaterThan(0, $data['xp']);
        $this->assertSame(1, $data['tasks_completed_today']);
        $this->assertGreaterThan(0, $data['achievements_unlocked']);
        $this->assertNotEmpty($data['recent_achievements']);
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->client->request('GET', '/api/gamification/dashboard');
        $this->assertResponseStatusCodeSame(401);
    }
}
