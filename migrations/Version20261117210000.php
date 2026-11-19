<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261117210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Employee order contact audit, equipment loans and reliable return notifications'; }
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders ADD equipment_loaned BOOLEAN NOT NULL DEFAULT FALSE, ADD equipment_description TEXT, ADD equipment_return_due DATE, ADD equipment_returned_at TIMESTAMPTZ");
        $this->addSql("CREATE TABLE order_contact_actions (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE, employee_id UUID REFERENCES users(id), contact_method VARCHAR(10) NOT NULL CHECK (contact_method IN ('phone','email')), contacted_at TIMESTAMPTZ NOT NULL, reason TEXT NOT NULL CHECK (length(trim(reason))>0), action VARCHAR(10) NOT NULL CHECK (action IN ('edit','cancel')), changes JSONB NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->addSql("CREATE TABLE employee_mail_outbox (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE, kind VARCHAR(40) NOT NULL, recipient TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, sent_at TIMESTAMPTZ, attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT, UNIQUE(order_id,kind))");
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE employee_mail_outbox, order_contact_actions');
        $this->addSql('ALTER TABLE orders DROP equipment_loaned, DROP equipment_description, DROP equipment_return_due, DROP equipment_returned_at');
    }
}
