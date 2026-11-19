<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Compter le stock et les réservations en menus complets pour des groupes";
    }

    public function up(Schema $schema): void
    {
        // Existing stock values were entered by the operator as group menus.
        // Normalize earlier reservations so cancellation restores one group menu.
        $this->addSql(
            "UPDATE orders SET stock_reserved = 1 WHERE stock_reserved > 0",
        );
        $this->addSql(
            "COMMENT ON COLUMN menus.available_stock IS 'Nombre de menus complets disponibles pour des groupes'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Les anciennes réservations ont été converties en menus complets.",
        );
    }
}
