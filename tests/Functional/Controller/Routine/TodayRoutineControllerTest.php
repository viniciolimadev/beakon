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

class TodayRoutineControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'routine_today@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Routine Today User';
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
        $em->createQuery('DELETE FROM App\Entity\RoutineItem r WHERE r.user IN (SELECT u FROM App\Entity\User u WHERE u.email = :email)')
            ->execute(['email' => $this->testEmail]);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    private function getToday(): Response
    {
        $this->client->request(
            'GET',
            '/api/routines/today',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        return $this->client->getResponse();
    }

    private function seedRoutineItem(array $overrides = []): RoutineItem
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $today = (int) (new \DateTimeImmutable())->format('w'); // 0=Sun, 6=Sat

        $item = new RoutineItem();
        $item->setTitle($overrides['title'] ?? 'Item de hoje');
        $item->setTimeOfDay($overrides['time_of_day'] ?? '08:00');
        $item->setDaysOfWeek($overrides['days_of_week'] ?? [$today]);
        $item->setSortOrder($overrides['sort_order'] ?? 0);
        $item->setIsActive($overrides['is_active'] ?? true);
        $item->setUser($this->user);

        $em->persist($item);
        $em->flush();

        return $item;
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_today_returns_200(): void
    {
        $response = $this->getToday();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_today_returns_array(): void
    {
        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
    }

    public function test_today_includes_item_for_current_day(): void
    {
        $this->seedRoutineItem();

        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(1, $body['data']);
    }

    public function test_today_item_structure(): void
    {
        $this->seedRoutineItem();

        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);
        $item     = $body['data'][0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('timeOfDay', $item);
        $this->assertArrayHasKey('daysOfWeek', $item);
        $this->assertArrayHasKey('order', $item);
        $this->assertArrayHasKey('isActive', $item);
    }

    public function test_today_excludes_item_for_other_day(): void
    {
        $today     = (int) (new \DateTimeImmutable())->format('w');
        $otherDay  = ($today + 1) % 7;

        $this->seedRoutineItem(['days_of_week' => [$otherDay]]);

        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(0, $body['data']);
    }

    public function test_today_excludes_inactive_items(): void
    {
        $this->seedRoutineItem(['is_active' => false]);

        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);

        $this->assertCount(0, $body['data']);
    }

    public function test_today_ordered_by_time_asc(): void
    {
        $this->seedRoutineItem(['title' => 'Tarde', 'time_of_day' => '14:00']);
        $this->seedRoutineItem(['title' => 'Manhã', 'time_of_day' => '07:00']);
        $this->seedRoutineItem(['title' => 'Noite', 'time_of_day' => '21:00']);

        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);

        $this->assertSame('Manhã', $body['data'][0]['title']);
        $this->assertSame('Tarde', $body['data'][1]['title']);
        $this->assertSame('Noite', $body['data'][2]['title']);
    }

    public function test_empty_when_no_routines(): void
    {
        $response = $this->getToday();
        $body     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $body['data']);
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->client->request('GET', '/api/routines/today');

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
