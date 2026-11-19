<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261117200000 extends AbstractMigration
{
    public function getDescription(): string { return 'Persist personal employee daily delivery checks'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE employee_daily_checks (user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE, service_date DATE NOT NULL, check_code VARCHAR(20) NOT NULL CHECK (check_code IN ('vehicle','bags','route')), completed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id,service_date,check_code))");
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE employee_daily_checks'); }
}
