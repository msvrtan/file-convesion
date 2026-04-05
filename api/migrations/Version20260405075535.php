<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405075535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create conversion table for conversion request lifecycle tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversion (id CHAR(36) NOT NULL, owner_id CHAR(36) NOT NULL, source_format VARCHAR(32) NOT NULL, target_format VARCHAR(32) NOT NULL, message CLOB DEFAULT NULL, created_at DATETIME NOT NULL, processing_started_at DATETIME DEFAULT NULL, processing_ended_at DATETIME DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_conversion_owner_id ON conversion (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE conversion');
    }
}
