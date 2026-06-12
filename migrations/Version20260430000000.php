<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création du schema esup_signature et de la table sign_request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE SCHEMA IF NOT EXISTS esup_signature");

        $this->addSql("
            CREATE TABLE esup_signature.sign_request (
                id             SERIAL PRIMARY KEY,
                esup_id        VARCHAR(255)  NOT NULL,
                esup_type      VARCHAR(32)   NOT NULL,
                case_type      VARCHAR(64)   NOT NULL,
                case_id        VARCHAR(255)  NOT NULL,
                create_by_eppn VARCHAR(255)  NOT NULL,
                status         VARCHAR(32)   NOT NULL DEFAULT 'pending',
                signed_file_key VARCHAR(512) NULL,
                callback_payload JSON        NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        ");

        $this->addSql("CREATE INDEX idx_esup_sign_request_esup_id   ON esup_signature.sign_request (esup_id)");
        $this->addSql("CREATE INDEX idx_esup_sign_request_case       ON esup_signature.sign_request (case_type, case_id)");
        $this->addSql("CREATE INDEX idx_esup_sign_request_status     ON esup_signature.sign_request (status)");

        $this->addSql("COMMENT ON TABLE esup_signature.sign_request IS 'Suivi local des demandes ESUP Signature'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS esup_signature.sign_request");
        $this->addSql("DROP SCHEMA IF EXISTS esup_signature CASCADE");
    }
}
