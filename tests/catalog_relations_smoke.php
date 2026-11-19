<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$db->beginTransaction();
try {
    $check((int) $db->fetchOne('SELECT count(*) FROM menus WHERE theme_id IS NULL OR diet_id IS NULL') === 0, 'Missing relations');
    $check((int) $db->fetchOne("SELECT count(*) FROM menus WHERE jsonb_exists(catalogue, 'theme') OR jsonb_exists(catalogue, 'tag')") === 0, 'Legacy JSON remains');
    $menu = $db->fetchAssociative("SELECT * FROM menus WHERE publication_status='published' AND catalogue IS NOT NULL LIMIT 1");
    $check((bool) $menu, 'A published menu is needed');
    $author = $db->fetchOne('SELECT id FROM users LIMIT 1');
    $theme = $db->fetchOne("INSERT INTO themes(name,slug) VALUES ('Test relation thème','test-relation-theme') RETURNING id");
    $diet = $db->fetchOne("INSERT INTO diets(name,slug) VALUES ('Test relation régime','test-relation-diet') RETURNING id");
    $publisher = new App\Catalog\MenuPublisher($db, new Symfony\Component\String\Slugger\AsciiSlugger(), dirname(__DIR__) . '/public');
    $data = [
        'title' => $menu['title'], 'description' => $menu['description'],
        'starter' => 'Entrée test', 'main_course' => 'Plat test', 'dessert' => 'Dessert test',
        'theme_id' => (string) $theme, 'diet_id' => (string) $diet,
        'min' => '4', 'price' => '25.00', 'state' => 'published',
        'stock' => (string) $menu['available_stock'], 'original_stock' => (string) $menu['available_stock'],
    ];
    $publisher->save($data, null, $author, $menu['id']);
    $catalog = new App\Catalog\MenuCatalog($db);
    $result = $catalog->find($menu['slug']);
    $check($result['theme'] === 'Test relation thème' && $result['tag'] === 'Test relation régime', 'Save/read relations');
    $db->executeStatement('UPDATE themes SET name=? WHERE id=?', ['Thème renommé', $theme]);
    $db->executeStatement('UPDATE diets SET name=? WHERE id=?', ['Régime renommé', $diet]);
    $filters = ['maxPrice' => 1000, 'guests' => 0, 'themes' => ['Thème renommé'], 'diets' => ['Régime renommé'], 'legacyTag' => 'Tous', 'day' => '', 'date' => '', 'search' => 'renommé', 'sort' => 'asc'];
    $check(isset($catalog->all(filters: $filters)[$menu['slug']]), 'Filters/search follow renamed references');
    $options = $catalog->filterOptions();
    $check(in_array('Régime renommé', $options['diets'], true), 'Dynamic diet options');
    foreach (['theme_id', 'diet_id'] as $field) {
        try {
            $publisher->save(array_replace($data, [$field => '0']), null, $author, $menu['id']);
            throw new RuntimeException('Invalid reference accepted');
        } catch (DomainException) {
            // Expected: reject invalid references before any write.
        }
    }
    echo "OK: migration, publication, relations, rename, filters, options and invalid references.\n";
} finally {
    $db->rollBack();
    $kernel->shutdown();
}
