<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610111843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'esup_signature.sign_request: add server_id column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE esup_signature.sign_request ADD COLUMN IF NOT EXISTS server_id VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE esup_signature.sign_request DROP COLUMN IF EXISTS server_id');
    }
}
