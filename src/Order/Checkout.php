<?php

namespace App\Order;

use Doctrine\DBAL\Connection;

final class Checkout
{
    public function __construct(private Connection $db) {}

    public function submit(
        string $user,
        string $token,
        array $basket,
        array $data,
    ): array {
        if (!preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new \DomainException("Rechargez la page de commande.");
        }
        return $this->db->transactional(function () use (
            $user,
            $token,
            $basket,
            $data,
        ) {
            $this->db->executeQuery(
                "SELECT id FROM users WHERE id=? FOR UPDATE",
                [$user],
            );
            $prior = $this->db->fetchOne(
                "SELECT references_json FROM checkout_submissions WHERE user_id=? AND token=?",
                [$user, $token],
            );
            if ($prior) {
                return json_decode($prior, true, flags: JSON_THROW_ON_ERROR);
            }
            if (!$basket) {
                throw new \DomainException("Votre panier est vide.");
            }
            $service = $data["service"] ?? "";
            if (
                !in_array($service, ["delivery", "pickup"], true) ||
                ($data["terms"] ?? "") !== "1"
            ) {
                throw new \DomainException(
                    "Choisissez le service et acceptez les conditions générales.",
                );
            }
            $time = $data["time"] ?? "";
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)) {
                throw new \DomainException("Heure invalide.");
            }
            foreach (
                ["name" => 150, "phone" => 25, "email" => 254, "notes" => 2000]
                as $key => $max
            ) {
                if (
                    !is_string($data[$key] ?? null) ||
                    mb_strlen($data[$key]) > $max ||
                    ($key !== "notes" && trim($data[$key]) === "")
                ) {
                    throw new \DomainException("Vérifiez vos coordonnées.");
                }
            }
            if (
                !filter_var($data["email"], FILTER_VALIDATE_EMAIL) ||
                !preg_match('/^[+0-9 ().-]{6,25}$/D', $data["phone"])
            ) {
                throw new \DomainException("Coordonnées invalides.");
            }
            $address =
                $service === "pickup"
                    ? "Retrait en boutique"
                    : trim($data["address"] ?? "");
            $postal = $service === "pickup" ? "33000" : $data["postal"] ?? "";
            $city =
                $service === "pickup" ? "Bordeaux" : trim($data["city"] ?? "");
            if (
                !$address ||
                mb_strlen($address) > 255 ||
                !preg_match('/^\d{5}$/D', $postal) ||
                !$city ||
                mb_strlen($city) > 120
            ) {
                throw new \DomainException("Adresse de livraison incomplète.");
            }
            $references = [];
            ksort($basket);
            foreach ($basket as $slug => $entry) {
                $menu = $this->db->fetchAssociative(
                    "SELECT *,available_from::date AS first_date,available_until::date AS last_date FROM menus WHERE slug=? FOR UPDATE",
                    [$slug],
                );
                if (
                    !$menu ||
                    $menu["publication_status"] !== "published" ||
                    !$menu["catalogue"]
                ) {
                    throw new \DomainException(
                        "Un menu du panier est indisponible.",
                    );
                }
                $people = is_array($entry) ? $entry["guests"] ?? null : null;
                $people = filter_var($people, FILTER_VALIDATE_INT, [
                    "options" => ["min_range" => 1, "max_range" => 9999],
                ]);
                if (!$people || $people < $menu["minimum_people"]) {
                    throw new \DomainException(
                        "Nombre de personnes incorrect pour " .
                            $menu["title"] .
                            ".",
                    );
                }
                if ($menu["available_stock"] < 1) {
                    throw new \DomainException(
                        "Stock insuffisant pour " . $menu["title"] . ".",
                    );
                }
                $date = $entry["date"] ?? "";
                $parsed = \DateTimeImmutable::createFromFormat("!Y-m-d", $date);
                $earliest = (new \DateTimeImmutable(
                    "now",
                    new \DateTimeZone("Europe/Paris"),
                ))
                    ->modify("+" . $menu["order_lead_time_hours"] . " hours")
                    ->format("Y-m-d");
                $meta = json_decode(
                    $menu["catalogue"],
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
                $day = substr($date, 5);
                if (
                    !$parsed ||
                    $parsed->format("Y-m-d") !== $date ||
                    $date < $earliest ||
                    ($menu["first_date"] && $date < $menu["first_date"]) ||
                    ($menu["last_date"] && $date > $menu["last_date"]) ||
                    (!empty($meta["start"]) &&
                        ($day < $meta["start"] || $day > $meta["end"]))
                ) {
                    throw new \DomainException(
                        "Date indisponible pour " . $menu["title"] . ".",
                    );
                }
                $unit = (int) round(
                    ((float) $menu["minimum_price"] * 100) /
                        (int) $menu["minimum_people"],
                );
                $options = $entry["options"] ?? [];
                if (!is_array($options)) {
                    throw new \DomainException("Options invalides.");
                }
                $catalogOptions = array_column(
                    $meta["options"] ?? [],
                    null,
                    "name",
                );
                foreach (array_unique($options) as $option) {
                    if (
                        !is_string($option) ||
                        !isset($catalogOptions[$option])
                    ) {
                        throw new \DomainException(
                            "Une option du panier est indisponible.",
                        );
                    }
                    $unit += (int) round(
                        $catalogOptions[$option]["price"] * 100,
                    );
                }
                $subtotal = $unit * $people;
                $fee = $service === "delivery" && !$references ? 1500 : 0;
                $reference =
                    "VG-" .
                    $this->db->fetchOne(
                        "SELECT nextval('checkout_reference_seq')",
                    );
                $note =
                    $data["notes"] .
                    "\nContact : " .
                    $data["name"] .
                    " · " .
                    $data["phone"] .
                    " · " .
                    $data["email"];
                if ($options) {
                    $note .= "\nOptions : " . implode(", ", $options);
                }
                $order = $this->db->fetchOne(
                    "INSERT INTO orders(reference,user_id,current_status_id,service_date,delivery_time,delivery_address_line1,delivery_postal_code,delivery_city,menu_subtotal,delivery_fee,total_amount,customer_note,stock_reserved) VALUES (?,?,(SELECT id FROM order_statuses WHERE code='pending'),?,?,?,?,?,?,?,?,?,?) RETURNING id",
                    [
                        $reference,
                        $user,
                        $date,
                        $time,
                        $address,
                        $postal,
                        $city,
                        $subtotal / 100,
                        $fee / 100,
                        ($subtotal + $fee) / 100,
                        $note,
                        1,
                    ],
                );
                $this->db->executeStatement(
                    "INSERT INTO order_items(order_id,menu_id,menu_title,people_count,minimum_people_snapshot,unit_price_per_person,line_total) VALUES (?,?,?,?,?,?,?)",
                    [
                        $order,
                        $menu["id"],
                        $menu["title"],
                        $people,
                        $menu["minimum_people"],
                        $unit / 100,
                        $subtotal / 100,
                    ],
                );
                $this->db->executeStatement(
                    "UPDATE menus SET available_stock=available_stock-1 WHERE id=?",
                    [$menu["id"]],
                );
                $this->db->executeStatement(
                    "INSERT INTO order_status_history(order_id,status_id,changed_by) VALUES (?,(SELECT id FROM order_statuses WHERE code='pending'),?)",
                    [$order, $user],
                );
                $references[] = $reference;
            }
            $this->db->insert("checkout_submissions", [
                "user_id" => $user,
                "token" => $token,
                "references_json" => json_encode(
                    $references,
                    JSON_THROW_ON_ERROR,
                ),
            ]);
            return $references;
        });
    }
}
