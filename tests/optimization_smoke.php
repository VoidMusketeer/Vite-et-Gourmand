<?php
// Read comparisons and isolated pagination fixtures; PostgreSQL writes are rolled back.
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
class OptimizationSmokeKernel extends App\Kernel
{
    public function getProjectDir(): string { return dirname(__DIR__); }
    public function getCacheDir(): string { return $this->getProjectDir().'/var/cache/optimization_smoke'; }
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->setAlias('smoke.twig', 'twig')->setPublic(true);
        $container->setAlias('smoke.statistics', App\Statistics\AdminStatistics::class)->setPublic(true);
    }
}
$kernel = new OptimizationSmokeKernel('dev', true);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
function verify(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    // Keep successful comparisons quiet; failures identify the exact case.
}
$catalog = new App\Catalog\MenuCatalog($db);
$all = $catalog->all();
$options = $catalog->filterOptions();
verify($options['priceLimit'] === (int) ceil(max([60, ...array_column($all, 'price')])), 'Price slider maximum unchanged');
verify(array_values($options['themes']) === array_values(array_unique(array_column($all, 'theme'))), 'Theme options unchanged');
$base = ['guests'=>0, 'themes'=>[], 'diets'=>[], 'legacyTag'=>'Tous', 'day'=>'', 'date'=>'', 'maxPrice'=>$options['priceLimit'], 'search'=>'', 'sort'=>'asc'];
$cases = [[], ['guests'=>5], ['guests'=>50], ['maxPrice'=>0], ['maxPrice'=>30], ['search'=>'MENU'], ['search'=>'é'], ['search'=>'%'], ['search'=>'_'], ['search'=>'missing-menu'], ['date'=>'2026-12-25','day'=>'12-25'], ['date'=>'2099-01-02','day'=>'01-02'], ['themes'=>['unknown']], ['diets'=>['unknown']]];
foreach ($all as $menu) {
    $cases[] = ['themes'=>[$menu['theme']], 'diets'=>[$menu['tag']]];
    $cases[] = ['legacyTag'=>$menu['tag'], 'guests'=>$menu['min']];
}
foreach ($cases as $changes) foreach (['asc','desc'] as $sort) {
    $f = array_replace($base, $changes, ['sort'=>$sort]);
    extract($f);
    $expected = array_filter($all, static fn(array $menu): bool =>
        (!$guests || $guests >= $menu['min']) && $menu['price'] <= $maxPrice
        && (!$themes || in_array($menu['theme'], $themes, true))
        && (!$diets || in_array($menu['tag'], $diets, true))
        && ($legacyTag === 'Tous' || $menu['tag'] === $legacyTag)
        && (!$day || !$menu['start'] || ($day >= $menu['start'] && $day <= $menu['end']))
        && (!$date || ((!$menu['first_date'] || $date >= $menu['first_date']) && (!$menu['last_date'] || $date <= $menu['last_date'])))
        && ($search === '' || mb_stripos($menu['name'].' '.$menu['tag'].' '.$menu['theme'], $search) !== false));
    $actual = $catalog->all(filters: $f);
    $expectedKeys = array_keys($expected); $actualKeys = array_keys($actual);
    sort($expectedKeys); sort($actualKeys);
    verify($actualKeys === $expectedKeys, 'Catalogue filter parity '.json_encode($changes).' '.$sort);
    foreach ($actual as $slug=>$menu) verify($menu === $all[$slug], 'Menu detail data preserved: '.$slug);
    $prices = array_column($actual, 'price'); $sorted = $prices;
    $sort === 'desc' ? rsort($sorted) : sort($sorted);
    verify($prices === $sorted, 'Catalogue price ordering preserved');
}
$db->beginTransaction();
try {
    $owner = $db->fetchOne("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='user' LIMIT 1");
    $menuId = $db->fetchOne('SELECT id FROM menus LIMIT 1');
    $prefix = 'VG-'.random_int(100000,999999);
    for ($i=0; $i<55; $i++) {
        $id = $db->fetchOne("INSERT INTO orders(reference,user_id,current_status_id,service_date,delivery_time,delivery_address_line1,delivery_postal_code,delivery_city,menu_subtotal,total_amount) VALUES (?,?,(SELECT id FROM order_statuses WHERE code='pending'),'2099-01-02','12:00','Test','33000','Bordeaux',100,100) RETURNING id", [$prefix.sprintf('%02d',$i),$owner]);
        $db->executeStatement("INSERT INTO order_items(order_id,menu_id,menu_title,people_count,minimum_people_snapshot,unit_price_per_person,line_total) VALUES (?,?,'Pagination fixture',10,1,10,100)", [$id,$menuId]);
    }
    $workspace = new App\Employee\EmployeeWorkspace($db);
    $full = $workspace->orders($prefix,'2099-01-02','pending');
    $first = $workspace->orders($prefix,'2099-01-02','pending',1);
    $second = $workspace->orders($prefix,'2099-01-02','pending',2);
    verify(count($first) === 51 && count($second) === 5, 'Pagination detects next page and final page');
    verify(array_merge(array_slice($first,0,50), $second) === $full, 'Pagination preserves every filtered row without overlap');
    verify($workspace->orders($prefix,'2099-01-02','accepted',1) === [], 'Status filter retained during pagination');
    $twig = $kernel->getContainer()->get('smoke.twig');
    $request = Symfony\Component\HttpFoundation\Request::create('/employe/commandes?q=test&status=pending&page=2');
    $request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
    $request->attributes->set('_route','employee_orders');
    $kernel->getContainer()->get('request_stack')->push($request);
    $html = $twig->render('components/pagination.html.twig', ['page'=>2,'hasNext'=>true]);
    verify(str_contains($html,'page=1') && str_contains($html,'page=3') && str_contains($html,'status=pending') && str_contains($html,'q=test'), 'Pagination links retain search and status');
    $request->attributes->set('_route','admin_orders');
    $admin = $kernel->getContainer()->get(App\Controller\AdminController::class);
    $firstRequest = clone $request;
    $firstRequest->query->set('page', 1);
    $firstHtml = $admin->orders($firstRequest)->getContent();
    $secondHtml = $admin->orders($request)->getContent();
    verify(substr_count($firstHtml, '<div class="entry">') === 50 && str_contains($firstHtml, 'rel="next"'), 'Admin first page has 50 orders and next link');
    verify(str_contains($secondHtml, 'rel="prev"'), 'Admin second page renders with return navigation');
    $request->attributes->set('_route','app_menus');
    $response = $kernel->getContainer()->get(App\Controller\PublicSite\NavigationController::class)->menus(Symfony\Component\HttpFoundation\Request::create('/menus?price=30&sort=desc'));
    verify($response->getStatusCode() === 200, 'Filtered public catalogue renders successfully');
    $kernel->getContainer()->get('request_stack')->pop();
    // Compare selected rows locally; never invoke Node or transfer data to MongoDB.
    $db->executeStatement("UPDATE orders SET current_status_id=(SELECT id FROM order_statuses WHERE code='completed') WHERE id=?", [$id]);
    $export = $db->fetchAllAssociative("SELECT o.id AS order_id,i.menu_id,o.service_date,s.code AS status,round(GREATEST(0,i.line_total-o.discount_amount)*100)::bigint AS revenue_cents FROM orders o JOIN order_items i ON i.order_id=o.id JOIN order_statuses s ON s.id=o.current_status_id");
    $statistics = $kernel->getContainer()->get('smoke.statistics');
    foreach ([['','',''],[$menuId,'2099-01-02','2099-01-02'],['','2100-01-01','2100-01-02'],['','2099-01-02',''],['','','2026-01-01']] as [$selected,$from,$until]) {
        $expected = array_values(array_filter($export, static fn ($row) => $row['status'] !== 'cancelled'
            && (!$selected || $row['menu_id'] === $selected)
            && (!$from || $row['service_date'] >= $from)
            && (!$until || $row['service_date'] <= $until)));
        $method = new ReflectionMethod($statistics, 'filteredOrders');
        $actual = $method->invoke($statistics, $selected, $from, $until);
        $compare = static fn ($a, $b) => [$a['order_id'],$a['menu_id'],$a['revenue_cents']] <=> [$b['order_id'],$b['menu_id'],$b['revenue_cents']];
        usort($expected, $compare); usort($actual, $compare);
        verify($actual === $expected, 'Statistics rows and revenue preserved for '.json_encode([$selected,$from,$until]));
    }
} finally {
    $db->rollBack();
    $kernel->shutdown();
}
verify(App\Security\AccountValidation::password('abcdefghijkl','abcdefghijkl'), 'Existing valid password accepted');
verify(!App\Security\AccountValidation::password('short','short'), 'Short password rejected');
verify(!App\Security\AccountValidation::password(str_repeat('a',73),str_repeat('a',73)), 'Password byte limit preserved');
verify(!App\Security\AccountValidation::password('abcdefghijkl','different'), 'Password confirmation enforced');
echo "Optimization checks passed; fixtures rolled back.\n";
