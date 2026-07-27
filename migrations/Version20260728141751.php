<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728141751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game ADD release_year INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD protagonist VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE game DROP coordinates');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game ADD coordinates VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE game DROP release_year');
        $this->addSql('ALTER TABLE game DROP protagonist');
    }
}
