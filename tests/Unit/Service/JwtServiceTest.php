<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Exception\InvalidRefreshTokenException;
use App\Repository\UserRepository;
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
    private UserRepository&Stub $userRepository;

    protected function setUp(): void
    {
        $this->jwtManager     = $this->createStub(JWTTokenManagerInterface::class);
        $this->em             = $this->createStub(EntityManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->service        = new JwtService($this->jwtManager, $this->em, $this->userRepository);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('$2y$04$hashed');

        return $user;
    }

    // ── createTokensForUser ────────────────────────────────────

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

        $service = new JwtService($jwtManager, $this->em, $this->userRepository);
        $service->createTokensForUser($this->makeUser());

        $this->assertArrayHasKey('user_id', $capturedPayload);
        $this->assertArrayHasKey('email', $capturedPayload);
        $this->assertSame('alice@example.com', $capturedPayload['email']);
    }

    // ── refreshAccessToken ────────────────────────────────────

    #[Test]
    public function refreshes_tokens_with_valid_refresh_token(): void
    {
        $user = $this->makeUser();
        $user->setRefreshToken('valid-refresh-token');
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('+1 day'));

        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByRefreshToken')->willReturn($user);

        $this->jwtManager->method('createFromPayload')->willReturn('new.jwt.access.token');

        $service = new JwtService($this->jwtManager, $this->em, $repo);
        $tokens  = $service->refreshAccessToken('valid-refresh-token');

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertSame('new.jwt.access.token', $tokens['access_token']);
    }

    #[Test]
    public function throws_on_invalid_refresh_token(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByRefreshToken')->willReturn(null);

        $service = new JwtService($this->jwtManager, $this->em, $repo);

        $this->expectException(InvalidRefreshTokenException::class);
        $service->refreshAccessToken('inexistente');
    }

    #[Test]
    public function throws_on_expired_refresh_token(): void
    {
        $user = $this->makeUser();
        $user->setRefreshToken('expired-token');
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('-1 day'));

        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByRefreshToken')->willReturn($user);

        $service = new JwtService($this->jwtManager, $this->em, $repo);

        $this->expectException(InvalidRefreshTokenException::class);
        $service->refreshAccessToken('expired-token');
    }

    #[Test]
    public function rotates_refresh_token_on_refresh(): void
    {
        $user = $this->makeUser();
        $user->setRefreshToken('old-refresh-token');
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('+1 day'));

        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByRefreshToken')->willReturn($user);

        $this->jwtManager->method('createFromPayload')->willReturn('new.jwt.access.token');

        $service = new JwtService($this->jwtManager, $this->em, $repo);
        $tokens  = $service->refreshAccessToken('old-refresh-token');

        $this->assertNotSame('old-refresh-token', $tokens['refresh_token']);
        $this->assertSame($tokens['refresh_token'], $user->getRefreshToken());
    }
}
