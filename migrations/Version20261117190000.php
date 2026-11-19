<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add catering positions, responsibilities and sample employee accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN staff_function TYPE VARCHAR(100)');
        $this->addSql('CREATE TABLE staff_positions (name VARCHAR(100) PRIMARY KEY, department VARCHAR(80) NOT NULL, responsibilities TEXT NOT NULL, service_context TEXT NOT NULL)');
        foreach ([
            ['Chef cuisinier / Traiteur', 'Cuisine', 'Coordonne la création des menus, supervise la préparation et garantit les normes d’hygiène (HACCP).', 'Cocktail, buffet, repas assis.'],
            ['Second de cuisine / Commis', 'Cuisine', 'Assiste à la confection des plats, à la découpe et à la mise en place.', 'Cocktail, buffet, repas assis.'],
            ['Directeur de salle', 'Salle et service', 'Gère la coordination globale des équipes et du service.', 'Grands événements de plus de 200 convives.'],
            ['Maître d’hôtel', 'Salle et service', 'Encadre l’équipe de serveurs et fait le lien entre la cuisine et les clients.', 'Cocktail, buffet, repas assis.'],
            ['Chef de rang', 'Salle et service', 'Supervise un secteur ou des tables spécifiques.', 'Repas assis.'],
            ['Serveurs / Extras', 'Salle et service', 'Assurent le service à l’assiette, le réassort des buffets et le débarrassage.', 'Cocktail, buffet, repas assis.'],
            ['Barmans / Mixologues', 'Salle et service', 'Gèrent le bar, la création de cocktails et les boissons.', 'Prestations avec service de bar.'],
            ['Sommelier', 'Salle et service', 'Conseille les clients et assure le service des vins.', 'Selon le standing de la prestation.'],
            ['Hôtes / Hôtesses d’accueil', 'Salle et service', 'Accueillent et orientent les invités à leur arrivée.', 'Accueil des événements.'],
            ['Livraison', 'Logistique', 'Assure le transport et la remise des commandes.', 'Livraison des commandes.'],
            ['Cuisine', 'Cuisine', 'Participe à la préparation des plats.', 'Fonction générale existante.'],
        ] as $position) {
            $this->addSql('INSERT INTO staff_positions (name, department, responsibilities, service_context) VALUES (?, ?, ?, ?)', $position);
        }

        foreach ([
            ['Hugo', 'Martins', 'Chef cuisinier / Traiteur'],
            ['Sofia', 'Lemaire', 'Livraison'],
            ['Lucas', 'Moreau', 'Second de cuisine / Commis'],
            ['Camille', 'Dubois', 'Directeur de salle'],
            ['Antoine', 'Laurent', 'Maître d’hôtel'],
            ['Emma', 'Petit', 'Chef de rang'],
            ['Louis', 'Roux', 'Serveurs / Extras'],
            ['Chloe', 'Garnier', 'Barmans / Mixologues'],
            ['Gabriel', 'Faure', 'Sommelier'],
            ['Manon', 'Chevalier', 'Hôtes / Hôtesses d’accueil'],
        ] as [$first, $last, $position]) {
            $email = strtolower($first.'.'.$last).'@gmail.com';
            $hash = password_hash($first.'Employe12345!', PASSWORD_BCRYPT);
            // Update the exact imported identities in place, preserving their IDs,
            // schedules and references. Never overwrite an unrelated email owner.
            if (in_array($first, ['Hugo', 'Sofia'], true)) {
                $this->addSql("UPDATE users SET email=?, password_hash=?, staff_function=? WHERE email=? AND first_name=? AND last_name=? AND role_id=(SELECT id FROM roles WHERE code='employee')",
                    [$email, $hash, $position, strtolower($first.'.'.$last).'@example.invalid', $first, $last]);
            }
            $this->addSql(<<<'SQL'
                INSERT INTO users (role_id, first_name, last_name, email, password_hash, phone, address_line1, postal_code, city, staff_function)
                SELECT (SELECT id FROM roles WHERE code='employee'), ?, ?, ?, ?, '', '', '', '', ?
                WHERE NOT EXISTS (SELECT 1 FROM users WHERE email=?)
                SQL, [$first, $last, $email, $hash, $position, $email]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Employee profiles may be linked to business records.');
    }
}
