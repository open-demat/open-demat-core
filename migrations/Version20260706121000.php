<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passe user_document.user_id en ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_document ALTER COLUMN user_id DROP NOT NULL');
        $this->dropUserDocumentUserForeignKey();
        $this->addSql('ALTER TABLE user_document ADD CONSTRAINT FK_USER_DOCUMENT_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM user_document WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE user_document DROP CONSTRAINT FK_USER_DOCUMENT_USER');
        $this->addSql('ALTER TABLE user_document ADD CONSTRAINT FK_USER_DOCUMENT_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_document ALTER COLUMN user_id SET NOT NULL');
    }

    private function dropUserDocumentUserForeignKey(): void
    {
        $this->addSql(<<<'SQL'
DO $$
DECLARE
    constraint_name text;
BEGIN
    SELECT tc.constraint_name INTO constraint_name
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu
        ON tc.constraint_name = kcu.constraint_name
        AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage ccu
        ON ccu.constraint_name = tc.constraint_name
        AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY'
        AND tc.table_schema = 'public'
        AND tc.table_name = 'user_document'
        AND kcu.column_name = 'user_id'
        AND ccu.table_name = 'user'
    LIMIT 1;

    IF constraint_name IS NOT NULL THEN
        EXECUTE format('ALTER TABLE user_document DROP CONSTRAINT %I', constraint_name);
    END IF;
END $$;
SQL);
    }
}
