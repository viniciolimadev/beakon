<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    private JwtService $service;
    private JWTTokenManagerInterface&Stub $jwtManager;
    private EntityManagerInterface&Stub $em;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createStub(JWTTokenManagerInterface::class);
        $this->em         = $this->createStub(EntityManagerInterface::class);
        $this->service    = new JwtService($this->jwtManager, $this->em);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('$2y$04$hashed');

        return $user;
    }

    // ── tokens gerados ────────────────────────────────────────

    #[Test]
    public function creates_access_token(): void
    {
        $this->jwtManager->method('createFromPayload')->willReturn('jwt.access.token');

        $tokens = $this->service->createTokensForUser($this->makeUser());

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertSame('jwt.access.token', $tokens['access_token']);
    }

    #[Test]
    public function creates_refresh_token(): void
    {
        $this->jwtManager->method('createFromPayload')->willReturn('jwt.access.token');

        $tokens = $this->service->createTokensForUser($this->makeUser());

        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertNotEmpty($tokens['refresh_token']);
    }

    #[Test]
    public function refresh_token_is_stored_on_user(): void
    {
        $this->jwtManager->method('createFromPayload')->willReturn('jwt.access.token');
        $user = $this->makeUser();

        $tokens = $this->service->createTokensForUser($user);

        $this->assertSame($tokens['refresh_token'], $user->getRefreshToken());
        $this->assertNotNull($user->getRefreshTokenExpiresAt());
    }

    #[Test]
    public function refresh_token_expires_in_7_days(): void
    {
        $this->jwtManager->method('createFromPayload')->willReturn('jwt.access.token');
        $user = $this->makeUser();

        $this->service->createTokensForUser($user);

        $expiry = $user->getRefreshTokenExpiresAt();
        $diff   = (new \DateTimeImmutable())->diff($expiry);

        $this->assertGreaterThanOrEqual(6, $diff->days);
        $this->assertLessThanOrEqual(7, $diff->days);
    }

    #[Test]
    public function access_token_payload_contains_user_id_and_email(): void
    {
        $capturedPayload = [];

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())
            ->method('createFromPayload')
            ->willReturnCallback(function ($user, array $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return 'jwt.access.token';
            });

        $service = new JwtService($jwtManager, $this->em);
        $service->createTokensForUser($this->makeUser());

        $this->assertArrayHasKey('user_id', $capturedPayload);
        $this->assertArrayHasKey('email', $capturedPayload);
        $this->assertSame('alice@example.com', $capturedPayload['email']);
    }
}
