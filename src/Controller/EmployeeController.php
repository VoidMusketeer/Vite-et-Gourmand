<?php
declare(strict_types=1);
namespace App\Controller;

use App\Employee\EmployeeWorkspace;
use App\Security\AccountValidation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYEE')]
#[Route('/employe')]
final class EmployeeController extends AbstractController
{
    public function __construct(private Connection $db, private EmployeeWorkspace $workspace) {}
    private function today(): string { return (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Paris')))->format('Y-m-d'); }
    private function date(string $date): string
    {
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date) throw $this->createNotFoundException('Date invalide.');
        return $date;
    }
    private function view(string $page,array $data=[]): Response
    {
        return $this->render('employee/'.$page.'.html.twig',$data+['position'=>$this->workspace->position($this->getUser()->getId()),'statuses'=>EmployeeWorkspace::STATUSES,'today'=>$this->today()]);
    }
    private function token(Request $request,string $key): void
    {
        if (!$this->isCsrfTokenValid($key,$request->request->getString('_token'))) throw $this->createAccessDeniedException();
    }

    #[Route('',name:'app_employee',methods:['GET'])]
    public function dashboard(): Response
    {
        $orders=$this->workspace->orders(date:$this->today());
        $active=array_values(array_filter($orders,fn($o)=>!in_array($o['status'],['completed','cancelled'],true)));
        $next=array_values(array_filter($active,fn($o)=>!in_array($o['status'],['delivered','awaiting_equipment_return'],true)))[0] ?? null;
        $checked=$this->db->fetchFirstColumn('SELECT check_code FROM employee_daily_checks WHERE user_id=? AND service_date=?',[$this->getUser()->getId(),$this->today()]);
        return $this->view('dashboard',['orders'=>$active,'next'=>$next,'portions'=>array_sum(array_column($active,'people_count')),'checks'=>EmployeeWorkspace::CHECKS,'checked'=>$checked]);
    }

    #[Route('/commandes',name:'employee_orders',methods:['GET'])]
    public function orders(Request $request): Response
    {
        $search=trim($request->query->getString('q')); $date=$request->query->getString('date'); $status=$request->query->getString('status');
        if ($date!=='') $this->date($date);
        if ($status!=='' && !isset(EmployeeWorkspace::STATUSES[$status])) throw $this->createNotFoundException();
        $page = max(1, min(1000000, $request->query->getInt('page', 1)));
        $orders = $this->workspace->orders($search,$date,$status,$page);
        $hasNext = count($orders) > 50;
        $orders = array_slice($orders, 0, 50);
        return $this->view('orders',compact('search','date','status','orders','page','hasNext'));
    }

    #[Route('/planning',name:'employee_schedule',methods:['GET'])]
    public function schedule(Request $request): Response
    {
        $date=$this->date($request->query->getString('date',$this->today()));
        $day=new \DateTimeImmutable($date); $monday=$day->modify('monday this week'); $days=[];
        foreach (['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'] as $i=>$label) $days[]=['date'=>$monday->modify('+'.$i.' days')->format('Y-m-d'),'label'=>$label];
        return $this->view('schedule',['date'=>$date,'days'=>$days,'previous'=>$day->modify('-7 days')->format('Y-m-d'),'following'=>$day->modify('+7 days')->format('Y-m-d'),'orders'=>array_values(array_filter($this->workspace->orders(date:$date),fn($o)=>$o['status']!=='cancelled'))]);
    }

    #[Route('/commandes/{id}',name:'employee_order',requirements:['id'=>'[0-9a-fA-F-]{36}'],methods:['GET'])]
    public function order(string $id): Response
    {
        $order=$this->workspace->order($id) ?: throw $this->createNotFoundException();
        $dishes=$this->db->fetchAllAssociative("SELECT d.name,d.allergens_reviewed,md.is_optional,string_agg(a.name,', ' ORDER BY a.name) AS allergens FROM menu_dishes md JOIN dishes d ON d.id=md.dish_id LEFT JOIN dish_allergens da ON da.dish_id=d.id LEFT JOIN allergens a ON a.id=da.allergen_id WHERE md.menu_id=? GROUP BY d.id,d.name,d.allergens_reviewed,md.is_optional,md.display_order ORDER BY md.display_order",[$order['menu_id']]);
        $history=$this->db->fetchAllAssociative('SELECT h.created_at,s.code,h.public_comment,u.first_name,u.last_name FROM order_status_history h JOIN order_statuses s ON s.id=h.status_id LEFT JOIN users u ON u.id=h.changed_by WHERE h.order_id=? ORDER BY h.created_at,h.id',[$id]);
        return $this->view('order',compact('order','dishes','history')+['action'=>$this->workspace->next($order['status'],'',(bool)$order['equipment_loaned']), 'contacts'=>$this->db->fetchAllAssociative('SELECT a.*,u.first_name,u.last_name FROM order_contact_actions a LEFT JOIN users u ON u.id=a.employee_id WHERE a.order_id=? ORDER BY a.created_at DESC',[$id]), 'notification'=>$this->db->fetchAssociative('SELECT sent_at,last_error FROM employee_mail_outbox WHERE order_id=?',[$id]), 'legacyAllergens'=>json_decode($this->db->fetchOne("SELECT catalogue->'allergens' FROM menus WHERE id=?",[$order['menu_id']]) ?: '[]',true), 'contactNow'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i')]);
    }

    #[Route('/commandes/{id}/statut',name:'employee_order_status',requirements:['id'=>'[0-9a-fA-F-]{36}'],methods:['POST'])]
    public function status(Request $request,string $id,\App\Employee\EquipmentMailer $mailer): Response
    {
        $this->token($request,'status'.$id);
        if ($request->request->getString('confirmed')!=='1') throw $this->createAccessDeniedException('Confirmez la réalisation de cette étape.');
        $ok=$this->workspace->transition($id,$request->request->getString('expected'),$request->request->getString('target'),$this->getUser()->getId(),$this->workspace->position($this->getUser()->getId())['department'] ?? '');
        $this->addFlash($ok?'success':'error',$ok?'Statut enregistré et historique mis à jour.':'Cette action est indisponible ou la commande a changé. Vérifiez son statut.');
        if ($ok && $request->request->getString('target')==='awaiting_equipment_return' && !$mailer->sendPending($id)) $this->addFlash('error','Le statut est enregistré mais le mail reste en attente. Configurez l’expéditeur et le transport mail, puis relancez l’envoi.');
        return $this->redirectToRoute('employee_order',['id'=>$id],303);
    }

    #[Route('/commandes/{id}/modifier',name:'employee_order_change',requirements:['id'=>'[0-9a-fA-F-]{36}'],methods:['POST'])]
    public function change(Request $request,string $id): Response
    {
        $this->token($request,'change'.$id);
        try { $this->workspace->change($id,$request->request->all(),$this->getUser()->getId()); $this->addFlash('success','Commande mise à jour. Le contact client et le motif sont enregistrés.'); }
        catch (\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        return $this->redirectToRoute('employee_order',['id'=>$id],303);
    }

    #[Route('/commandes/{id}/notification',name:'employee_order_notify',requirements:['id'=>'[0-9a-fA-F-]{36}'],methods:['POST'])]
    public function notify(Request $request,string $id,\App\Employee\EquipmentMailer $mailer): Response
    {
        $this->token($request,'notify'.$id);
        $sent=$mailer->sendPending($id);
        $this->addFlash($sent?'success':'error',$sent?'Notification envoyée.':'Aucun nouvel envoi confirmé. Vérifiez la configuration mail ou l’historique de notification.');
        return $this->redirectToRoute('employee_order',['id'=>$id],303);
    }

    #[Route('/controles',name:'employee_checks',methods:['POST'])]
    public function checks(Request $request): Response
    {
        $this->token($request,'checks');
        if (!$this->isGranted('ROLE_ADMIN') && ($this->workspace->position($this->getUser()->getId())['department'] ?? '')!=='Logistique') throw $this->createAccessDeniedException();
        $selected=$request->request->all('checks');
        if (array_diff($selected,array_keys(EmployeeWorkspace::CHECKS))) throw $this->createAccessDeniedException();
        $this->db->transactional(function () use ($selected) {
            $id=$this->getUser()->getId(); $date=$this->today();
            $this->db->executeQuery('SELECT id FROM users WHERE id=? FOR UPDATE',[$id]);
            $this->db->executeStatement('DELETE FROM employee_daily_checks WHERE user_id=? AND service_date=?',[$id,$date]);
            foreach (array_unique($selected) as $code) $this->db->insert('employee_daily_checks',['user_id'=>$id,'service_date'=>$date,'check_code'=>$code]);
        });
        $this->addFlash('success','Contrôles du jour enregistrés.');
        return $this->redirectToRoute('app_employee',[],303);
    }

    #[Route('/compte',name:'employee_account',methods:['GET','POST'])]
    public function account(Request $request,EntityManagerInterface $em,UserPasswordHasherInterface $hasher): Response
    {
        $user=$this->getUser(); $error=null;
        $values=['first'=>$user->getFirstName(),'last'=>$user->getLastName(),'email'=>$user->getEmail(),'phone'=>$user->getPhone()];
        if ($request->isMethod('POST')) {
            $this->token($request,'employee_account'); $type=$request->request->getString('action');
            if ($type==='profile') {
                foreach (array_keys($values) as $key) $values[$key]=trim($request->request->getString($key));
                if (!$values['first'] || !$values['last'] || mb_strlen($values['first'])>100 || mb_strlen($values['last'])>100 || strlen($values['email'])>254 || !AccountValidation::email($values['email']) || ($values['phone']!=='' && !preg_match('/^[+0-9 ().-]{6,30}$/',$values['phone']))) $error='Vérifiez votre nom, votre e-mail et votre téléphone.';
                elseif ($values['email']!==$user->getEmail() && !$hasher->isPasswordValid($user,$request->request->getString('current_password'))) $error='Le mot de passe actuel est nécessaire pour changer l’e-mail.';
                else { $user->setFirstName($values['first']); $user->setLastName($values['last']); $user->setEmail($values['email']); $user->setPhone($values['phone']); }
            } elseif ($type==='password') {
                $password=$request->request->getString('password');
                if (!$hasher->isPasswordValid($user,$request->request->getString('current_password'))) $error='Mot de passe actuel incorrect.';
                elseif (!AccountValidation::password($password, $request->request->getString('confirmation'), minimumInBytes: true)) $error='Utilisez 12 à 72 octets et une confirmation identique.';
                else $user->setPasswordHash($hasher->hashPassword($user,$password));
            } else throw $this->createAccessDeniedException();
            if (!$error) {
                try { $em->flush(); $this->addFlash('success','Vos modifications ont été enregistrées.'); return $this->redirectToRoute('employee_account',[],303); }
                catch (UniqueConstraintViolationException) { $error='Cette adresse e-mail est déjà utilisée.'; }
            }
        }
        $response=$this->view('account',compact('values','error')); if ($error) $response->setStatusCode(422); return $response;
    }
}
