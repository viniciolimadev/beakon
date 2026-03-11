<?php

namespace App\Tests\Functional\Gamification;

use App\Dto\RegisterInput;
use App\Entity\Achievement;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AchievementTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'achievement_test@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Achievement User';
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
            json_encode(['title' => 'Achievement task'])
        );

        $taskId = json_decode($this->client->getResponse()->getContent(), true)['data']['id'];

        $this->client->request(
            'PATCH', "/api/tasks/{$taskId}/status", [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['status' => 'done'])
        );
    }

    public function test_achievements_endpoint_returns_all_achievements(): void
    {
        $this->client->request(
            'GET', '/api/gamification/achievements', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $this->assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertNotEmpty($body['data']);

        $first = $body['data'][0];
        $this->assertArrayHasKey('key', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('xp_bonus', $first);
        $this->assertArrayHasKey('unlocked', $first);
        $this->assertFalse($first['unlocked']);
    }

    public function test_first_task_achievement_unlocked_after_completing_task(): void
    {
        $this->completeTask();

        $this->client->request(
            'GET', '/api/gamification/achievements', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $body         = json_decode($this->client->getResponse()->getContent(), true);
        $achievements = $body['data'];

        $firstTask = array_values(array_filter($achievements, fn($a) => $a['key'] === 'first_task'))[0] ?? null;

        $this->assertNotNull($firstTask);
        $this->assertTrue($firstTask['unlocked']);
        $this->assertArrayHasKey('unlocked_at', $firstTask);
    }

    public function test_achievement_unlocked_only_once(): void
    {
        $this->completeTask();
        $this->completeTask();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());

        $count = $em->createQuery(
            'SELECT COUNT(ua.id) FROM App\Entity\UserAchievement ua
             JOIN ua.achievement a
             WHERE ua.user = :user AND a.achievementKey = :key'
        )->setParameters(['user' => $user, 'key' => 'first_task'])
         ->getSingleScalarResult();

        $this->assertSame(1, (int) $count);
    }

    public function test_xp_bonus_credited_on_unlock(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $userBefore = $em->find(User::class, $this->user->getId());
        $xpBefore   = $userBefore->getXp();

        $this->completeTask();

        $em->clear();
        $userAfter = $em->find(User::class, $this->user->getId());
        $xpAfter   = $userAfter->getXp();

        // XP from task (10 for default medium → low? Let's just check it increased by more than task XP alone)
        // Achievement first_task grants 50 XP bonus
        $this->assertGreaterThan($xpBefore, $xpAfter);

        // The achievement bonus for first_task is 50 XP on top of task XP
        /** @var Achievement|null $achievement */
        $achievement = $em->getRepository(Achievement::class)->findOneBy(['achievementKey' => 'first_task']);
        $this->assertNotNull($achievement);
        $this->assertGreaterThanOrEqual($achievement->getXpBonus(), $xpAfter - $xpBefore);
    }

    public function test_unlocked_achievement_shows_unlocked_true(): void
    {
        $this->completeTask();

        $this->client->request(
            'GET', '/api/gamification/achievements', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $unlocked = array_filter($body['data'], fn($a) => $a['unlocked'] === true);

        $this->assertNotEmpty($unlocked);
    }

    public function test_achievements_endpoint_requires_auth(): void
    {
        $this->client->request('GET', '/api/gamification/achievements');
        $this->assertResponseStatusCodeSame(401);
    }
}
