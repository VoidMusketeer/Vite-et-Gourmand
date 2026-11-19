<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117190100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Apply requested credentials to existing Sofia and Hugo Gmail profiles";
    }

    public function up(Schema $schema): void
    {
        foreach (
            [
                ["Sofia", "Lemaire", "Livraison"],
                ["Hugo", "Martins", "Chef cuisinier / Traiteur"],
            ]
            as [$first, $last, $position]
        ) {
            $this->addSql(
                "UPDATE users SET password_hash=?, staff_function=? WHERE email=? AND first_name=? AND last_name=? AND role_id=(SELECT id FROM roles WHERE code='employee')",
                [
                    password_hash($first . "Employe12345!", PASSWORD_BCRYPT),
                    $position,
                    strtolower($first . "." . $last) . "@gmail.com",
                    $first,
                    $last,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Previous password hashes cannot be restored.",
        );
    }
}
