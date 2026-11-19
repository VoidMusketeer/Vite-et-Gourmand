<?php
namespace App\Controller;

use App\Catalog\{CustomerReviews,MenuPublisher};
use App\Entity\{User,Role};
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/administration')]
final class AdminController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('', name:'app_admin', methods:['GET'])]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', ['pending'=>$this->db->fetchAllAssociative("SELECT o.reference,o.service_date,u.first_name,u.last_name FROM orders o JOIN users u ON u.id=o.user_id JOIN order_statuses s ON s.id=o.current_status_id WHERE s.code='pending' ORDER BY o.service_date"), 'counts'=>$this->db->fetchAssociative("SELECT
            (SELECT count(*) FROM menus WHERE publication_status='published') AS menus,
            (SELECT count(*) FROM reviews WHERE moderation_status='pending') AS reviews,
            (SELECT count(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='employee' AND u.is_active) AS employees")]);
    }

    #[Route('/commandes', name:'admin_orders', methods:['GET'])]
    public function orders(Request $request): Response
    {
        $page = max(1, min(1000000, $request->query->getInt('page', 1)));
        $orders = $this->db->fetchAllAssociative('SELECT o.reference,o.service_date,u.first_name,u.last_name,s.label FROM orders o JOIN users u ON u.id=o.user_id JOIN order_statuses s ON s.id=o.current_status_id ORDER BY o.service_date DESC,o.id LIMIT 51 OFFSET '.(($page - 1) * 50));
        $hasNext = count($orders) > 50;
        $orders = array_slice($orders, 0, 50);
        return $this->render('admin/orders.html.twig',compact('orders','page','hasNext'));
    }

    #[Route('/menus', name:'admin_menus', methods:['GET'])]
    public function menus(): Response
    {
        return $this->render('admin/menus.html.twig',['menus'=>$this->db->fetchAllAssociative('SELECT m.id,title,slug,available_stock,publication_status,(publication_status IN (\'archived\',\'unpublished\') OR available_until<CURRENT_TIMESTAMP) AS is_old,homepage_selected,(catalogue IS NOT NULL) AS has_catalogue,minimum_people,round(minimum_price/minimum_people,2) AS price,(SELECT url FROM menu_images WHERE menu_id=m.id ORDER BY is_cover DESC,display_order LIMIT 1) AS image FROM menus m ORDER BY title')]);
    }

    #[Route('/menus/{id}/gestion', name:'admin_menu_manage', requirements:['id'=>'[0-9a-fA-F-]{36}'], methods:['POST'])]
    public function manageMenu(Request $request,string $id): Response
    {
        $this->token($request,'manage_menu'.$id);
        $action=$request->request->getString('action');
        if ($action==='stock') {
            $stock=filter_var($request->request->get('stock'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
            $original=filter_var($request->request->get('original_stock'),FILTER_VALIDATE_INT);
            if ($stock===false || $original===false) throw $this->createNotFoundException('Stock invalide.');
            $changed=$this->db->executeStatement('UPDATE menus SET available_stock=?,updated_by=? WHERE id=? AND available_stock=?',[$stock,$this->getUser()->getId(),$id,$original]);
            $this->addFlash($changed?'success':'error',$changed?'Stock enregistré.':'Le stock a changé. Vérifiez la quantité actualisée puis réessayez.');
        } else {
            $state=match($action) {'disable'=>'unpublished','archive'=>'archived','restore'=>'draft',default=>throw $this->createNotFoundException()};
            $this->db->executeStatement('UPDATE menus SET publication_status=?,homepage_selected=false,updated_by=? WHERE id=?',[$state,$this->getUser()->getId(),$id]);
            if ($action==='restore') {
                $this->addFlash('success','Menu restauré en brouillon. Vérifiez ses dates et son stock avant publication.');
                return $this->redirectToRoute('admin_menu_edit',['id'=>$id],303);
            }
            $this->addFlash('success','Menu retiré du catalogue et conservé dans les archives.');
        }
        return $this->redirectToRoute('admin_menus',[],303);
    }

    #[Route('/menus/nouveau', name:'admin_menu_new', methods:['GET','POST'])]
    #[Route('/menus/{id}/modifier', name:'admin_menu_edit', requirements:['id'=>'[0-9a-fA-F-]{36}'], methods:['GET','POST'])]
    public function menu(Request $request, MenuPublisher $publisher, ?string $id=null): Response
    {
        $values = [];
        if ($id) {
            $values=$this->db->fetchAssociative("SELECT title,description,conditions,available_stock AS stock,available_stock AS original_stock,available_from::date AS start_date,available_until::date AS end_date,minimum_people AS min,round(minimum_price/minimum_people,2) AS price,catalogue->>'theme' AS theme,catalogue->>'tag' AS tag FROM menus WHERE id=?",[$id]);
            if (!$values) throw $this->createNotFoundException();
            $values += $this->db->fetchAllKeyValue('SELECT t.code,d.name FROM menu_dishes md JOIN dishes d ON d.id=md.dish_id JOIN dish_types t ON t.id=d.dish_type_id WHERE md.menu_id=? AND NOT md.is_optional ORDER BY md.display_order',[$id]);
        }
        $error=null;
        if ($request->isMethod('POST')) {
            $this->token($request,'menu'.($id ?? ''));
            $values=$request->request->all();
            try {
                $publisher->save($values,$request->files->get('photo'),$this->getUser()->getId(),$id);
                $this->addFlash('success','Menu enregistré.');
                return $this->redirectToRoute('admin_menus',[],303);
            } catch (\DomainException $e) { $error=$e->getMessage(); }
        }
        return $this->render('admin/menu.html.twig',compact('values','id','error'),new Response(status:$error ? 422 : 200));
    }

    #[Route('/employes', name:'admin_employees', methods:['GET','POST'])]
    public function employees(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, \App\Employee\EquipmentMailer $mailer): Response
    {
        $error=null;
        $positions=$this->db->fetchAllAssociative('SELECT name,department,responsibilities,service_context FROM staff_positions ORDER BY department,name');
        if ($request->isMethod('POST')) {
            $this->token($request,'employee');
            $p=$request->request;
            $first=trim($p->getString('first'));
            $last=trim($p->getString('last'));
            $email=trim($p->getString('email'));
            $password=$p->getString('password');
            $job=$p->getString('job');
            if (!$first || !$last || mb_strlen($first)>100 || mb_strlen($last)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL)
                || strlen($password)<12 || strlen($password)>72 || !in_array($job,array_column($positions,'name'),true)) {
                $error='Vérifiez les coordonnées, la fonction et le mot de passe (12 à 72 octets).';
            } else {
                $role=$em->getRepository(Role::class)->findOneBy(['code'=>'employee']);
                $user=new User($role,$email,'',$first,$last,'','','','');
                $user->setPasswordHash($hasher->hashPassword($user,$password));
                $user->setStaffFunction($job);
                try {
                    $this->db->transactional(function () use ($em,$user,$email,$first) {
                        $em->persist($user);
                        $em->flush();
                        $this->db->insert('employee_mail_outbox', [
                            'employee_id'=>$user->getId(),'kind'=>'account_created','recipient'=>$email,
                            'subject'=>'Votre compte employé Vite & Gourmand',
                            'body'=>"Bonjour ".$first.",\n\nUn compte employé a été créé pour vous chez Vite & Gourmand. Votre identifiant est ".$email.".\nContactez votre administrateur pour obtenir votre mot de passe, puis connectez-vous sur la page Connexion du site.\n\nVite & Gourmand",
                        ]);
                    });
                    $sent=$mailer->sendPending(employeeId:$user->getId());
                    $this->addFlash($sent?'success':'error',$sent?'Compte employé créé et notification envoyée.':'Compte créé. La notification est en attente d’envoi. Vous pouvez la relancer depuis la liste.');
                    return $this->redirectToRoute('admin_employees',[],303);
                } catch (UniqueConstraintViolationException) { $error='Cette adresse est déjà utilisée.'; }
            }
        }
        $employees=$this->db->fetchAllAssociative("SELECT users.id,users.is_active,first_name,last_name,email,staff_function,staff_schedule,p.department,p.responsibilities,p.service_context,n.id AS notification_id,n.sent_at FROM users JOIN roles ON role_id=roles.id LEFT JOIN staff_positions p ON p.name=users.staff_function LEFT JOIN employee_mail_outbox n ON n.employee_id=users.id AND n.kind='account_created' WHERE roles.code='employee' ORDER BY last_name,first_name");
        return $this->render('admin/employees.html.twig',compact('employees','positions','error'),new Response(status:$error ? 422 : 200));
    }

    #[Route('/employes/{id}/modifier', name:'admin_employee_edit', requirements:['id'=>'[0-9a-fA-F-]{36}'], methods:['GET','POST'])]
    public function editEmployee(Request $request,string $id): Response
    {
        $person=$this->db->fetchAssociative("SELECT id,first_name,last_name,email,staff_function,staff_schedule FROM users WHERE id=? AND role_id=(SELECT id FROM roles WHERE code='employee')",[$id]);
        if (!$person) throw $this->createNotFoundException();
        $positions=$this->db->fetchAllAssociative('SELECT name FROM staff_positions ORDER BY department,name');
        $error=null;
        if ($request->isMethod('POST')) {
            $this->token($request,'employee_edit'.$id);
            foreach (['first_name','last_name','email','staff_function','staff_schedule'] as $field) $person[$field]=trim($request->request->getString($field));
            if (!$person['first_name'] || !$person['last_name'] || mb_strlen($person['first_name'])>100 || mb_strlen($person['last_name'])>100
                || mb_strlen($person['email'])>180 || !filter_var($person['email'],FILTER_VALIDATE_EMAIL)
                || mb_strlen($person['staff_schedule'])>120 || !in_array($person['staff_function'],array_column($positions,'name'),true)) {
                $error='Vérifiez le nom, les coordonnées, la fonction et les horaires.';
            } else {
                try {
                    $this->db->transactional(function () use ($person,$id) {
                        $this->db->executeStatement("UPDATE users SET first_name=?,last_name=?,email=?,staff_function=?,staff_schedule=? WHERE id=? AND role_id=(SELECT id FROM roles WHERE code='employee')",
                            [$person['first_name'],$person['last_name'],$person['email'],$person['staff_function'],$person['staff_schedule'] ?: null,$id]);
                        $this->db->executeStatement('UPDATE employee_mail_outbox SET recipient=? WHERE employee_id=? AND sent_at IS NULL',[$person['email'],$id]);
                    });
                    $this->addFlash('success','Informations de l’employé mises à jour.');
                    return $this->redirectToRoute('admin_employees',[],303);
                } catch (UniqueConstraintViolationException) { $error='Cette adresse est déjà utilisée.'; }
            }
        }
        return $this->render($request->isXmlHttpRequest() ? 'admin/_employee_edit_form.html.twig' : 'admin/employee_edit.html.twig',compact('person','positions','error') + ['popup'=>$request->isXmlHttpRequest()],new Response(status:$error ? 422 : 200));
    }

    #[Route('/employes/{id}/activation', name:'admin_employee_active', requirements:['id'=>'[0-9a-fA-F-]{36}'], methods:['POST'])]
    public function activation(Request $request,string $id): Response
    {
        $this->token($request,'employee_active'.$id);
        $active=$request->request->getString('active');
        if (!in_array($active,['0','1'],true)) throw $this->createNotFoundException();
        $count=$this->db->executeStatement("UPDATE users SET is_active=?::boolean WHERE id=? AND role_id=(SELECT id FROM roles WHERE code='employee')",[$active,$id]);
        if (!$count) throw $this->createNotFoundException();
        $this->addFlash('success',$active==='1'?'Compte employé réactivé.':'Compte employé désactivé.');
        return $this->redirectToRoute('admin_employees',[],303);
    }

    #[Route('/employes/{id}/notification', name:'admin_employee_notify', requirements:['id'=>'[0-9a-fA-F-]{36}'], methods:['POST'])]
    public function notification(Request $request,string $id,\App\Employee\EquipmentMailer $mailer): Response
    {
        $this->token($request,'employee_notify'.$id);
        $sent=$mailer->sendPending(employeeId:$id);
        $this->addFlash($sent?'success':'error',$sent?'Notification envoyée.':'Aucun envoi confirmé. Vérifiez la configuration de messagerie.');
        return $this->redirectToRoute('admin_employees',[],303);
    }

    #[Route('/avis', name:'admin_reviews', methods:['GET'])]
    public function reviews(Request $request, CustomerReviews $reviews): Response
    {
        $rating=$request->query->getString('rating');
        $from=$request->query->getString('from');
        $until=$request->query->getString('until');
        $error=null;
        foreach ([$from,$until] as $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if ($date !== '' && (!$parsed || $parsed->format('Y-m-d') !== $date)) $error='Date invalide.';
        }
        if (($rating!=='' && !in_array($rating,['1','2','3','4','5'],true)) || ($from && $until && $from>$until)) $error='Vérifiez les filtres.';
        return $this->render('admin/reviews.html.twig', ['featured'=>$reviews->list(true),'reviews'=>$error ? [] : $reviews->list(false,$rating,$from,$until)] + compact('rating','from','until','error'));
    }

    #[Route('/avis/{id}', name:'admin_review_moderate', requirements:['id'=>'[0-9a-fA-F-]{36}'], methods:['POST'])]
    public function moderate(Request $request,string $id): Response
    {
        $this->token($request,'review'.$id);
        $state=$request->request->getString('state');
        if (!in_array($state,['approved','rejected'],true)) throw $this->createNotFoundException();
        if (!$this->db->executeStatement("UPDATE reviews SET moderation_status=?,homepage_selected=CASE WHEN ?='rejected' THEN false ELSE homepage_selected END,moderated_by=?,moderated_at=CURRENT_TIMESTAMP WHERE id=?",[$state,$state,$this->getUser()->getId(),$id])) throw $this->createNotFoundException();
        return $this->redirectToRoute('admin_reviews',[],303);
    }

    #[Route('/statistiques', name:'admin_statistics', methods:['GET'])]
    public function statistics(Request $request,\App\Statistics\AdminStatistics $statistics): Response
    {
        $menu=$request->query->getString('menu');
        $from=$request->query->getString('from');
        $until=$request->query->getString('until');
        $menus=$this->db->fetchAllAssociative('SELECT id,title FROM menus ORDER BY title');
        $error=null;
        if ($menu!=='' && !in_array($menu,array_column($menus,'id'),true)) $error='Menu inconnu.';
        foreach ([$from,$until] as $date) {
            $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if ($date!=='' && (!$parsed || $parsed->format('Y-m-d')!==$date)) $error='Date invalide.';
        }
        if ($from && $until && $from>$until) $error='La fin doit suivre le début de la période.';
        $rows=[];
        if (!$error) {
            try {
                $results=array_column($statistics->report($menu,$from,$until),null,'_id');
                foreach ($menus as $item) {
                    if ($menu!=='' && $item['id']!==$menu) continue;
                    $rows[]=['title'=>$item['title']]+($results[$item['id']] ?? ['orders'=>0,'completed'=>0,'revenueCents'=>0]);
                }
            } catch (\RuntimeException $e) { $error=$e->getMessage(); }
        }
        $totalOrders=array_sum(array_column($rows,'orders'));
        $totalRevenue=array_sum(array_column($rows,'revenueCents'));
        $maxOrders=max([0,...array_column($rows,'orders')]);
        return $this->render('admin/statistics.html.twig',compact('menu','from','until','menus','error','rows','totalOrders','totalRevenue','maxOrders'));
    }

    #[Route('/accueil/{kind}', name:'admin_home_selection', requirements:['kind'=>'menus|reviews'], methods:['POST'])]
    public function homeSelection(Request $request,string $kind): Response
    {
        $this->token($request,'home_selection_'.$kind);
        $selected=$request->request->all('selected');
        $table=$kind==='menus'?'menus':'reviews';
        $condition=$kind==='menus'?"publication_status='published' AND catalogue IS NOT NULL":"moderation_status='approved'";
        $this->db->transactional(function () use ($selected,$table,$condition) {
            $eligible=$this->db->fetchFirstColumn("SELECT id FROM $table WHERE $condition ORDER BY id FOR UPDATE");
            foreach ($selected as $id) {
                if (!is_string($id) || !in_array($id,$eligible,true)) {
                    throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('La sélection a changé. Rechargez la page et choisissez uniquement des éléments publiés.');
                }
            }
            foreach ($eligible as $id) {
                $this->db->executeStatement("UPDATE $table SET homepage_selected=?::boolean WHERE id=?",[in_array($id,$selected,true)?'true':'false',$id]);
            }
        });
        $this->addFlash('success','Sélection de la page d’accueil enregistrée.');
        return $this->redirectToRoute($kind==='menus'?'admin_menus':'admin_reviews',[],303);
    }

    private function token(Request $request,string $key): void
    {
        if (!$this->isCsrfTokenValid($key,$request->request->getString('_token'))) throw $this->createAccessDeniedException();
    }
}
