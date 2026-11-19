<?php
declare(strict_types=1);
namespace App\Employee;

use Doctrine\DBAL\Connection;

final class EmployeeWorkspace
{
    public const STATUSES = ['pending'=>'En attente', 'accepted'=>'Acceptée', 'in_preparation'=>'En préparation', 'out_for_delivery'=>'En livraison', 'delivered'=>'Livrée', 'awaiting_equipment_return'=>'Retour matériel attendu', 'completed'=>'Terminée', 'cancelled'=>'Annulée'];
    public const CHECKS = ['vehicle'=>'Températures du véhicule vérifiées', 'bags'=>'Glacières et sacs isothermes prêts', 'route'=>'Téléphone chargé et itinéraires vérifiés'];

    public function __construct(private Connection $db) {}

    public function position(string $id): array
    {
        return $this->db->fetchAssociative('SELECT p.* FROM users u JOIN staff_positions p ON p.name=u.staff_function WHERE u.id=?', [$id]) ?: [];
    }

    public function orders(string $search='', string $date='', string $status=''): array
    {
        $sql = 'SELECT o.*, s.code AS status, u.first_name, u.last_name, u.phone, u.email, i.menu_title, i.people_count, i.menu_id FROM orders o JOIN order_statuses s ON s.id=o.current_status_id JOIN users u ON u.id=o.user_id LEFT JOIN order_items i ON i.order_id=o.id WHERE 1=1';
        $params=[];
        if ($search!=='') { $sql.=" AND (o.reference ILIKE ? OR concat_ws(' ',u.first_name,u.last_name) ILIKE ? OR u.email ILIKE ? OR i.menu_title ILIKE ?)"; $params=array_fill(0,4,'%'.$search.'%'); }
        if ($date!=='') { $sql.=' AND o.service_date=?'; $params[]=$date; }
        if ($status!=='') { $sql.=' AND s.code=?'; $params[]=$status; }
        return $this->db->fetchAllAssociative($sql.' ORDER BY o.service_date,o.delivery_time,o.reference', $params);
    }

    public function order(string $id): array|false
    {
        return $this->db->fetchAssociative('SELECT o.*,s.code AS status,u.first_name,u.last_name,u.phone,u.email,i.menu_title,i.people_count,i.menu_id FROM orders o JOIN order_statuses s ON s.id=o.current_status_id JOIN users u ON u.id=o.user_id LEFT JOIN order_items i ON i.order_id=o.id WHERE o.id=?',[$id]);
    }

    public function next(string $status, string $department='', bool $equipment=false): ?array
    {
        return match ($status) {
            'pending'=>['accepted','Accepter la commande'],
            'accepted'=>['in_preparation','Démarrer la préparation'],
            'in_preparation'=>['out_for_delivery','Confirmer le départ en livraison'],
            'out_for_delivery'=>['delivered','Confirmer la livraison'],
            'delivered'=>$equipment ? ['awaiting_equipment_return','Demander le retour du matériel et notifier le client'] : ['completed','Terminer la commande sans matériel'],
            'awaiting_equipment_return'=>['completed','Confirmer la restitution et terminer'],
            default=>null,
        };
    }

