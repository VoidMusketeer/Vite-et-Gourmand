<?php
// Integration checks against the local database; all changes are rolled back.
declare(strict_types=1);
require dirname(__DIR__) . "/vendor/autoload.php";
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . "/.env");
class CustomerSmokeKernel extends App\Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . "/var/cache/customer_smoke";
    }
    protected function build(
        Symfony\Component\DependencyInjection\ContainerBuilder $container,
    ): void {
        parent::build($container);
        $container
            ->setAlias("smoke.tokens", "security.token_storage")
            ->setPublic(true);
        $container
            ->setAlias("smoke.csrf", "security.csrf.token_manager")
            ->setPublic(true);
        $container
            ->setAlias("smoke.hasher", "security.user_password_hasher")
            ->setPublic(true);
    }
}
function check(bool $value, string $label): void
{
    if (!$value) {
        throw new RuntimeException($label);
    }
    echo "PASS: $label\n";
}
$kernel = new CustomerSmokeKernel("dev", true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get("doctrine")->getManager();
$db = $em->getConnection();
$db->beginTransaction();
try {
    $ids = [];
    for ($i = 0; $i < 2; $i++) {
        $ids[] = $db->fetchOne(
            "INSERT INTO users(role_id,email,password_hash,first_name,last_name,phone,address_line1,postal_code,city) VALUES ((SELECT id FROM roles WHERE code='user'),?,?,'Test','Customer','0600000000','Test address','33000','Bordeaux') RETURNING id",
            [
                "customer-test-" .
                bin2hex(random_bytes(8)) .
                "@example.invalid",
                password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT),
            ],
        );
    }
    check(count($ids) === 2, "Isolated customer test accounts created");
    $user = $em->find(App\Entity\User::class, $ids[0]);
    $container
        ->get("smoke.tokens")
        ->setToken(
            new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
                $user,
                "main",
                $user->getRoles(),
            ),
        );
    $session = new Symfony\Component\HttpFoundation\Session\Session(
        new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage(),
    );
    $request = Symfony\Component\HttpFoundation\Request::create(
        "/compte/commandes",
    );
    $request->attributes->set("_route", "customer_orders");
    $request->setSession($session);
    $container->get("request_stack")->push($request);
    $controller = $container->get(
        App\Controller\CustomerAccountController::class,
    );
    $create = function (string $owner) use ($db): array {
        $ref = "VG-" . random_int(10000000, 99999999);
        $id = $db->fetchOne(
            "INSERT INTO orders(reference,user_id,current_status_id,service_date,delivery_time,delivery_address_line1,delivery_postal_code,delivery_city,menu_subtotal,total_amount) VALUES (?,?,(SELECT id FROM order_statuses WHERE code='pending'),'2099-01-02','12:00','Test address','33000','Bordeaux',100,100) RETURNING id",
            [$ref, $owner],
        );
        $menu = $db->fetchOne("SELECT id FROM menus LIMIT 1");
        $db->executeStatement(
            "INSERT INTO order_items(order_id,menu_id,menu_title,people_count,minimum_people_snapshot,unit_price_per_person,line_total) VALUES (?,?,'Test menu',10,1,10,100)",
            [$id, $menu],
        );
        return [$id, $ref];
    };
    [$id, $reference] = $create($ids[0]);
    [$other, $otherReference] = $create($ids[1]);
    $html = $controller->index("orders", $db)->getContent();
    check(
        str_contains($html, $reference) &&
            !str_contains($html, $otherReference),
        "Only own orders are rendered",
    );
    foreach (["orders", "history", "profile"] as $section) {
        check(
            $controller->index($section, $db)->getStatusCode() === 200,
            "Authenticated " . $section . " renders",
        );
        check(
            $controller->demo($section)->getStatusCode() === 200,
            "Demo " . $section . " renders",
        );
    }
    $post = function (string $order, array $data, bool $valid = true) use (
        $controller,
        $container,
        $session,
        $db,
    ) {
        $data["_token"] = $valid
            ? $container
                ->get("smoke.csrf")
                ->getToken("customer_order" . $order)
                ->getValue()
            : "invalid";
        $req = Symfony\Component\HttpFoundation\Request::create(
            "/compte/commandes/" . $order,
            "POST",
            $data,
        );
        $req->setSession($session);
        return $controller->saveOrder($order, $req, $db);
    };
    $denied = false;
    try {
        $post($id, ["action" => "cancel", "comment" => "test"], false);
    } catch (Symfony\Component\Security\Core\Exception\AccessDeniedException) {
        $denied = true;
    }
    check($denied, "Invalid CSRF token rejected");
    $denied = false;
    try {
        $post($other, ["action" => "cancel", "comment" => "test"]);
    } catch (Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        $denied = true;
    }
    check($denied, "Cannot modify another customer order");
    $post($id, [
        "action" => "edit",
        "time" => "13:30",
        "comment" => "Test delivery note",
    ]);
    check(
        $db->fetchOne("SELECT delivery_time FROM orders WHERE id=?", [$id]) ===
            "13:30:00",
        "Pending order delivery time saved",
    );
    $post($id, ["action" => "comment", "comment" => "Test customer comment"]);
    check(
        $db->fetchOne("SELECT customer_note FROM orders WHERE id=?", [$id]) ===
            "Test customer comment",
        "Customer comment saved",
    );
    $db->executeStatement(
        "UPDATE orders SET current_status_id=(SELECT id FROM order_statuses WHERE code='accepted') WHERE id=?",
        [$id],
    );
    $post($id, ["action" => "cancel", "comment" => "test"]);
    check(
        $db->fetchOne("SELECT cancelled_at FROM orders WHERE id=?", [$id]) ===
            null,
        "Accepted order cannot be cancelled",
    );
    $post($id, ["action" => "edit", "time" => "14:30", "comment" => "test"]);
    check(
        $db->fetchOne("SELECT delivery_time FROM orders WHERE id=?", [$id]) ===
            "13:30:00",
        "Accepted order cannot be edited",
    );
    $db->executeStatement(
        "UPDATE orders SET current_status_id=(SELECT id FROM order_statuses WHERE code='pending') WHERE id=?",
        [$id],
    );
    $post($id, ["action" => "cancel", "comment" => "Test cancellation"]);
    check(
        $db->fetchOne("SELECT cancelled_at FROM orders WHERE id=?", [$id]) !==
            null,
        "Pending order cancellation saved",
    );
    check(
        str_contains(
            $controller->index("history", $db)->getContent(),
            $reference,
        ),
        "Cancelled order appears in history",
    );
    check(
        (int) $db->fetchOne(
            "SELECT count(*) FROM order_status_history WHERE order_id=?",
            [$id],
        ) === 3,
        "Changes recorded in public history",
    );
    $data = [
        "field" => "phone",
        "phone" => "06 12 34 56 78",
        "_token" => $container
            ->get("smoke.csrf")
            ->getToken("customer_profile")
            ->getValue(),
    ];
    $req = Symfony\Component\HttpFoundation\Request::create(
        "/compte/parametres",
        "POST",
        $data,
    );
    $req->setSession($session);
    check(
        $controller
            ->saveProfile($req, $em, $container->get("smoke.hasher"))
            ->getStatusCode() === 303,
        "Profile save redirects",
    );
    check(
        $db->fetchOne("SELECT phone FROM users WHERE id=?", [$ids[0]]) ===
            $data["phone"],
        "Profile change persists",
    );
} finally {
    $db->rollBack();
    $kernel->shutdown();
}
echo "All customer checks passed; database changes rolled back.\n";
