<?php

namespace App\Tests\Functional\Gamification;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StreakEndpointTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail   = 'streak_endpoint_test@example.com';
    private string $accessToken = '';
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Streak Endpoint User';
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
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    public function test_streak_endpoint_returns_expected_structure(): void
    {
        $this->client->request(
            'GET', '/api/gamification/streak', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $this->assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $data = $body['data'];

        $this->assertArrayHasKey('streak_days', $data);
        $this->assertArrayHasKey('last_activity_date', $data);
    }

    public function test_streak_initial_values(): void
    {
        $this->client->request(
            'GET', '/api/gamification/streak', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $data = $body['data'];

        $this->assertSame(0, $data['streak_days']);
        $this->assertNull($data['last_activity_date']);
    }

    public function test_streak_reflects_user_streak_days(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $this->user->getId());
        $user->setStreakDays(7);
        $user->setLastActivityDate(new \DateTimeImmutable('yesterday'));
        $em->flush();

        $this->client->request(
            'GET', '/api/gamification/streak', [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->accessToken]
        );

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $data = $body['data'];

        $this->assertSame(7, $data['streak_days']);
        $this->assertNotNull($data['last_activity_date']);
        $this->assertSame(
            (new \DateTimeImmutable('yesterday'))->format('Y-m-d'),
            substr($data['last_activity_date'], 0, 10)
        );
    }

    public function test_streak_endpoint_requires_auth(): void
    {
        $this->client->request('GET', '/api/gamification/streak');
        $this->assertResponseStatusCodeSame(401);
    }
}
