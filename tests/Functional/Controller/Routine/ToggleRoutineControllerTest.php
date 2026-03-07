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

class ToggleRoutineControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'routine_toggle@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Toggle User';
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
            ->execute(['emails' => [$this->testEmail, 'other_toggle@example.com']]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_toggle@example.com']]);

        parent::tearDown();
    }

    private function seedRoutineItem(bool $isActive = true): RoutineItem
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $item = new RoutineItem();
        $item->setTitle('Item de rotina');
        $item->setTimeOfDay('08:00');
        $item->setDaysOfWeek([1, 2, 3]);
        $item->setSortOrder(0);
        $item->setIsActive($isActive);
        $item->setUser($this->user);

        $em->persist($item);
        $em->flush();

        return $item;
    }

    private function toggle(string $id, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('PATCH', "/api/routines/{$id}/toggle", [], [], $headers);

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_toggle_active_to_inactive_returns_200(): void
    {
        $item     = $this->seedRoutineItem(isActive: true);
        $response = $this->toggle((string) $item->getId(), $this->accessToken);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_toggle_deactivates_active_item(): void
    {
        $item     = $this->seedRoutineItem(isActive: true);
        $response = $this->toggle((string) $item->getId(), $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertFalse($body['data']['isActive']);
    }

    public function test_toggle_activates_inactive_item(): void
    {
        $item     = $this->seedRoutineItem(isActive: false);
        $response = $this->toggle((string) $item->getId(), $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertTrue($body['data']['isActive']);
    }

    public function test_toggle_response_structure(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->toggle((string) $item->getId(), $this->accessToken);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertArrayHasKey('title', $body['data']);
        $this->assertArrayHasKey('isActive', $body['data']);
    }

    public function test_toggle_persists_in_database(): void
    {
        $item = $this->seedRoutineItem(isActive: true);
        $this->toggle((string) $item->getId(), $this->accessToken);

        /** @var EntityManagerInterface $em */
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updated = $em->find(RoutineItem::class, $item->getId());

        $this->assertFalse($updated->isActive());
    }

    // ── erros ─────────────────────────────────────────────────

    public function test_toggle_unknown_id_returns_404(): void
    {
        $response = $this->toggle('00000000-0000-0000-0000-000000000000', $this->accessToken);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_toggle_other_user_item_returns_403(): void
    {
        // cria outro usuário e item
        /** @var EntityManagerInterface $em */
        $em    = static::getContainer()->get(EntityManagerInterface::class);
        $other = new User();
        $other->setName('Other');
        $other->setEmail('other_toggle@example.com');
        $other->setPassword('x');
        $em->persist($other);

        $item = new RoutineItem();
        $item->setTitle('Item alheio');
        $item->setTimeOfDay('10:00');
        $item->setDaysOfWeek([1]);
        $item->setSortOrder(0);
        $item->setUser($other);
        $em->persist($item);
        $em->flush();

        $response = $this->toggle((string) $item->getId(), $this->accessToken);

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->toggle((string) $item->getId());

        $this->assertSame(401, $response->getStatusCode());
    }
}
