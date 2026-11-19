<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261117210100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Import existing displayed opening hours and track reviewed dish allergens";
    }
    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE dishes ADD allergens_reviewed BOOLEAN NOT NULL DEFAULT FALSE",
        );
        // Preserve any business hours already entered. These are the former public footer hours.
        $this->addSql(
            "INSERT INTO opening_hours(day_of_week,opens_at,closes_at,is_closed) VALUES (1,'09:00','18:00',false),(2,'09:00','18:00',false),(3,'09:00','18:00',false),(4,'09:00','18:00',false),(5,'09:00','18:00',false),(6,'10:00','16:00',false),(7,NULL,NULL,true) ON CONFLICT(day_of_week) DO NOTHING",
        );
    }
    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dishes DROP allergens_reviewed");
    }
}
