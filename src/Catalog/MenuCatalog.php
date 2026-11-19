<?php

namespace App\Catalog;

use Doctrine\DBAL\Connection;

final class MenuCatalog
{
    public function __construct(private Connection $db) {}

    public function all(?string $slug = null, bool $homepageOnly = false, array $filters = []): array
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
        $params = [];
        if ($slug !== null) { $sql .= ' AND m.slug = ?'; $params[] = $slug; }
        if ($filters) {
            $sql .= ' AND round(m.minimum_price / m.minimum_people, 2) <= ?';
            $params[] = $filters['maxPrice'];
            if ($filters['guests']) { $sql .= ' AND m.minimum_people <= ?'; $params[] = $filters['guests']; }
            foreach (['themes' => 'theme', 'diets' => 'tag'] as $key => $field) {
                if ($filters[$key]) {
                    $sql .= " AND m.catalogue->>'$field' IN (".implode(',', array_fill(0, count($filters[$key]), '?')).')';
                    array_push($params, ...$filters[$key]);
                }
            }
            if ($filters['legacyTag'] !== 'Tous') { $sql .= " AND m.catalogue->>'tag' = ?"; $params[] = $filters['legacyTag']; }
            if ($filters['day']) {
                $sql .= " AND (COALESCE(m.catalogue->>'start', '') IN ('', '0') OR (m.catalogue->>'start' <= ? AND m.catalogue->>'end' >= ?))";
                array_push($params, $filters['day'], $filters['day']);
            }
            if ($filters['date']) {
                $sql .= ' AND (m.available_from IS NULL OR m.available_from::date::text <= ?) AND (m.available_until IS NULL OR m.available_until::date::text >= ?)';
                array_push($params, $filters['date'], $filters['date']);
            }
            // strpos keeps percent and underscore literal, like the original substring search.
            if ($filters['search'] !== '') {
                $sql .= " AND strpos(lower(m.title || ' ' || COALESCE(m.catalogue->>'tag', '') || ' ' || COALESCE(m.catalogue->>'theme', '')), lower(?)) > 0";
                $params[] = $filters['search'];
            }
            $sql .= ' ORDER BY price '.($filters['sort'] === 'desc' ? 'DESC' : 'ASC').', m.slug';
        }
        $rows = $this->db->fetchAllAssociative($sql, $params);
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

    public function filterOptions(): array
    {
        $rows = $this->db->fetchAllAssociative("SELECT catalogue->>'theme' AS theme, round(minimum_price / minimum_people, 2) AS price FROM menus WHERE publication_status = 'published' AND catalogue IS NOT NULL");
        return ['themes' => array_unique(array_column($rows, 'theme')), 'priceLimit' => (int) ceil(max([60, ...array_column($rows, 'price')]))];
    }
}
