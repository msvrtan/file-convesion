<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer table for seeded API users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer (id CHAR(36) NOT NULL, username VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_username ON customer (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer');
    }
}
