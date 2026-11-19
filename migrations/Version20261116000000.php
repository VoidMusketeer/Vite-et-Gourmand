<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE menus ADD COLUMN catalogue JSONB");
        $menus = json_decode(
            file_get_contents(
                __DIR__ . "/../database/postgresql/menu-details.json",
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        foreach ($menus as $menu) {
            $metadata = array_intersect_key(
                $menu,
                array_flip([
                    "tag",
                    "theme",
                    "season",
                    "start",
                    "end",
                    "service",
                    "allergens",
                    "options",
                ]),
            );
            $this->addSql(
                "INSERT INTO menus (slug,title,description,conditions,minimum_people,minimum_price,order_lead_time_hours,publication_status,catalogue)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'published', ?)",
                [
                    $menu["slug"],
                    $menu["name"],
                    $menu["description"],
                    "Livré froid en plateaux. Réchauffer 20 minutes à 160 °C. Couverts non inclus. En cas d’allergie sévère, contactez-nous avant toute demande.",
                    $menu["min"],
                    $menu["min"] * $menu["price"],
                    $menu["hours"],
                    json_encode($metadata, JSON_THROW_ON_ERROR),
                ],
            );
            foreach ($menu["images"] as $i => $image) {
                $this->addSql(
                    'INSERT INTO menu_images (menu_id,url,alt_text,display_order,is_cover)
                    SELECT id, ?, ?, ?, ?::integer = 0 FROM menus WHERE slug = ?',
                    [
                        $image,
                        $menu["name"] . " — photo " . ($i + 1),
                        $i,
                        $i,
                        $menu["slug"],
                    ],
                );
            }
            foreach ($menu["dishes"] as $i => $dish) {
                $this->addSql(
                    'WITH dish AS (INSERT INTO dishes (name,dish_type_id)
                    SELECT ?, id FROM dish_types WHERE code = ? RETURNING id)
                    INSERT INTO menu_dishes (menu_id,dish_id,display_order)
                    SELECT menus.id, dish.id, ? FROM menus CROSS JOIN dish WHERE menus.slug = ?',
                    [
                        $dish,
                        ["starter", "main_course", "dessert"][$i],
                        $i,
                        $menu["slug"],
                    ],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Les menus peuvent être associés à des commandes.",
        );
    }
}
