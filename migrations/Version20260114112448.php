<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114112448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE csst.observation_document (observation_id INT NOT NULL, document_id UUID NOT NULL, PRIMARY KEY (observation_id, document_id))');
        $this->addSql('CREATE INDEX IDX_FDC681FB1409DD88 ON csst.observation_document (observation_id)');
        $this->addSql('CREATE INDEX IDX_FDC681FBC33F7837 ON csst.observation_document (document_id)');
        $this->addSql('ALTER TABLE csst.observation_document ADD CONSTRAINT FK_FDC681FB1409DD88 FOREIGN KEY (observation_id) REFERENCES csst.observation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE csst.observation_document ADD CONSTRAINT FK_FDC681FBC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA f3sct');
        $this->addSql('ALTER TABLE csst.observation_document DROP CONSTRAINT FK_FDC681FB1409DD88');
        $this->addSql('ALTER TABLE csst.observation_document DROP CONSTRAINT FK_FDC681FBC33F7837');
        $this->addSql('DROP TABLE csst.observation_document');
    }
}
