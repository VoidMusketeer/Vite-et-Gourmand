<?php
// Local integration check. All database writes are rolled back.
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
class EmployeeSmokeKernel extends App\Kernel
{
    public function getProjectDir(): string { return dirname(__DIR__); }
    public function getCacheDir(): string { return $this->getProjectDir().'/var/cache/employee_smoke'; }
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->setAlias('smoke.token_storage','security.token_storage')->setPublic(true);
        $container->setAlias('smoke.csrf','security.csrf.token_manager')->setPublic(true);
    }
}
$kernel=new EmployeeSmokeKernel('dev',true); $kernel->boot();
$db=$kernel->getContainer()->get('doctrine')->getConnection();
$workspace=new App\Employee\EmployeeWorkspace($db);
function check(bool $condition,string $label): void { if (!$condition) throw new RuntimeException($label); echo "PASS: $label\n"; }
$db->beginTransaction();
try {
    $employee=$db->fetchOne("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id JOIN staff_positions p ON p.name=u.staff_function WHERE r.code='employee' AND p.department='Logistique' LIMIT 1");
    $customer=$db->fetchOne("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='user' LIMIT 1");
    $menu=$db->fetchOne('SELECT id FROM menus LIMIT 1');
    check((bool)($employee && $customer && $menu),'Existing database accounts and menu available');
    $reference='VG-'.random_int(10000000,99999999);
    $id=$db->fetchOne("INSERT INTO orders(reference,user_id,current_status_id,service_date,delivery_time,delivery_address_line1,delivery_postal_code,delivery_city,menu_subtotal,total_amount) VALUES (?,?,(SELECT id FROM order_statuses WHERE code='accepted'),'2099-01-02','12:00','Test address','33000','Bordeaux',100,100) RETURNING id",[$reference,$customer]);
    $db->executeStatement("INSERT INTO order_items(order_id,menu_id,menu_title,people_count,minimum_people_snapshot,unit_price_per_person,line_total) VALUES (?,?,'Integration menu',10,1,10,100)",[$id,$menu]);
    check(count($workspace->orders($reference,'2099-01-02','accepted'))===1,'Search, date and status filters return database order');
    check($workspace->order($id)['people_count']===10,'Order details include ordered portions');
    $container=$kernel->getContainer();
    $user=$container->get('doctrine')->getRepository(App\Entity\User::class)->find($employee);
    $container->get('smoke.token_storage')->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user,'main',$user->getRoles()));
    $request=Symfony\Component\HttpFoundation\Request::create('/employe/commandes/'.$id);
    $request->attributes->set('_route','employee_order');
    $request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
    $container->get('request_stack')->push($request);
    $response=$container->get(App\Controller\EmployeeController::class)->order($id);
    check($response->getStatusCode()===200 && str_contains($response->getContent(),$reference),'Populated order page and allergen query render successfully');
    check($workspace->next('pending')[0]==='accepted','Employees can accept incoming orders');
    check($workspace->transition($id,'accepted','in_preparation',$employee,'Cuisine'),'Employee preparation transition is persisted');
    check(!$workspace->transition($id,'accepted','in_preparation',$employee,'Cuisine'),'Stale transition is rejected');
    check($workspace->transition($id,'in_preparation','out_for_delivery',$employee,'Logistique'),'Delivery departure is persisted');
    check(!$workspace->transition($id,'out_for_delivery','completed',$employee,'Logistique'),'Cannot skip delivery stages');
    check((int)$db->fetchOne('SELECT count(*) FROM order_status_history WHERE order_id=? AND changed_by=?',[$id,$employee])===2,'History records successful changes and actor only');
    check($workspace->next('accepted','Salle et service')[0]==='in_preparation','All employee positions can update order status');
    check($workspace->transition($id,'out_for_delivery','delivered',$employee),'Delivery can be confirmed');
    check($workspace->transition($id,'delivered','completed',$employee),'No-equipment order finishes directly after delivery');
    check((int)$db->fetchOne('SELECT count(*) FROM employee_mail_outbox WHERE order_id=?',[$id])===0,'No equipment email for orders without loans');

    $createOrder=function () use ($db,$customer): string {
        return $db->fetchOne("INSERT INTO orders(reference,user_id,current_status_id,service_date,delivery_time,delivery_address_line1,delivery_postal_code,delivery_city,menu_subtotal,total_amount,created_at) VALUES (?,?,(SELECT id FROM order_statuses WHERE code='pending'),'2099-01-02','12:00','Test address','33000','Bordeaux',100,100,CURRENT_TIMESTAMP-INTERVAL '1 day') RETURNING id",['VG-'.random_int(10000000,99999999),$customer]);
    };
    $loan=$createOrder();
    $data=['action'=>'edit','contact_method'=>'phone','contacted_at'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i'),'contact_confirmed'=>'1','reason'=>'Accord du client pour le prêt de matériel','version'=>$workspace->order($loan)['updated_at'],'service_date'=>'2099-01-03','delivery_time'=>'13:00','delivery_address_line1'=>'Nouvelle adresse','delivery_postal_code'=>'33000','delivery_city'=>'Bordeaux','customer_note'=>'Test instructions','equipment_loaned'=>'1','equipment_description'=>'Deux plateaux'];
    $rejected=false; try { $workspace->change($loan,array_replace($data,['contact_confirmed'=>'']),$employee); } catch (DomainException) { $rejected=true; }
    check($rejected,'Order editing requires prior-contact confirmation');
    $workspace->change($loan,$data,$employee);
    check($workspace->order($loan)['equipment_loaned'] && $workspace->order($loan)['delivery_time']==='13:00:00','Contact-backed changes persist delivery and equipment details');
    check((int)$db->fetchOne('SELECT count(*) FROM order_contact_actions WHERE order_id=? AND contact_method=?',[$loan,'phone'])===1,'Contact mode, reason and changes are audited');
    foreach ([['pending','accepted'],['accepted','in_preparation'],['in_preparation','out_for_delivery'],['out_for_delivery','delivered']] as [$from,$to]) check($workspace->transition($loan,$from,$to,$employee),'Loan order advances '.$to);
    check(!$workspace->transition($loan,'delivered','completed',$employee),'Loan order cannot bypass equipment return');
    check($workspace->transition($loan,'delivered','awaiting_equipment_return',$employee),'Loan enters return-waiting state');
    $mail=$db->fetchAssociative('SELECT * FROM employee_mail_outbox WHERE order_id=?',[$loan]);
    check((bool)$mail && str_contains($mail['body'],'10 jours ouvrés') && str_contains($mail['body'],'600 euros') && str_contains($mail['body'],'répondant'),'Return notification is queued with deadline, fee and contact instructions');
    check(!$workspace->transition($loan,'delivered','awaiting_equipment_return',$employee) && (int)$db->fetchOne('SELECT count(*) FROM employee_mail_outbox WHERE order_id=?',[$loan])===1,'Repeated transition cannot duplicate notification');
    $fakeTransport=new class implements Symfony\Component\Mailer\Transport\TransportInterface {
        public array $messages=[];
        public bool $fail=true;
        public function send(Symfony\Component\Mime\RawMessage $message,?Symfony\Component\Mailer\Envelope $envelope=null): ?Symfony\Component\Mailer\SentMessage { if ($this->fail) throw new RuntimeException('Simulated transport failure'); $this->messages[]=$message; return null; }
        public function __toString(): string { return 'test-memory'; }
    };
    $mailer=new App\Employee\EquipmentMailer($db,$fakeTransport,'sender@example.test','smtp://test');
    check($mailer->sendPending($loan)===0 && !$db->fetchOne('SELECT sent_at FROM employee_mail_outbox WHERE order_id=?',[$loan]) && (bool)$db->fetchOne('SELECT last_error FROM employee_mail_outbox WHERE order_id=?',[$loan]),'Failed email remains pending with a visible error');
    $unconfiguredMailer=new App\Employee\EquipmentMailer($db,$fakeTransport,'','null://null');
    check($unconfiguredMailer->sendPending($loan)===0 && count($fakeTransport->messages)===0,'Unconfigured sender never marks notifications as sent');
    $fakeTransport->fail=false;
    check($mailer->sendPending($loan)===1 && count($fakeTransport->messages)===1,'Notification is sent through the mail transport');
    check($mailer->sendPending($loan)===0,'Already-sent notification is not resent');
    check($workspace->transition($loan,'awaiting_equipment_return','completed',$employee) && (bool)$workspace->order($loan)['equipment_returned_at'],'Completion records equipment restitution');
    $cancel=$createOrder();
    $cancelData=array_replace($data,['action'=>'cancel','version'=>$workspace->order($cancel)['updated_at'],'contact_method'=>'email','reason'=>'Annulation demandée par le client']);
    $rejected=false; try { $workspace->change($cancel,array_replace($cancelData,['reason'=>'']),$employee); } catch (DomainException) { $rejected=true; }
    check($rejected,'Cancellation without a reason is rejected');
    $workspace->change($cancel,$cancelData,$employee);
    check($workspace->order($cancel)['status']==='cancelled' && $workspace->order($cancel)['cancellation_contact_method']==='email','Cancellation saves status, reason and contact method');
    $deadline=new App\Employee\EquipmentReturnDeadline();
    check($deadline->after(new DateTimeImmutable('2026-12-18',new DateTimeZone('Europe/Paris')))->format('Y-m-d')==='2027-01-05','Ten working days excludes weekends, Christmas and New Year');
    check($deadline->after(new DateTimeImmutable('2026-04-03',new DateTimeZone('Europe/Paris')))->format('Y-m-d')==='2026-04-20','Ten working days excludes Easter Monday');
    $management=$container->get(App\Controller\EmployeeManagementController::class);
    foreach (['menus','dishes','hours'] as $page) {
        $response=$page==='hours' ? $management->hours(Symfony\Component\HttpFoundation\Request::create('/employe/horaires')) : $management->$page();
        check($response->getStatusCode()===200,'Employee '.$page.' page renders');
    }
    $response=$management->reviews(new App\Catalog\CustomerReviews($db)); check($response->getStatusCode()===200,'Employee reviews page renders');
    $post=function (string $key,array $data) use ($container): Symfony\Component\HttpFoundation\Request {
        return Symfony\Component\HttpFoundation\Request::create('/employe','POST',$data+['_token'=>(string)$container->get('smoke.csrf')->getToken($key)]);
    };
    $csrfRejected=false;
    try { $management->hours(Symfony\Component\HttpFoundation\Request::create('/employe/horaires','POST',['day'=>'1','action'=>'delete'])); }
    catch (Symfony\Component\Security\Core\Exception\AccessDeniedException) { $csrfRejected=true; }
    check($csrfRejected,'Employee mutations reject missing CSRF tokens');
    $response=$management->hours($post('employee_hours',['day'=>'1','opens_at'=>'10:00','closes_at'=>'17:30','action'=>'save']));
    check($response->getStatusCode()===303 && (new App\Catalog\OpeningHours($db))->all()[1]['opens_at']==='10:00:00','Edited hours feed the public footer');
    $response=$management->hours($post('employee_hours',['day'=>'1','opens_at'=>'18:00','closes_at'=>'09:00','action'=>'save']));
    check($response->getStatusCode()===422 && (new App\Catalog\OpeningHours($db))->all()[1]['opens_at']==='10:00:00','Invalid hours are rejected without changing stored hours');
    $management->hours($post('employee_hours',['day'=>'1','action'=>'delete']));
    check(!isset((new App\Catalog\OpeningHours($db))->all()[1]),'Employees can remove an opening-hour record');
    $dishId=$db->fetchOne('SELECT id FROM dishes WHERE is_active LIMIT 1');
    $dish=$db->fetchAssociative('SELECT * FROM dishes WHERE id=?',[$dishId]);
    $allergen=(string)$db->fetchOne('SELECT id FROM allergens LIMIT 1');
    $response=$management->dish($post('employee_dish'.$dishId,['name'=>'Plat intégration','description'=>'Description','dish_type_id'=>(string)$dish['dish_type_id'],'allergens'=>[$allergen]]),$dishId);
    check($response->getStatusCode()===303 && $db->fetchOne('SELECT name FROM dishes WHERE id=?',[$dishId])==='Plat intégration' && (int)$db->fetchOne('SELECT count(*) FROM dish_allergens WHERE dish_id=?',[$dishId])===1,'Employee edits update dishes and allergen associations');
    $menuData=$db->fetchAssociative('SELECT * FROM menus WHERE id=?',[$menu]);
    $response=$management->menu($post('employee_menu'.$menu,['title'=>'Menu intégration','description'=>'Menu modifié','conditions'=>'Conditions conservées','minimum_people'=>'10','minimum_price'=>'100.00','publication_status'=>'published','start_date'=>'','end_date'=>'','dishes'=>[$dishId]]),$menu);
    check($response->getStatusCode()===303 && $db->fetchOne('SELECT title FROM menus WHERE id=?',[$menu])==='Menu intégration','Employees can modify menu content and publication');
    check($db->fetchOne('SELECT menu_title FROM order_items WHERE order_id=?',[$id])==='Integration menu','Menu editing preserves existing order snapshots');
    $management->deleteDish($post('delete_dish'.$dishId,['confirmed'=>'1']),$dishId);
    check(!$db->fetchOne('SELECT is_active FROM dishes WHERE id=?',[$dishId]) && $db->fetchOne('SELECT publication_status FROM menus WHERE id=?',[$menu])==='unpublished','Dish removal unpublishes affected menus');
    $management->deleteMenu($post('delete_menu'.$menu,['confirmed'=>'1']),$menu);
    check($db->fetchOne('SELECT publication_status FROM menus WHERE id=?',[$menu])==='archived','Menu deletion removes it from the catalogue without losing order references');
    $review=$db->fetchOne("INSERT INTO reviews(order_id,rating,comment) VALUES (?,5,'Avis de test') RETURNING id",[$id]);
    $management->moderate($post('employee_review'.$review,['state'=>'approved']),$review);
    check(in_array($review,array_column((new App\Catalog\CustomerReviews($db))->list(true),'id'),true),'Employee-approved review appears in the homepage feed');
    $management->moderate($post('employee_review'.$review,['state'=>'rejected']),$review);
    check(!in_array($review,array_column((new App\Catalog\CustomerReviews($db))->list(true),'id'),true),'Employee-rejected review disappears from the homepage feed');
    $container->get('request_stack')->pop();
} finally { $db->rollBack(); $kernel->shutdown(); }
echo "Database changes rolled back.\n";
