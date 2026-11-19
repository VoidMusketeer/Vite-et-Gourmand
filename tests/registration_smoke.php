<?php
// Run against the local database; test accounts are rolled back and no email is sent.
declare(strict_types=1);
require dirname(__DIR__) . "/vendor/autoload.php";
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . "/.env");
class RegistrationSmokeKernel extends App\Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . "/var/cache/registration_smoke";
    }
    protected function build(
        Symfony\Component\DependencyInjection\ContainerBuilder $container,
    ): void {
        parent::build($container);
        $container
            ->setAlias("test.csrf", "security.csrf.token_manager")
            ->setPublic(true);
        $container
            ->setAlias("test.hasher", "security.user_password_hasher")
            ->setPublic(true);
        $container->setAlias("test.mailer", "mailer.mailer")->setPublic(true);
    }
}
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
$kernel = new RegistrationSmokeKernel("dev", true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get("doctrine")->getManager();
$db = $em->getConnection();
$mailer = new class implements Symfony\Component\Mailer\MailerInterface {
    public array $messages = [];
    public function send(
        Symfony\Component\Mime\RawMessage $message,
        ?Symfony\Component\Mailer\Envelope $envelope = null,
    ): void {
        $this->messages[] = $message;
    }
};
$session = new Symfony\Component\HttpFoundation\Session\Session(
    new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage(),
);
$root = Symfony\Component\HttpFoundation\Request::create("/inscription");
$root->setSession($session);
$container->get("request_stack")->push($root);
$controller = $container->get(App\Controller\RegistrationController::class);
$hasher = $container->get("test.hasher");
$call = function (string $method, array $data = []) use (
    $controller,
    $session,
    $em,
    $hasher,
    $mailer,
) {
    $request = Symfony\Component\HttpFoundation\Request::create(
        "/inscription",
        $method,
        $data,
    );
    $request->setSession($session);
    return $controller->register(
        $request,
        $em,
        $hasher,
        $mailer,
        "test@example.invalid",
    );
};
$db->beginTransaction();
try {
    $response = $call("GET");
    check(
        $response->getStatusCode() === 200 &&
            str_contains($response->getContent(), "Numéro et voie"),
        "Registration renders with complete address",
    );
    $data = [
        "_token" => $container
            ->get("test.csrf")
            ->getToken("registration")
            ->getValue(),
        "firstName" => "Test",
        "lastName" => "Inscription",
        "email" =>
            "registration-" . bin2hex(random_bytes(6)) . "@example.invalid",
        "phone" => "06 12 34 56 78",
        "addressLine1" => "12 rue du Test",
        "addressLine2" => "Bâtiment A",
        "postalCode" => "33000",
        "city" => "Bordeaux",
        "password" => "Exemple!12345",
        "confirmation" => "Exemple!12345",
        "terms" => "1",
    ];
    $denied = false;
    try {
        $call("POST", array_replace($data, ["_token" => "invalid"]));
    } catch (Symfony\Component\Security\Core\Exception\AccessDeniedException) {
        $denied = true;
    }
    check($denied, "Invalid CSRF rejected");
    foreach (
        [
            "firstName",
            "lastName",
            "phone",
            "email",
            "addressLine1",
            "postalCode",
            "city",
            "terms",
        ]
        as $field
    ) {
        check(
            $call(
                "POST",
                array_replace($data, [$field => ""]),
            )->getStatusCode() === 422,
            "Required $field validated on server",
        );
    }
    foreach (
        [
            "Aa1!short",
            "minuscules!123",
            "MAJUSCULES!123",
            "SansChiffre!",
            "SansSpecial123",
            "Avec Espace123",
        ]
        as $weak
    ) {
        check(
            $call(
                "POST",
                array_replace($data, [
                    "password" => $weak,
                    "confirmation" => $weak,
                ]),
            )->getStatusCode() === 422,
            "Weak password rejected: " . $weak,
        );
    }
    check(
        $call(
            "POST",
            array_replace($data, ["confirmation" => "different"]),
        )->getStatusCode() === 422,
        "Password mismatch rejected",
    );
    check(
        count($mailer->messages) === 0,
        "Invalid registrations send no email",
    );
    $response = $call(
        "POST",
        array_replace($data, ["role" => "administrator"]),
    );
    check(
        $response->getStatusCode() === 303 &&
            $response->headers->get("Location") === "/connexion",
        "Successful registration redirects to login",
    );
    $user = $em
        ->getRepository(App\Entity\User::class)
        ->findOneBy(["email" => $data["email"]]);
    check(
        $user &&
            $user->getRole()->getCode() === "user" &&
            $user->getRoles() === ["ROLE_USER"],
        "User role enforced regardless of submitted role",
    );
    check(
        $hasher->isPasswordValid($user, $data["password"]) &&
            $user->getPassword() !== $data["password"],
        "Password securely hashed",
    );
    check(
        $user->getAddressLine2() === "Bâtiment A" &&
            $user->getPhone() === "0612345678",
        "Address and normalized mobile saved",
    );
    check(
        count($mailer->messages) === 1 &&
            $mailer->messages[0]->getTo()[0]->getAddress() === $data["email"] &&
            str_contains($mailer->messages[0]->getSubject(), "Bienvenue"),
        "Welcome email dispatched to new user",
    );
    check(
        $call("POST", $data)->getStatusCode() === 422 &&
            count($mailer->messages) === 1,
        "Duplicate email rejected without another email",
    );
    check(
        $call(
            "POST",
            array_replace($data, ["email" => strtoupper($data["email"])]),
        )->getStatusCode() === 422,
        "Case-insensitive duplicate email rejected",
    );
    // Exercise the actual Doctrine transport as well: mocking the mailer alone
    // cannot detect a missing messenger_messages table. The outer transaction
    // keeps this message invisible to workers and rolls it back with the account.
    $queueBefore = (int) $db->fetchOne(
        "SELECT COUNT(*) FROM messenger_messages",
    );
    $queuedData = array_replace($data, [
        "email" => "queued-" . bin2hex(random_bytes(6)) . "@example.invalid",
    ]);
    $queuedRequest = Symfony\Component\HttpFoundation\Request::create(
        "/inscription",
        "POST",
        $queuedData,
    );
    $queuedRequest->setSession($session);
    $queuedResponse = $controller->register(
        $queuedRequest,
        $em,
        $hasher,
        $container->get("test.mailer"),
        "test@example.invalid",
    );
    check(
        $queuedResponse->getStatusCode() === 303,
        "Registration succeeds with the real mail queue",
    );
    check(
        (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages") ===
            $queueBefore + 1,
        "Welcome email persisted in messenger_messages",
    );
    check(
        (bool) $em
            ->getRepository(App\Entity\User::class)
            ->findOneBy(["email" => $queuedData["email"]]),
        "Account and queued email saved in the same transaction",
    );
} finally {
    $db->rollBack();
    $kernel->shutdown();
}
