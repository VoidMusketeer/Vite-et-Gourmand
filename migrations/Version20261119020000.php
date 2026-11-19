<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Use mandatory theme and diet relations instead of catalogue JSON";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menus ADD diet_id SMALLINT REFERENCES diets(id) ON DELETE RESTRICT');
        // Preserve the labels currently displayed. Do not guess a classification.
        foreach ([['themes', 'theme', 'theme_id'], ['diets', 'tag', 'diet_id']] as [$table, $key, $column]) {
            $this->addSql("INSERT INTO $table (name,slug)
                SELECT DISTINCT trim(catalogue->>'$key'), 'import-' || md5(trim(catalogue->>'$key'))
                FROM menus WHERE NULLIF(trim(catalogue->>'$key'), '') IS NOT NULL
                ON CONFLICT (name) DO NOTHING");
            $this->addSql("UPDATE menus m SET $column=r.id FROM $table r
                WHERE r.name=trim(m.catalogue->>'$key')");
        }
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM menu_diets GROUP BY menu_id HAVING count(*) > 1) THEN
                    RAISE EXCEPTION 'Plusieurs régimes associés à un menu : choisir un régime avant la migration.';
                END IF;
                IF EXISTS (SELECT 1 FROM menus m JOIN menu_diets md ON md.menu_id=m.id
                    WHERE m.diet_id IS NOT NULL AND m.diet_id<>md.diet_id) THEN
                    RAISE EXCEPTION 'Conflit entre le régime JSON et menu_diets : réconcilier avant la migration.';
                END IF;
            END $$
            SQL);
        $this->addSql('UPDATE menus m SET diet_id=md.diet_id FROM menu_diets md WHERE md.menu_id=m.id AND m.diet_id IS NULL');
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM menus WHERE theme_id IS NULL OR diet_id IS NULL) THEN
                    RAISE EXCEPTION 'Thème ou régime manquant : compléter les menus avant la migration.';
                END IF;
            END $$
            SQL);
        $this->addSql('ALTER TABLE menus DROP CONSTRAINT menus_theme_id_fkey');
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT menus_theme_id_fkey FOREIGN KEY(theme_id) REFERENCES themes(id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE menus ALTER theme_id SET NOT NULL, ALTER diet_id SET NOT NULL');
        $this->addSql('CREATE INDEX menus_diet_id_idx ON menus(diet_id)');
        $this->addSql("UPDATE menus SET catalogue=catalogue - 'theme' - 'tag' WHERE catalogue IS NOT NULL");
        $this->addSql('DROP TABLE menu_diets');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE menu_diets (menu_id UUID NOT NULL REFERENCES menus(id) ON DELETE CASCADE, diet_id SMALLINT NOT NULL REFERENCES diets(id) ON DELETE RESTRICT, PRIMARY KEY(menu_id,diet_id))');
        $this->addSql('CREATE INDEX menu_diets_diet_idx ON menu_diets(diet_id)');
        $this->addSql('INSERT INTO menu_diets SELECT id,diet_id FROM menus');
        $this->addSql("UPDATE menus m SET catalogue=m.catalogue || jsonb_build_object('theme',t.name,'tag',d.name) FROM themes t,diets d WHERE t.id=m.theme_id AND d.id=m.diet_id AND m.catalogue IS NOT NULL");
        $this->addSql('ALTER TABLE menus DROP diet_id, ALTER theme_id DROP NOT NULL');
        $this->addSql('ALTER TABLE menus DROP CONSTRAINT menus_theme_id_fkey');
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT menus_theme_id_fkey FOREIGN KEY(theme_id) REFERENCES themes(id) ON DELETE SET NULL');
    }
}
