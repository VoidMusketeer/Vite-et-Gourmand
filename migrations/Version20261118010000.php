<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118010000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE orders ADD COLUMN stock_reserved INTEGER NOT NULL DEFAULT 0 CHECK (stock_reserved >= 0)",
        );
        $this->addSql(
            "CREATE TABLE checkout_submissions (user_id UUID NOT NULL REFERENCES users(id), token VARCHAR(64) NOT NULL, references_json JSONB NOT NULL, PRIMARY KEY(user_id,token))",
        );
        $this->addSql(
            "CREATE SEQUENCE checkout_reference_seq START 1000000000",
        );
        $this->addSql(
            <<<'SQL'
            CREATE FUNCTION restore_cancelled_stock() RETURNS trigger AS $$
            BEGIN
                IF NEW.stock_reserved > 0 AND EXISTS (SELECT 1 FROM order_statuses WHERE id=NEW.current_status_id AND code='cancelled') THEN
                    UPDATE menus SET available_stock=available_stock+NEW.stock_reserved
                    WHERE id=(SELECT menu_id FROM order_items WHERE order_id=NEW.id);
                    NEW.stock_reserved := 0;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL
            ,
        );
        $this->addSql(
            "CREATE TRIGGER orders_restore_stock BEFORE UPDATE OF current_status_id ON orders FOR EACH ROW EXECUTE FUNCTION restore_cancelled_stock()",
        );
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Les réservations de stock doivent être conservées.",
        );
    }
}
