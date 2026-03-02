<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\InvalidRefreshTokenException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class JwtService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {}

    public function createTokensForUser(User $user): array
    {
        $accessToken = $this->jwtManager->createFromPayload($user, [
            'user_id' => (string) $user->getId(),
            'email'   => $user->getEmail(),
        ]);

        $refreshToken = bin2hex(random_bytes(32));

        $user->setRefreshToken($refreshToken);
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('+7 days'));

        $this->em->flush();

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $user = $this->userRepository->findByRefreshToken($refreshToken);

        if ($user === null || $user->getRefreshTokenExpiresAt() <= new \DateTimeImmutable()) {
            throw new InvalidRefreshTokenException();
        }

        $accessToken = $this->jwtManager->createFromPayload($user, [
            'user_id' => (string) $user->getId(),
            'email'   => $user->getEmail(),
        ]);

        $newRefreshToken = bin2hex(random_bytes(32));

        $user->setRefreshToken($newRefreshToken);
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('+7 days'));

        $this->em->flush();

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
        ];
    }
}
