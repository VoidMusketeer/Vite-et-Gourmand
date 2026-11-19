<?php
namespace App\Catalog;
use Doctrine\DBAL\Connection;
final class OpeningHours
{
    public function __construct(private Connection $db) {}
    public function all(): array { return $this->db->fetchAllAssociativeIndexed('SELECT day_of_week,opens_at,closes_at,is_closed FROM opening_hours ORDER BY day_of_week'); }
}
