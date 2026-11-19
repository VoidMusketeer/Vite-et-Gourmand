<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117220000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE employee_mail_outbox ALTER COLUMN order_id DROP NOT NULL",
        );
        $this->addSql(
            "ALTER TABLE employee_mail_outbox ADD COLUMN employee_id UUID REFERENCES users(id) ON DELETE CASCADE",
        );
        $this->addSql(
            "CREATE UNIQUE INDEX employee_account_notification ON employee_mail_outbox(employee_id,kind) WHERE employee_id IS NOT NULL",
        );
        $this->addSql(
            "ALTER TABLE employee_mail_outbox ADD CONSTRAINT notification_owner CHECK (order_id IS NOT NULL OR employee_id IS NOT NULL)",
        );
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Les notifications de création doivent être conservées.",
        );
    }
}
