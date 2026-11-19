<?php
namespace App\Statistics;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class AdminStatistics
{
    public function __construct(private Connection $db,
        #[Autowire('%kernel.project_dir%')] private string $project,
        #[Autowire('%env(MONGODB_URI)%')] private string $uri,
        #[Autowire('%env(MONGODB_DATABASE)%')] private string $database,
        #[Autowire('%env(NODE_BINARY)%')] private string $node) {}

    public function report(string $menu,string $from,string $until): array
    {
        // Each request writes its own immutable snapshot, avoiding mixed results during concurrent refreshes.
        $orders=$this->db->fetchAllAssociative("SELECT o.id AS order_id,i.menu_id,o.service_date,s.code AS status,
            round(GREATEST(0,i.line_total-o.discount_amount)*100)::bigint AS revenue_cents
            FROM orders o JOIN order_items i ON i.order_id=o.id JOIN order_statuses s ON s.id=o.current_status_id");
        $process=new Process([$this->node,$this->project.'/bin/admin-statistics.mjs'],$this->project,
            ['MONGODB_URI'=>$this->uri,'MONGODB_DATABASE'=>$this->database]);
        $process->setInput(json_encode(compact('orders','menu','from','until'),JSON_THROW_ON_ERROR));
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) throw new \RuntimeException('Statistiques temporairement indisponibles. Vérifiez le service MongoDB.');
        return json_decode($process->getOutput(),true,flags:JSON_THROW_ON_ERROR);
    }
}
