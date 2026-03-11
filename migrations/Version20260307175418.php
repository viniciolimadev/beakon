<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260307175418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pomodoro_sessions (id UUID NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed BOOLEAN DEFAULT NULL, duration_minutes INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, task_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3E0EE2E0A76ED395 ON pomodoro_sessions (user_id)');
        $this->addSql('CREATE INDEX IDX_3E0EE2E08DB60186 ON pomodoro_sessions (task_id)');
        $this->addSql('ALTER TABLE pomodoro_sessions ADD CONSTRAINT FK_3E0EE2E0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pomodoro_sessions ADD CONSTRAINT FK_3E0EE2E08DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pomodoro_sessions DROP CONSTRAINT FK_3E0EE2E0A76ED395');
        $this->addSql('ALTER TABLE pomodoro_sessions DROP CONSTRAINT FK_3E0EE2E08DB60186');
        $this->addSql('DROP TABLE pomodoro_sessions');
    }
}
