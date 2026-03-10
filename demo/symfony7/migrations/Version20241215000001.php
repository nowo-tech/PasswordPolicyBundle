<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241215000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and password_history tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Note: Tables may already exist from init.sql, so we use IF NOT EXISTS
        $this->addSql('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, password_changed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS password_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_4E9C81F6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        // Add foreign key constraint if it doesn't exist
        $this->addSql("SET @constraint_check = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'FK_4E9C81F6A76ED395' AND TABLE_NAME = 'password_history');
        SET @sql = IF(@constraint_check = 0, 'ALTER TABLE password_history ADD CONSTRAINT FK_4E9C81F6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE', 'SELECT 1');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE password_history DROP FOREIGN KEY FK_4E9C81F6A76ED395');
        $this->addSql('DROP TABLE password_history');
        $this->addSql('DROP TABLE users');
    }
}
