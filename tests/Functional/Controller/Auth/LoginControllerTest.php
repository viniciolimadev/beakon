<?php

namespace App\Tests\Functional\Controller\Auth;

use App\Dto\RegisterInput;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LoginControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // cria usuário de teste direto via service
        $userService = static::getContainer()->get(UserService::class);
        $input           = new RegisterInput();
        $input->name     = 'Test User';
        $input->email    = 'login_test@example.com';
        $input->password = 'secret123';
        $userService->register($input);
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->execute(['email' => 'login_test@example.com']);

        parent::tearDown();
    }

    private function post(array $payload): Response
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        return $this->client->getResponse();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_login_returns_200(): void
    {
        $response = $this->post([
            'email'    => 'login_test@example.com',
            'password' => 'secret123',
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_login_returns_access_and_refresh_tokens(): void
    {
        $response = $this->post([
            'email'    => 'login_test@example.com',
            'password' => 'secret123',
        ]);

        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertArrayHasKey('refresh_token', $body['data']);
        $this->assertNotEmpty($body['data']['access_token']);
        $this->assertNotEmpty($body['data']['refresh_token']);
    }

    public function test_access_token_is_valid_jwt(): void
    {
        $response = $this->post([
            'email'    => 'login_test@example.com',
            'password' => 'secret123',
        ]);

        $token  = json_decode($response->getContent(), true)['data']['access_token'];
        $parts  = explode('.', $token);

        // JWT tem exatamente 3 partes separadas por ponto
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode(str_pad($parts[1], strlen($parts[1]) + 4 - strlen($parts[1]) % 4, '=')), true);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('email', $payload);
        $this->assertSame('login_test@example.com', $payload['email']);
    }

    // ── credenciais inválidas ─────────────────────────────────

    public function test_wrong_password_returns_401(): void
    {
        $response = $this->post([
            'email'    => 'login_test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_unknown_email_returns_401(): void
    {
        $response = $this->post([
            'email'    => 'ghost@example.com',
            'password' => 'secret123',
        ]);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_invalid_credentials_response_body(): void
    {
        $response = $this->post([
            'email'    => 'login_test@example.com',
            'password' => 'wrongpassword',
        ]);

        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $body);
        $this->assertSame(401, $body['code']);
    }
}