    public function transition(string $id, string $expected, string $target, string $actor, string $department=''): bool
    {
        return $this->db->transactional(function () use ($id,$expected,$target,$actor): bool {
            $order=$this->db->fetchAssociative('SELECT o.*,s.code,u.email FROM orders o JOIN order_statuses s ON s.id=o.current_status_id JOIN users u ON u.id=o.user_id WHERE o.id=? FOR UPDATE OF o',[$id]);
            if (!$order || $order['code']!==$expected || ($this->next($expected,'',(bool)$order['equipment_loaned'])[0] ?? null)!==$target) return false;
            $status=$this->db->fetchOne('SELECT id FROM order_statuses WHERE code=?',[$target]);
            $this->db->executeStatement('UPDATE orders SET current_status_id=? WHERE id=?',[$status,$id]);
            if ($target==='awaiting_equipment_return') {
                $due=(new EquipmentReturnDeadline())->after(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Paris')));
                $this->db->update('orders',['equipment_return_due'=>$due->format('Y-m-d')],['id'=>$id]);
                $body="Bonjour,\n\nVotre commande ".$order['reference']." est en attente du retour du matériel prêté : ".$order['equipment_description'].".\nMerci de nous contacter en répondant à cet e-mail pour organiser sa restitution. Vous disposez de 10 jours ouvrés, soit jusqu’au ".$due->format('d/m/Y')." inclus. À défaut de restitution dans ce délai, des frais de 600 euros seront dus, comme indiqué dans nos conditions générales de vente.\n\nVite & Gourmand";
                $this->db->insert('employee_mail_outbox',['order_id'=>$id,'kind'=>'equipment_return','recipient'=>$order['email'],'subject'=>'Retour de matériel — '.$order['reference'],'body'=>$body]);
            }
            if ($expected==='awaiting_equipment_return' && $target==='completed') $this->db->executeStatement('UPDATE orders SET equipment_returned_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);
            $this->db->insert('order_status_history',['order_id'=>$id,'status_id'=>$status,'changed_by'=>$actor]);
            return true;
        });
    }

    public function change(string $id,array $data,string $actor): void
    {
        foreach ($data as $value) if (!is_string($value)) throw new \DomainException('Formulaire invalide.');
        $method=$data['contact_method'] ?? ''; $reason=trim($data['reason'] ?? '');
        $time=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$data['contacted_at'] ?? '',new \DateTimeZone('Europe/Paris'));
        if (!in_array($method,['phone','email'],true) || !$reason || mb_strlen($reason)>5000 || ($data['contact_confirmed'] ?? '')!=='1' || !$time || $time->format('Y-m-d\TH:i')!==($data['contacted_at'] ?? '') || $time>new \DateTimeImmutable('now')) throw new \DomainException('Confirmez le contact déjà effectué, sa date, son mode et le motif (5 000 caractères maximum).');
        $action=$data['action'] ?? '';
        if (!in_array($action,['edit','cancel'],true)) throw new \DomainException('Action inconnue.');
        $this->db->transactional(function () use ($id,$data,$actor,$method,$reason,$time,$action) {
            $order=$this->db->fetchAssociative('SELECT o.*,s.code FROM orders o JOIN order_statuses s ON s.id=o.current_status_id WHERE o.id=? FOR UPDATE OF o',[$id]);
            if (!$order) throw new \DomainException('Commande introuvable.');
            if (($data['version'] ?? '')!==$order['updated_at']) throw new \DomainException('La commande a changé. Rechargez-la avant de recommencer.');
            if ($time->modify('+59 seconds')<new \DateTimeImmutable($order['created_at'])) throw new \DomainException('Le contact doit être postérieur à la création de la commande.');
            if (!in_array($order['code'],['pending','accepted','in_preparation'],true)) throw new \DomainException('La commande a déjà quitté la cuisine ou est clôturée.');
            $changes=[];
            if ($action==='cancel') {
                $status=$this->db->fetchOne("SELECT id FROM order_statuses WHERE code='cancelled'");
                $changes=['current_status_id'=>$status,'cancellation_reason'=>$reason,'cancellation_contact_method'=>$method,'cancelled_at'=>(new \DateTimeImmutable())->format(DATE_ATOM)];
                $this->db->insert('order_status_history',['order_id'=>$id,'status_id'=>$status,'changed_by'=>$actor,'internal_comment'=>$reason.' — contact : '.$method]);
            } else {
                $date=$data['service_date'] ?? ''; $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$parsed || $parsed->format('Y-m-d')!==$date || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$data['delivery_time'] ?? '')) throw new \DomainException('Date ou heure de livraison invalide.');
                $changes=['service_date'=>$date,'delivery_time'=>$data['delivery_time']];
                foreach (['delivery_address_line1'=>255,'delivery_postal_code'=>20,'delivery_city'=>120] as $field=>$max) {
                    $value=trim($data[$field] ?? ''); if ($value==='' || mb_strlen($value)>$max) throw new \DomainException('Adresse de livraison incomplète ou trop longue.'); $changes[$field]=$value;
                }
                $changes['customer_note']=trim($data['customer_note'] ?? '');
                $changes['equipment_description']=trim($data['equipment_description'] ?? '');
                $loaned=($data['equipment_loaned'] ?? '')==='1';
                if (mb_strlen($changes['customer_note'])>5000 || mb_strlen($changes['equipment_description'])>2000 || ($loaned && !$changes['equipment_description'])) throw new \DomainException('Décrivez le matériel prêté et limitez la longueur des instructions.');
                $changes['equipment_loaned']=$loaned?'true':'false';
                if (!$loaned) $changes['equipment_description']=null;
            }
            $this->db->update('orders',$changes,['id'=>$id]);
            $before=array_intersect_key($order,$changes);
            $this->db->insert('order_contact_actions',['order_id'=>$id,'employee_id'=>$actor,'contact_method'=>$method,'contacted_at'=>$time->format(DATE_ATOM),'reason'=>$reason,'action'=>$action,'changes'=>json_encode(['before'=>$before,'after'=>$changes],JSON_THROW_ON_ERROR)]);
        });
    }
}
