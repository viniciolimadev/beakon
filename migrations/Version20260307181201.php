<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260307181201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create achievements and user_achievements tables with seed data';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE achievements (id UUID NOT NULL, achievement_key VARCHAR(64) NOT NULL, name VARCHAR(128) NOT NULL, description VARCHAR(255) NOT NULL, xp_bonus INT DEFAULT 0 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D1227EFEBBF2F0B8 ON achievements (achievement_key)');
        $this->addSql('CREATE TABLE user_achievements (id UUID NOT NULL, unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, achievement_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_51EE02FCA76ED395 ON user_achievements (user_id)');
        $this->addSql('CREATE INDEX IDX_51EE02FCB3EC99FE ON user_achievements (achievement_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_user_achievement ON user_achievements (user_id, achievement_id)');
        $this->addSql('ALTER TABLE user_achievements ADD CONSTRAINT FK_51EE02FCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_achievements ADD CONSTRAINT FK_51EE02FCB3EC99FE FOREIGN KEY (achievement_id) REFERENCES achievements (id) NOT DEFERRABLE');

        // Seed initial achievements
        $achievements = [
            ['gen_random_uuid()', 'first_task',    'Primeira Tarefa',      'Complete sua primeira tarefa',             50],
            ['gen_random_uuid()', 'tasks_10',      '10 Tarefas',           'Complete 10 tarefas',                     100],
            ['gen_random_uuid()', 'tasks_50',      '50 Tarefas',           'Complete 50 tarefas',                     300],
            ['gen_random_uuid()', 'streak_3',      'Sequência de 3 Dias',  'Mantenha uma sequência de 3 dias',        100],
            ['gen_random_uuid()', 'streak_7',      'Semana Perfeita',      'Mantenha uma sequência de 7 dias',        200],
            ['gen_random_uuid()', 'streak_30',     'Mês de Fogo',          'Mantenha uma sequência de 30 dias',       500],
            ['gen_random_uuid()', 'xp_100',        'Iniciante',            'Acumule 100 XP',                           50],
            ['gen_random_uuid()', 'xp_500',        'Experiente',           'Acumule 500 XP',                          100],
        ];

        foreach ($achievements as [$uuidExpr, $key, $name, $desc, $xp]) {
            $this->addSql(
                "INSERT INTO achievements (id, achievement_key, name, description, xp_bonus) VALUES ({$uuidExpr}, ?, ?, ?, ?)",
                [$key, $name, $desc, $xp]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_achievements DROP CONSTRAINT FK_51EE02FCA76ED395');
        $this->addSql('ALTER TABLE user_achievements DROP CONSTRAINT FK_51EE02FCB3EC99FE');
        $this->addSql('DROP TABLE achievements');
        $this->addSql('DROP TABLE user_achievements');
    }
}
