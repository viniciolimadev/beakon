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

class UpdateRoutineControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'routine_update@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Update User';
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
            ->execute(['emails' => [$this->testEmail, 'other_update@example.com']]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email IN (:emails)')
            ->execute(['emails' => [$this->testEmail, 'other_update@example.com']]);

        parent::tearDown();
    }

    private function seedRoutineItem(): RoutineItem
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $item = new RoutineItem();
        $item->setTitle('Original');
        $item->setTimeOfDay('08:00');
        $item->setDaysOfWeek([1, 2]);
        $item->setSortOrder(0);
        $item->setUser($this->user);

        $em->persist($item);
        $em->flush();

        return $item;
    }

    private function update(string $id, array $payload, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('PUT', "/api/routines/{$id}", [], [], $headers, json_encode($payload));

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_update_returns_200(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->update((string) $item->getId(), [
            'title'        => 'Atualizado',
            'time_of_day'  => '09:00',
            'days_of_week' => [3, 4, 5],
            'order'        => 1,
        ], $this->accessToken);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_update_changes_all_fields(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->update((string) $item->getId(), [
            'title'        => 'Novo título',
            'time_of_day'  => '20:30',
            'days_of_week' => [0, 6],
            'order'        => 2,
        ], $this->accessToken);

        $body = json_decode($response->getContent(), true);

        $this->assertSame('Novo título', $body['data']['title']);
        $this->assertSame('20:30', $body['data']['timeOfDay']);
        $this->assertSame([0, 6], $body['data']['daysOfWeek']);
        $this->assertSame(2, $body['data']['order']);
    }

    public function test_update_persists_in_database(): void
    {
        $item = $this->seedRoutineItem();
        $this->update((string) $item->getId(), [
            'title'        => 'Persistido',
            'time_of_day'  => '11:00',
            'days_of_week' => [2],
            'order'        => 0,
        ], $this->accessToken);

        /** @var EntityManagerInterface $em */
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updated = $em->find(RoutineItem::class, $item->getId());

        $this->assertSame('Persistido', $updated->getTitle());
        $this->assertSame('11:00', $updated->getTimeOfDay());
    }

    // ── validação ─────────────────────────────────────────────

    public function test_update_empty_title_returns_422(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->update((string) $item->getId(), [
            'title'        => '',
            'time_of_day'  => '08:00',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_update_invalid_time_returns_422(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->update((string) $item->getId(), [
            'title'        => 'Valido',
            'time_of_day'  => '8h00',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── erros ─────────────────────────────────────────────────

    public function test_update_unknown_id_returns_404(): void
    {
        $response = $this->update('00000000-0000-0000-0000-000000000000', [
            'title'        => 'x',
            'time_of_day'  => '08:00',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_update_other_user_item_returns_403(): void
    {
        /** @var EntityManagerInterface $em */
        $em    = static::getContainer()->get(EntityManagerInterface::class);
        $other = new User();
        $other->setName('Other');
        $other->setEmail('other_update@example.com');
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

        $response = $this->update((string) $item->getId(), [
            'title'        => 'Hack',
            'time_of_day'  => '08:00',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $item     = $this->seedRoutineItem();
        $response = $this->update((string) $item->getId(), ['title' => 'x']);

        $this->assertSame(401, $response->getStatusCode());
    }
}
