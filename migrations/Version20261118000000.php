<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menus ADD COLUMN homepage_selected BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE reviews ADD COLUMN homepage_selected BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql("UPDATE menus SET homepage_selected=TRUE WHERE publication_status='published' AND catalogue IS NOT NULL");
        $this->addSql("UPDATE reviews SET homepage_selected=TRUE WHERE moderation_status='approved'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menus DROP COLUMN homepage_selected');
        $this->addSql('ALTER TABLE reviews DROP COLUMN homepage_selected');
    }
}
