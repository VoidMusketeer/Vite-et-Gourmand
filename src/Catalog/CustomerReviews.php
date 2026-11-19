<?php
namespace App\Catalog;

use Doctrine\DBAL\Connection;

final class CustomerReviews
{
    public function __construct(private Connection $db) {}

    public function list(
        bool $publishedOnly = false,
        string $rating = "",
        string $from = "",
        string $until = "",
        bool $homepageOnly = false,
    ): array {
        $sql = "SELECT r.id,r.rating,r.comment,r.moderation_status,r.homepage_selected,r.created_at,u.first_name,o.reference
                FROM reviews r JOIN orders o ON o.id=r.order_id JOIN users u ON u.id=o.user_id WHERE TRUE";
        $args = [];
        if ($publishedOnly) {
            $sql .= " AND r.moderation_status='approved'";
        }
        if ($homepageOnly) {
            $sql .=
                " AND r.homepage_selected AND r.moderation_status='approved'";
        }
        if ($rating !== "") {
            $sql .= " AND r.rating=?";
            $args[] = $rating;
        }
        foreach ([[">=", $from], ["<=", $until]] as [$operator, $date]) {
            if ($date !== "") {
                $sql .= " AND (r.created_at AT TIME ZONE 'Europe/Paris')::date $operator ?::date";
                $args[] = $date;
            }
        }
        return $this->db->fetchAllAssociative(
            $sql . " ORDER BY r.created_at DESC, r.id",
            $args,
        );
    }
}
