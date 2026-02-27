<?php

namespace App\Tests\Functional\Controller\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegisterControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ], $overrides);
    }

    private function post(array $payload): Response
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        return $this->client->getResponse();
    }

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :prefix')
            ->execute(['prefix' => '%@example.com']);

        parent::tearDown();
    }

    // ── sucesso ───────────────────────────────────────────────

    public function test_register_returns_201(): void
    {
        $response = $this->post($this->validPayload());

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_register_response_structure(): void
    {
        $response = $this->post($this->validPayload());

        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertSame('User registered successfully', $body['message']);
    }

    public function test_register_response_contains_user_fields(): void
    {
        $response = $this->post($this->validPayload());

        $data = json_decode($response->getContent(), true)['data'];

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('xp', $data);
        $this->assertArrayHasKey('streakDays', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function test_register_response_does_not_expose_password(): void
    {
        $response = $this->post($this->validPayload());

        $data = json_decode($response->getContent(), true)['data'];

        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_register_persists_user_with_hashed_password(): void
    {
        $this->post($this->validPayload(['email' => 'bob@example.com']));

        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'bob@example.com']);

        $this->assertNotNull($user);
        $this->assertNotSame('secret123', $user->getPassword());
        $this->assertStringStartsWith('$2y$', $user->getPassword()); // bcrypt
    }

    // ── validações ────────────────────────────────────────────

    public function test_duplicate_email_returns_422(): void
    {
        $payload = $this->validPayload(['email' => 'dup@example.com']);

        $this->post($payload);                  // registra pela 1ª vez
        $response = $this->post($payload);      // tenta duplicar

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_short_password_returns_422(): void
    {
        $response = $this->post($this->validPayload(['password' => '123']));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_invalid_email_returns_422(): void
    {
        $response = $this->post($this->validPayload(['email' => 'not-an-email']));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_missing_name_returns_422(): void
    {
        $response = $this->post($this->validPayload(['name' => '']));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_validation_response_has_violations(): void
    {
        $response = $this->post($this->validPayload(['password' => '123']));

        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('violations', $body);
        $this->assertArrayHasKey('password', $body['violations']);
    }
}
