<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN staff_function VARCHAR(32)');
        foreach (['Julie' => 'julie', 'José' => 'jose'] as $name => $login) {
            $this->addSql("INSERT INTO users (role_id,first_name,last_name,email,password_hash,phone,address_line1,postal_code,city)
                VALUES ((SELECT id FROM roles WHERE code='administrator'), ?, '', ?, ?, '', '', '', '')",
                [$name, $login.'@vite-et-gourmand.fr', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les profils peuvent déjà être associés à des opérations.');
    }
}
