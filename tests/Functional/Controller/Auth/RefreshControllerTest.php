<?php

namespace App\Tests\Functional\Controller\Auth;

use App\Dto\RegisterInput;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RefreshControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $testEmail = 'refresh_test@example.com';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userService     = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Refresh User';
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

    private function refresh(string $refreshToken): Response
    {
        $this->client->request(
            'POST',
            '/api/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['refresh_token' => $refreshToken])
        );

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_refresh_returns_200(): void
    {
        $tokens   = $this->login();
        $response = $this->refresh($tokens['refresh_token']);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_refresh_returns_access_and_refresh_tokens(): void
    {
        $tokens   = $this->login();
        $response = $this->refresh($tokens['refresh_token']);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertArrayHasKey('refresh_token', $body['data']);
        $this->assertNotEmpty($body['data']['access_token']);
        $this->assertNotEmpty($body['data']['refresh_token']);
    }

    public function test_new_access_token_is_valid_jwt(): void
    {
        $tokens = $this->login();
        $body   = json_decode($this->refresh($tokens['refresh_token'])->getContent(), true);
        $parts  = explode('.', $body['data']['access_token']);

        $this->assertCount(3, $parts);
    }

    public function test_refresh_token_is_rotated(): void
    {
        $loginTokens   = $this->login();
        $oldRefresh    = $loginTokens['refresh_token'];
        $body          = json_decode($this->refresh($oldRefresh)->getContent(), true);
        $newRefresh    = $body['data']['refresh_token'];

        $this->assertNotSame($oldRefresh, $newRefresh);
    }

    // ── token inválido / expirado ─────────────────────────────

    public function test_invalid_refresh_token_returns_401(): void
    {
        $response = $this->refresh('token-completamente-invalido');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_invalid_token_response_body(): void
    {
        $response = $this->refresh('token-invalido');
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $body);
        $this->assertSame(401, $body['code']);
    }

    public function test_expired_refresh_token_returns_401(): void
    {
        $loginTokens = $this->login();

        // Força expiração do token diretamente no banco
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('-1 day'));
        $em->flush();

        $response = $this->refresh($loginTokens['refresh_token']);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_old_token_cannot_be_reused(): void
    {
        $loginTokens = $this->login();
        $oldRefresh  = $loginTokens['refresh_token'];

        // Primeiro uso — deve funcionar e rotacionar
        $this->refresh($oldRefresh);

        // Segundo uso do mesmo token — deve retornar 401
        $response = $this->refresh($oldRefresh);

        $this->assertSame(401, $response->getStatusCode());
    }
}
