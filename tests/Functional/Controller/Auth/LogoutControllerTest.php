<?php

namespace App\Tests\Functional\Controller\Auth;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LogoutControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail = 'logout_test@example.com';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Logout User';
        $input->email    = $this->testEmail;
        $input->password = 'secret123';
        $userService->register($input);
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => $this->testEmail]);

        parent::tearDown();
    }

    private function login(): array
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $this->testEmail, 'password' => 'secret123'])
        );

        return json_decode($this->client->getResponse()->getContent(), true)['data'];
    }

    private function logout(string $refreshToken): Response
    {
        $this->client->request(
            'POST',
            '/api/auth/logout',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['refresh_token' => $refreshToken])
        );

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_logout_returns_200(): void
    {
        $tokens   = $this->login();
        $response = $this->logout($tokens['refresh_token']);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_logout_clears_refresh_token_from_database(): void
    {
        $tokens = $this->login();
        $this->logout($tokens['refresh_token']);

        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);

        $this->assertNull($user->getRefreshToken());
        $this->assertNull($user->getRefreshTokenExpiresAt());
    }

    public function test_logged_out_refresh_token_cannot_be_reused(): void
    {
        $tokens = $this->login();
        $this->logout($tokens['refresh_token']);

        $this->client->request(
            'POST',
            '/api/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['refresh_token' => $tokens['refresh_token']])
        );

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // ── token inválido (idempotente) ──────────────────────────

    public function test_logout_with_invalid_token_still_returns_200(): void
    {
        $response = $this->logout('token-completamente-invalido');

        $this->assertSame(200, $response->getStatusCode());
    }
}
