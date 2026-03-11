<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reset-streaks',
    description: 'Zera o streak de usuários que não completaram tarefas ontem.',
)]
final class ResetStreaksCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $yesterday = new \DateTimeImmutable('yesterday');

        $count = $this->em->createQuery(
            'UPDATE App\Entity\User u SET u.streakDays = 0
             WHERE u.streakDays > 0
               AND (u.lastActivityDate IS NULL OR u.lastActivityDate < :yesterday)'
        )
        ->setParameter('yesterday', $yesterday->setTime(0, 0, 0))
        ->execute();

        $output->writeln("Streaks zerados: {$count}");

        return Command::SUCCESS;
    }
}
