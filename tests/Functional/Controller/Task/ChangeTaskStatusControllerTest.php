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

class ChangeTaskStatusControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'change_status_test@example.com';
    private string $accessToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Status User';
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
            ->execute(['e' => 'other_status@example.com']);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_status@example.com']]);

        parent::tearDown();
    }

    private function createTaskViaService(array $overrides = []): Task
    {
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $taskService  = static::getContainer()->get(TaskService::class);
        $input        = new CreateTaskInput();
        $input->title = $overrides['title'] ?? 'Tarefa para status';

        if (isset($overrides['status'])) {
            $input->status = $overrides['status'];
        }

        return $taskService->create($input, $user);
    }

    private function patchStatus(string $id, string $status, ?string $token = 'use_default'): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token === 'use_default') {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken;
        } elseif ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $id . '/status',
            [],
            [],
            $headers,
            json_encode(['status' => $status])
        );

        return $this->client->getResponse();
    }

    // ── retorno básico ────────────────────────────────────────

    public function test_patch_status_returns_200(): void
    {
        $task = $this->createTaskViaService();

        $response = $this->patchStatus((string) $task->getId(), 'in_progress');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_patch_status_updates_status_field(): void
    {
        $task = $this->createTaskViaService();

        $body = json_decode(
            $this->patchStatus((string) $task->getId(), 'today')->getContent(),
            true
        );

        $this->assertSame('today', $body['data']['status']);
    }

    // ── completed_at ────────────────────────────────────────

    public function test_patch_status_sets_completed_at_when_done(): void
    {
        $task = $this->createTaskViaService();

        $this->patchStatus((string) $task->getId(), 'done');

        /** @var EntityManagerInterface $em */
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updated = $em->getRepository(Task::class)->find($task->getId());

        $this->assertNotNull($updated->getCompletedAt());
    }

    public function test_patch_status_clears_completed_at_when_leaving_done(): void
    {
        $task = $this->createTaskViaService(['status' => 'done']);

        /** @var EntityManagerInterface $em */
        $em  = static::getContainer()->get(EntityManagerInterface::class);
        $taskService = static::getContainer()->get(TaskService::class);

        // Force completedAt via service (status already set in entity)
        $em->clear();
        $freshTask = $em->getRepository(Task::class)->find($task->getId());
        $freshTask->setCompletedAt(new \DateTimeImmutable());
        $em->flush();

        // Now move back to inbox
        $this->patchStatus((string) $task->getId(), 'inbox');

        $em->clear();
        $updated = $em->getRepository(Task::class)->find($task->getId());

        $this->assertNull($updated->getCompletedAt());
    }

    // ── XP ────────────────────────────────────────────────────

    public function test_patch_status_awards_xp_when_done(): void
    {
        $task = $this->createTaskViaService();

        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);
        $xpBefore = $user->getXp();

        $this->patchStatus((string) $task->getId(), 'done');

        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $this->assertGreaterThan($xpBefore, $user->getXp());
    }

    public function test_patch_status_does_not_award_xp_for_non_done_transition(): void
    {
        $task = $this->createTaskViaService();

        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);
        $xpBefore = $user->getXp();

        $this->patchStatus((string) $task->getId(), 'in_progress');

        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $this->assertSame($xpBefore, $user->getXp());
    }

    // ── erros de negócio ─────────────────────────────────────

    public function test_patch_status_returns_404_when_not_found(): void
    {
        $response = $this->patchStatus('00000000-0000-0000-0000-000000000000', 'done');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_patch_status_returns_403_when_owned_by_other_user(): void
    {
        $userService = static::getContainer()->get(UserService::class);
        $other       = new RegisterInput();
        $other->name     = 'Other';
        $other->email    = 'other_status@example.com';
        $other->password = 'secret123';
        $userService->register($other);

        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $otherUser = $em->getRepository(User::class)->findOneBy(['email' => 'other_status@example.com']);
        $taskService   = static::getContainer()->get(TaskService::class);
        $otherInput    = new CreateTaskInput();
        $otherInput->title = 'Tarefa alheia';
        $otherTask = $taskService->create($otherInput, $otherUser);

        $response = $this->patchStatus((string) $otherTask->getId(), 'done');

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── validação ────────────────────────────────────────────

    public function test_patch_status_validates_invalid_status(): void
    {
        $task     = $this->createTaskViaService();
        $response = $this->patchStatus((string) $task->getId(), 'invalid_status');

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $task = $this->createTaskViaService();

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId() . '/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'done'])
        );

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
