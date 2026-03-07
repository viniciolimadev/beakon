<?php

namespace App\Tests\Functional\EventListener;

use App\Dto\RegisterInput;
use App\Entity\Task;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskCompletedXpTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'xp_test@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'XP User';
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

    private function createAndCompleteTask(string $priority = 'medium'): void
    {
        // Cria tarefa
        $this->client->request(
            'POST', '/api/tasks', [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['title' => "Task {$priority}", 'priority' => $priority])
        );

        $taskId = json_decode($this->client->getResponse()->getContent(), true)['data']['id'];

        // Move para done
        $this->client->request(
            'PATCH', "/api/tasks/{$taskId}/status", [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['status' => 'done'])
        );
    }

    private function getUserXp(): int
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());

        return $user->getXp();
    }

    // ── tabela de XP ──────────────────────────────────────────

    public function test_low_priority_gives_10_xp(): void
    {
        $this->createAndCompleteTask('low');

        $this->assertSame(10, $this->getUserXp());
    }

    public function test_medium_priority_gives_25_xp(): void
    {
        $this->createAndCompleteTask('medium');

        $this->assertSame(25, $this->getUserXp());
    }

    public function test_high_priority_gives_50_xp(): void
    {
        $this->createAndCompleteTask('high');

        $this->assertSame(50, $this->getUserXp());
    }

    public function test_xp_accumulates_across_tasks(): void
    {
        $this->createAndCompleteTask('low');    // +10
        $this->createAndCompleteTask('medium'); // +25

        $this->assertSame(35, $this->getUserXp());
    }

    // ── bônus de streak ───────────────────────────────────────

    public function test_streak_bonus_applies_when_streak_gte_3(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());
        $user->setStreakDays(3);
        $em->flush();

        $this->createAndCompleteTask('medium'); // 25 * 1.5 = 37 (arredondado)

        $xp = $this->getUserXp();
        $this->assertSame(37, $xp); // floor(25 * 1.5) = 37
    }

    public function test_no_streak_bonus_when_streak_lt_3(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());
        $user->setStreakDays(2);
        $em->flush();

        $this->createAndCompleteTask('medium');

        $this->assertSame(25, $this->getUserXp());
    }

    // ── sem XP ao desmarcar ───────────────────────────────────

    public function test_no_xp_when_task_moved_out_of_done(): void
    {
        $this->createAndCompleteTask('medium'); // +25
        $xpAfterDone = $this->getUserXp();

        // Busca a task criada
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $task = $em->createQuery('SELECT t FROM App\Entity\Task t WHERE t.user = :user ORDER BY t.createdAt DESC')
            ->setParameter('user', $this->user->getId())
            ->setMaxResults(1)
            ->getOneOrNullResult();

        // Move de volta para in_progress
        $this->client->request(
            'PATCH', "/api/tasks/{$task->getId()}/status", [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken],
            json_encode(['status' => 'in_progress'])
        );

        $this->assertSame($xpAfterDone, $this->getUserXp());
    }
}
