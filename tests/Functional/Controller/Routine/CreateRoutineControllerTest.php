<?php

namespace App\Tests\Functional\Controller\Routine;

use App\Dto\RegisterInput;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CreateRoutineControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'routine_test@example.com';
    private string $accessToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Routine User';
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

    private function createRoutine(array $payload, ?string $token = null): Response
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('POST', '/api/routines', [], [], $headers, json_encode($payload));

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_create_routine_returns_201(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Meditação',
            'time_of_day'  => '07:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_create_routine_response_structure(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Exercício',
            'time_of_day'  => '06:30',
            'days_of_week' => [1, 3, 5],
            'order'        => 1,
        ], $this->accessToken);

        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertArrayHasKey('title', $body['data']);
        $this->assertArrayHasKey('timeOfDay', $body['data']);
        $this->assertArrayHasKey('daysOfWeek', $body['data']);
        $this->assertArrayHasKey('order', $body['data']);
        $this->assertArrayHasKey('isActive', $body['data']);
        $this->assertArrayHasKey('createdAt', $body['data']);
    }

    public function test_is_active_defaults_to_true(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Leitura',
            'time_of_day'  => '21:00',
            'days_of_week' => [0, 6],
            'order'        => 0,
        ], $this->accessToken);

        $body = json_decode($response->getContent(), true);

        $this->assertTrue($body['data']['isActive']);
    }

    public function test_days_of_week_are_persisted(): void
    {
        $days = [1, 2, 3, 4, 5];

        $response = $this->createRoutine([
            'title'        => 'Standup',
            'time_of_day'  => '09:00',
            'days_of_week' => $days,
            'order'        => 0,
        ], $this->accessToken);

        $body = json_decode($response->getContent(), true);

        $this->assertSame($days, $body['data']['daysOfWeek']);
    }

    public function test_time_of_day_is_persisted(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Café',
            'time_of_day'  => '08:30',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $body = json_decode($response->getContent(), true);

        $this->assertSame('08:30', $body['data']['timeOfDay']);
    }

    // ── validação ─────────────────────────────────────────────

    public function test_missing_title_returns_422(): void
    {
        $response = $this->createRoutine([
            'time_of_day'  => '08:00',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_empty_title_returns_422(): void
    {
        $response = $this->createRoutine([
            'title'        => '',
            'time_of_day'  => '08:00',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_invalid_time_format_returns_422(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Tarefa',
            'time_of_day'  => '8h30',
            'days_of_week' => [1],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_invalid_day_of_week_returns_422(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Tarefa',
            'time_of_day'  => '08:00',
            'days_of_week' => [0, 7],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_empty_days_of_week_returns_422(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Tarefa',
            'time_of_day'  => '08:00',
            'days_of_week' => [],
            'order'        => 0,
        ], $this->accessToken);

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── autenticação ──────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->createRoutine([
            'title'        => 'Sem token',
            'time_of_day'  => '08:00',
            'days_of_week' => [1],
            'order'        => 0,
        ]);

        $this->assertSame(401, $response->getStatusCode());
    }
}
