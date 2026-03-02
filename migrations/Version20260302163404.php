<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302163404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add description, estimated_minutes and due_date to tasks';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tasks ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD estimated_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD due_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tasks DROP description');
        $this->addSql('ALTER TABLE tasks DROP estimated_minutes');
        $this->addSql('ALTER TABLE tasks DROP due_date');
    }
}
