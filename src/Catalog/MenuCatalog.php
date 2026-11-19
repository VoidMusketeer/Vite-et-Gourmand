<?php

namespace App\Catalog;

use Doctrine\DBAL\Connection;

final class MenuCatalog
{
    public function __construct(private Connection $db) {}

    public function all(?string $slug = null, bool $homepageOnly = false): array
    {
        $sql = "SELECT m.slug, m.title AS name, m.description, m.conditions, m.available_stock AS stock, m.minimum_people AS min, m.available_from::date AS first_date, m.available_until::date AS last_date,
                round(m.minimum_price / m.minimum_people, 2) AS price, m.order_lead_time_hours AS hours, m.catalogue,
                (SELECT json_agg(DISTINCT a.name) FROM menu_dishes md JOIN dish_allergens da ON da.dish_id=md.dish_id JOIN allergens a ON a.id=da.allergen_id WHERE md.menu_id=m.id) AS current_allergens,
                (SELECT bool_and(d.allergens_reviewed) FROM menu_dishes md JOIN dishes d ON d.id=md.dish_id WHERE md.menu_id=m.id) AS allergens_reviewed,
                (SELECT json_agg(json_build_object('url', url, 'alt', alt_text) ORDER BY is_cover DESC, display_order)
                 FROM menu_images WHERE menu_id = m.id) AS images,
                (SELECT json_agg(d.name ORDER BY md.display_order) FROM menu_dishes md
                 JOIN dishes d ON d.id = md.dish_id WHERE md.menu_id = m.id AND NOT md.is_optional) AS dishes
                FROM menus m WHERE m.publication_status = 'published' AND m.catalogue IS NOT NULL";
        if ($homepageOnly) $sql .= ' AND m.homepage_selected';
        $rows = $this->db->fetchAllAssociative($sql.($slug === null ? '' : ' AND m.slug = ?'), $slug === null ? [] : [$slug]);
        $menus = [];
        foreach ($rows as $row) {
            foreach (['catalogue', 'images', 'dishes'] as $key) {
                $row[$key] = json_decode($row[$key] ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            }
            $currentAllergens = json_decode($row['current_allergens'] ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            $row['allergens'] = $row['allergens_reviewed'] ? $currentAllergens : array_values(array_unique(array_merge($row['catalogue']['allergens'] ?? [], $currentAllergens)));
            $row['image'] = $row['images'][0]['url'] ?? '';
            $row['price'] = (float) $row['price'];
            $menus[$row['slug']] = $row + $row['catalogue'];
        }
        return $menus;
    }

    public function find(string $slug): ?array
    {
        return $this->all($slug)[$slug] ?? null;
    }
}
