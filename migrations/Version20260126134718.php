<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260126134718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD nom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD prenom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD telephone VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD fonction VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD service VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD site VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA f3sct');
        $this->addSql('ALTER TABLE "user" DROP nom');
        $this->addSql('ALTER TABLE "user" DROP prenom');
        $this->addSql('ALTER TABLE "user" DROP telephone');
        $this->addSql('ALTER TABLE "user" DROP fonction');
        $this->addSql('ALTER TABLE "user" DROP service');
        $this->addSql('ALTER TABLE "user" DROP site');
    }
}
