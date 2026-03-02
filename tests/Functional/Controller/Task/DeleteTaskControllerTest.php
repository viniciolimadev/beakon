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

class DeleteTaskControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'delete_task_test@example.com';
    private string $accessToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Delete User';
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
            ->execute(['e' => 'other_delete@example.com']);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_delete@example.com']]);

        parent::tearDown();
    }

    private function createTaskViaService(): Task
    {
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $taskService  = static::getContainer()->get(TaskService::class);
        $input        = new CreateTaskInput();
        $input->title = 'Tarefa para deletar';

        return $taskService->create($input, $user);
    }

    private function deleteTask(string $id, bool $withAuth = true): Response
    {
        $headers = [];

        if ($withAuth) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken;
        }

        $this->client->request('DELETE', '/api/tasks/' . $id, [], [], $headers);

        return $this->client->getResponse();
    }

    public function test_delete_returns_204(): void
    {
        $task = $this->createTaskViaService();

        $response = $this->deleteTask((string) $task->getId());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_delete_removes_task_from_database(): void
    {
        $task = $this->createTaskViaService();
        $id   = $task->getId();

        $this->deleteTask((string) $id);

        /** @var EntityManagerInterface $em */
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $deleted = $em->getRepository(Task::class)->find($id);

        $this->assertNull($deleted);
    }

    public function test_delete_returns_404_when_not_found(): void
    {
        $response = $this->deleteTask('00000000-0000-0000-0000-000000000000');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_delete_returns_403_when_owned_by_other_user(): void
    {
        $userService = static::getContainer()->get(UserService::class);
        $other       = new RegisterInput();
        $other->name     = 'Other';
        $other->email    = 'other_delete@example.com';
        $other->password = 'secret123';
        $userService->register($other);

        /** @var EntityManagerInterface $em */
        $em        = static::getContainer()->get(EntityManagerInterface::class);
        $otherUser = $em->getRepository(User::class)->findOneBy(['email' => 'other_delete@example.com']);
        $taskService   = static::getContainer()->get(TaskService::class);
        $otherInput    = new CreateTaskInput();
        $otherInput->title = 'Tarefa alheia';
        $otherTask = $taskService->create($otherInput, $otherUser);

        $response = $this->deleteTask((string) $otherTask->getId());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $task = $this->createTaskViaService();

        $response = $this->deleteTask((string) $task->getId(), false);

        $this->assertSame(401, $response->getStatusCode());
    }
}
