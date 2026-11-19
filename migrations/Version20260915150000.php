<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create the Vite et Gourmand catalogue, accounts, orders, payments and reference data";
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !(
                $this->connection->getDatabasePlatform() instanceof
                PostgreSQLPlatform
            ),
            "This migration requires PostgreSQL.",
        );

        // Keep these versioned SQL files unchanged after deploying this migration.
        // Doctrine owns the transaction; remove the standalone script wrappers.
        foreach (
            [
                "001_schema.sql",
                "002_seed_reference_data.sql",
                "003_payments.sql",
            ]
            as $file
        ) {
            $sql = file_get_contents(
                __DIR__ . "/../database/postgresql/" . $file,
            );
            if ($sql === false) {
                throw new \RuntimeException(
                    "Cannot read database migration SQL: " . $file,
                );
            }
            $sql = preg_replace('/^(?:BEGIN|COMMIT);\s*$/m', "", $sql);
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Contains customer and order history. Restore a backup to roll back.",
        );
    }
}
