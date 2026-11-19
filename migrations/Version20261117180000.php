<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import employee and customer profiles from the supplied demonstration screenshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN staff_schedule VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN demo_open_task_count SMALLINT DEFAULT NULL CHECK (demo_open_task_count >= 0)');

        // Missing required contact fields remain empty. Reserved .invalid addresses
        // identify placeholders, not contact information extracted from the images.
        // Task counts are screenshot snapshots, not actual assigned task records.
        foreach ([
            ['employee', 'Sofia', 'Lemaire', 'sofia.lemaire@example.invalid', '', '', 'Livraison', 'Lun – Ven, 8 h – 16 h', 2],
            ['employee', 'Hugo', 'Martins', 'hugo.martins@example.invalid', '', '', 'Cuisine', 'Mar – Sam, 7 h – 15 h', 4],
            ['user', 'Lucie', 'Bernard', 'lucie@exemple.fr', '06 00 00 00 00', 'Bordeaux Caudéran', null, null, null],
        ] as [$role, $first, $last, $email, $phone, $city, $function, $schedule, $tasks]) {
            $this->addSql(<<<'SQL'
                INSERT INTO users (
                    role_id, first_name, last_name, email, password_hash,
                    phone, address_line1, postal_code, city,
                    staff_function, staff_schedule, demo_open_task_count
                ) VALUES (
                    (SELECT id FROM roles WHERE code = ?), ?, ?, ?, ?, ?, '', '', ?, ?, ?, ?
                ) ON CONFLICT (email) DO NOTHING
                SQL,
                [$role, $first, $last, $email,
                    password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    $phone, $city, $function, $schedule, $tasks]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Imported accounts may already be linked to business records.');
    }
}
