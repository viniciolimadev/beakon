<?php

namespace App\Tests\Functional\EventListener;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StreakTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'streak_test@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Streak User';
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
        $em->createQuery('DELETE FROM App\Entity\Task t WHERE t.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    private function completeTask(): void
    {
        $this->client->request(
            'POST', '/api/tasks', [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['title' => 'Streak task'])
        );

        $taskId = json_decode($this->client->getResponse()->getContent(), true)['data']['id'];

        $this->client->request(
            'PATCH', "/api/tasks/{$taskId}/status", [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['status' => 'done'])
        );
    }

    private function freshUser(): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->find(User::class, $this->user->getId());
    }

    // ── streak incrementa ─────────────────────────────────────

    public function test_streak_increments_on_first_task_of_day(): void
    {
        $this->completeTask();

        $this->assertSame(1, $this->freshUser()->getStreakDays());
    }

    public function test_last_activity_date_is_set(): void
    {
        $this->completeTask();

        $user = $this->freshUser();
        $this->assertNotNull($user->getLastActivityDate());
        $this->assertSame(
            (new \DateTimeImmutable())->format('Y-m-d'),
            $user->getLastActivityDate()->format('Y-m-d')
        );
    }

    public function test_streak_not_incremented_twice_same_day(): void
    {
        $this->completeTask();
        $this->completeTask();

        $this->assertSame(1, $this->freshUser()->getStreakDays());
    }

    public function test_streak_continues_from_yesterday(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());
        $user->setStreakDays(5);
        $user->setLastActivityDate(new \DateTimeImmutable('yesterday'));
        $em->flush();

        $this->completeTask();

        $this->assertSame(6, $this->freshUser()->getStreakDays());
    }

    // ── streak zera ───────────────────────────────────────────

    public function test_streak_resets_when_last_activity_before_yesterday(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());
        $user->setStreakDays(10);
        $user->setLastActivityDate(new \DateTimeImmutable('-2 days'));
        $em->flush();

        $this->completeTask();

        $this->assertSame(1, $this->freshUser()->getStreakDays());
    }
}
