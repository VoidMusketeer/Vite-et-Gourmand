<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\{Role, User};
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: "app:seed-customers",
    description: "Create five sample customers and their order histories without purging existing data.",
)]
final class SeedCustomersCommand extends Command
{
    public function __construct(
        private Connection $db,
        private UserPasswordHasherInterface $hasher,
        #[Autowire("%kernel.project_dir%")] private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $clients = [
            ["Alice", "Bernard", 1, ["pending", "in_preparation"]],
            ["Nathan", "Martin", 2, ["accepted"]],
            ["Julie", "Laurent", 3, ["in_preparation", "pending"]],
            ["Antoine", "Moreau", 4, ["out_for_delivery"]],
            ["Manon", "Dubois", 2, ["accepted", "pending"]],
        ];
        $now = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
        $statuses = $this->db->fetchAllKeyValue(
            "SELECT code,id FROM order_statuses",
        );
        $role = $this->db->fetchOne("SELECT id FROM roles WHERE code='user'");
        $menus = $this->db->fetchAllAssociative(
            "SELECT id,title,minimum_people,round(minimum_price/minimum_people,2) AS unit_price FROM menus WHERE publication_status='published' ORDER BY slug",
        );
        if (!$role || !$menus) {
            throw new \RuntimeException(
                "A customer role and published menus are required.",
            );
        }
        $directory = $this->projectDir . "/var/passwords/user";
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new \RuntimeException("Cannot create credential directory.");
        }
        // Credentials stay outside the public document root and Git tracking.
        $files = [
            ".htaccess" => "Require all denied\n",
            "web.config" =>
                '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>',
            "index.php" => "<?php http_response_code(404); exit;\n",
        ];
        foreach ($files as $name => $content) {
            if (
                file_put_contents($directory . "/" . $name, $content) === false
            ) {
                throw new \RuntimeException(
                    "Cannot protect credential directory.",
                );
            }
        }
        $credentials =
            "<?php http_response_code(404); exit; ?>\nCOMPTES CLIENTS DE DEMONSTRATION — lecture locale uniquement\n\n";
        $report = [];
        $this->db->beginTransaction();
        try {
            foreach ($clients as $index => [$first, $last, $past, $active]) {
                $email = strtolower($first) . "@gmail.com";
                $password = strtolower($first . $last) . "12345!";
                $address = 12 + $index * 4 . " rue des Lilas";
                $user = new User(
                    new Role("user", "User"),
                    $email,
                    "",
                    $first,
                    $last,
                    "06000000" . sprintf("%02d", $index + 1),
                    $address,
                    "33200",
                    "Bordeaux",
                );
                $existing = $this->db->fetchAssociative(
                    "SELECT id,password_hash,role_id,first_name,last_name FROM users WHERE email=?",
                    [$email],
                );
                if ($existing) {
                    $user->setPasswordHash($existing["password_hash"]);
                    if (
                        (int) $existing["role_id"] !== (int) $role ||
                        $existing["first_name"] !== $first ||
                        $existing["last_name"] !== $last ||
                        !$this->hasher->isPasswordValid($user, $password)
                    ) {
                        throw new \RuntimeException(
                            "Existing account conflict for " .
                                $email .
                                "; no account overwritten.",
                        );
                    }
                    $userId = $existing["id"];
                } else {
                    $userId = $this->db->fetchOne(
                        "INSERT INTO users(role_id,email,password_hash,first_name,last_name,phone,address_line1,postal_code,city,email_verified_at) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP) RETURNING id",
                        [
                            $role,
                            $email,
                            $this->hasher->hashPassword($user, $password),
                            $first,
                            $last,
                            $user->getPhone(),
                            $address,
                            "33200",
                            "Bordeaux",
                        ],
                    );
                }
                $planned = [];
                for ($n = 0; $n < $past; $n++) {
                    $planned[] = [
                        "status" =>
                            $n === $past - 1 && in_array($index, [1, 3], true)
                                ? "cancelled"
                                : "completed",
                        "date" => $now->modify(
                            "-" . (14 + 21 * $n + $index) . " days",
                        ),
                    ];
                }
                foreach ($active as $n => $status) {
                    $planned[] = [
                        "status" => $status,
                        "date" => $now->modify(
                            "+" .
                                ($status === "pending"
                                    ? 14 + $n
                                    : ($status === "accepted"
                                        ? 7
                                        : 0)) .
                                " days",
                        ),
                    ];
                }
                foreach ($planned as $n => $plan) {
                    $reference = "VG-" . (861000 + $index * 100 + $n);
                    $present = $this->db->fetchAssociative(
                        "SELECT id,user_id FROM orders WHERE reference=?",
                        [$reference],
                    );
                    if ($present) {
                        if ($present["user_id"] !== $userId) {
                            throw new \RuntimeException(
                                "Order reference conflict: " . $reference,
                            );
                        }
                        continue;
                    }
                    $menu = $menus[($index + $n) % count($menus)];
                    $people = (int) $menu["minimum_people"] + ($n % 3) * 2;
                    $total = number_format(
                        (float) $menu["unit_price"] * $people,
                        2,
                        ".",
                        "",
                    );
                    $date = $plan["date"]->setTime(19, 0);
                    $created = $date->modify("-21 days")->setTime(10, 0);
                    $terminal = in_array(
                        $plan["status"],
                        ["completed", "cancelled"],
                        true,
                    );
                    $updated = $terminal
                        ? ($plan["status"] === "completed"
                            ? $date->modify("+1 day")->setTime(10, 0)
                            : $date->modify("-2 days")->setTime(10, 0))
                        : $now->modify("-1 hour");
                    $orderId = $this->db->fetchOne(
                        "INSERT INTO orders(reference,user_id,current_status_id,service_date,delivery_time,delivery_address_line1,delivery_postal_code,delivery_city,menu_subtotal,total_amount,customer_note,cancellation_reason,cancelled_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id",
                        [
                            $reference,
                            $userId,
                            $statuses[$plan["status"]],
                            $date->format("Y-m-d"),
                            "19:00",
                            $address,
                            "33200",
                            "Bordeaux",
                            $total,
                            $total,
                            "Commande de démonstration — réception familiale.",
                            $plan["status"] === "cancelled"
                                ? "Événement reporté par le client."
                                : null,
                            $plan["status"] === "cancelled"
                                ? $updated->format("c")
                                : null,
                            $created->format("c"),
                            $updated->format("c"),
                        ],
                    );
                    $this->db->executeStatement(
                        "INSERT INTO order_items(order_id,menu_id,menu_title,people_count,minimum_people_snapshot,unit_price_per_person,line_total,created_at) VALUES (?,?,?,?,?,?,?,?)",
                        [
                            $orderId,
                            $menu["id"],
                            $menu["title"],
                            $people,
                            $menu["minimum_people"],
                            $menu["unit_price"],
                            $total,
                            $created->format("c"),
                        ],
                    );
                    $steps =
                        $plan["status"] === "cancelled"
                            ? ["pending", "cancelled"]
                            : [
                                "pending",
                                "accepted",
                                "in_preparation",
                                "out_for_delivery",
                                "delivered",
                                "completed",
                            ];
                    $steps = array_slice(
                        $steps,
                        0,
                        array_search($plan["status"], $steps, true) + 1,
                    );
                    foreach ($steps as $stepIndex => $step) {
                        $timestamp =
                            count($steps) === 1
                                ? $updated
                                : $created->setTimestamp(
                                    $created->getTimestamp() +
                                        (int) ((($updated->getTimestamp() -
                                            $created->getTimestamp()) *
                                            $stepIndex) /
                                            (count($steps) - 1)),
                                );
                        $this->db->executeStatement(
                            "INSERT INTO order_status_history(order_id,status_id,public_comment,created_at) VALUES (?,?,?,?)",
                            [
                                $orderId,
                                $statuses[$step],
                                "Étape de démonstration.",
                                $timestamp->format("c"),
                            ],
                        );
                    }
                }
                $storedHash = $this->db->fetchOne(
                    "SELECT password_hash FROM users WHERE id=?",
                    [$userId],
                );
                $user->setPasswordHash($storedHash);
                if (!$this->hasher->isPasswordValid($user, $password)) {
                    throw new \RuntimeException(
                        "Password verification failed.",
                    );
                }
                $credentials .=
                    $first .
                    " " .
                    $last .
                    "\nE-mail : " .
                    $email .
                    "\nMot de passe : " .
                    $password .
                    "\n\n";
                $counts = $this->db->fetchAssociative(
                    "SELECT count(*) FILTER (WHERE s.code IN ('completed','cancelled')) AS past,count(*) FILTER (WHERE s.code NOT IN ('completed','cancelled')) AS active FROM orders o JOIN order_statuses s ON s.id=o.current_status_id WHERE o.user_id=?",
                    [$userId],
                );
                $report[] =
                    $email .
                    " : " .
                    $counts["past"] .
                    " historical, " .
                    $counts["active"] .
                    " current orders; password verified.";
            }
            if (
                file_put_contents(
                    $directory . "/comptes.php",
                    $credentials,
                    LOCK_EX,
                ) === false
            ) {
                throw new \RuntimeException("Cannot write credentials.");
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        foreach ($report as $line) {
            $output->writeln($line);
        }
        $output->writeln(
            "Credentials saved locally in var/passwords/user/comptes.php",
        );
        return Command::SUCCESS;
    }
}
