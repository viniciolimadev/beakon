<?php

namespace App\Tests\Functional\Controller\Routine;

use App\Dto\RegisterInput;
use App\Entity\RoutineItem;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DeleteRoutineControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'routine_delete@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Delete User';
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
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\RoutineItem r WHERE r.user IN (SELECT u FROM App\Entity\User u WHERE u.email IN (:emails))')
            ->execute(['emails' => [$this->testEmail, 'other_delete@example.com']]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_delete@example.com']]);

        parent::tearDown();
    }

    private function seedRoutineItem(): RoutineItem
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $item = new RoutineItem();
        $item->setTitle('Para excluir');
        $item->setTimeOfDay('08:00');
        $item->setDaysOfWeek([1]);
        $item->setSortOrder(0);
        $item->setUser($this->user);

        $em->persist($item);
        $em->flush();

        return $item;
    }

    private function delete(string $id, ?string $token = null): Response
    {
        $headers = [];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('DELETE', "/api/routines/{$id}", [], [], $headers);

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_delete_returns_204(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->delete((string) $item->getId(), $this->accessToken);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_delete_removes_from_database(): void
    {
        $item = $this->seedRoutineItem();
        $id   = $item->getId();
        $this->delete((string) $id, $this->accessToken);

        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $gone = $em->find(RoutineItem::class, $id);

        $this->assertNull($gone);
    }

    // ── erros ─────────────────────────────────────────────────

    public function test_delete_unknown_id_returns_404(): void
    {
        $response = $this->delete('00000000-0000-0000-0000-000000000000', $this->accessToken);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_delete_other_user_item_returns_403(): void
    {
        /** @var EntityManagerInterface $em */
        $em    = static::getContainer()->get(EntityManagerInterface::class);
        $other = new User();
        $other->setName('Other');
        $other->setEmail('other_delete@example.com');
        $other->setPassword('x');
        $em->persist($other);

        $item = new RoutineItem();
        $item->setTitle('Alheio');
        $item->setTimeOfDay('10:00');
        $item->setDaysOfWeek([1]);
        $item->setSortOrder(0);
        $item->setUser($other);
        $em->persist($item);
        $em->flush();

        $response = $this->delete((string) $item->getId(), $this->accessToken);

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->delete((string) $item->getId());

        $this->assertSame(401, $response->getStatusCode());
    }
}
