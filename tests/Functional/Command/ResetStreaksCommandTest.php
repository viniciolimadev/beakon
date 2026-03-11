<?php

namespace App\Tests\Functional\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ResetStreaksCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :pattern')
            ->execute(['pattern' => 'reset_streak_%@example.com']);

        parent::tearDown();
    }

    private function createUser(string $suffix, int $streak, ?\DateTimeImmutable $lastActivity): User
    {
        $user = new User();
        $user->setName("Reset {$suffix}");
        $user->setEmail("reset_streak_{$suffix}@example.com");
        $user->setPassword('x');
        $user->setStreakDays($streak);
        $user->setLastActivityDate($lastActivity);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function test_resets_streak_for_inactive_user(): void
    {
        $user = $this->createUser('inactive', 5, new \DateTimeImmutable('-2 days'));

        $app     = new Application(static::$kernel);
        $command = $app->find('app:reset-streaks');
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $this->em->clear();
        $updated = $this->em->find(User::class, $user->getId());

        $this->assertSame(0, $updated->getStreakDays());
    }

    public function test_does_not_reset_streak_for_active_today(): void
    {
        $user = $this->createUser('active', 7, new \DateTimeImmutable('today'));

        $app     = new Application(static::$kernel);
        $command = $app->find('app:reset-streaks');
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $this->em->clear();
        $updated = $this->em->find(User::class, $user->getId());

        $this->assertSame(7, $updated->getStreakDays());
    }

    public function test_does_not_reset_streak_for_active_yesterday(): void
    {
        $user = $this->createUser('yesterday', 3, new \DateTimeImmutable('yesterday'));

        $app     = new Application(static::$kernel);
        $command = $app->find('app:reset-streaks');
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $this->em->clear();
        $updated = $this->em->find(User::class, $user->getId());

        $this->assertSame(3, $updated->getStreakDays());
    }
}
