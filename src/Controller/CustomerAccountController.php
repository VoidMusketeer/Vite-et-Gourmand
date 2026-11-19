<?php

namespace App\Controller;

use App\Employee\EmployeeWorkspace;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CustomerAccountController extends AbstractController
{
    #[Route('/compte/commandes', name: 'customer_orders', defaults: ['section' => 'orders'], methods: ['GET'])]
    #[Route('/compte/historique', name: 'customer_history', defaults: ['section' => 'history'], methods: ['GET'])]
    #[Route('/compte/parametres', name: 'customer_profile', defaults: ['section' => 'profile'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(string $section, Connection $db): Response
    {
        $user = $this->getUser();
        $orders = $db->fetchAllAssociative("SELECT o.*, s.code AS status, i.menu_title, i.people_count, m.slug,
            (SELECT url FROM menu_images WHERE menu_id=m.id ORDER BY is_cover DESC,display_order LIMIT 1) AS image,
            r.id AS review_id FROM orders o JOIN order_statuses s ON s.id=o.current_status_id
            LEFT JOIN order_items i ON i.order_id=o.id LEFT JOIN menus m ON m.id=i.menu_id
            LEFT JOIN reviews r ON r.order_id=o.id WHERE o.user_id=? ORDER BY o.service_date DESC", [$user->getId()]);
        foreach ($orders as &$order) {
            $order['history'] = $db->fetchAllAssociative('SELECT h.created_at,h.public_comment,s.code FROM order_status_history h JOIN order_statuses s ON s.id=h.status_id WHERE h.order_id=? ORDER BY h.created_at,h.id', [$order['id']]);
        }
        unset($order);
        return $this->page($section, false, $orders, [
            'first_name' => $user->getFirstName(), 'last_name' => $user->getLastName(),
            'email' => $user->getEmail(), 'phone' => $user->getPhone(),
            'address' => $user->getAddressLine1(), 'postal_code' => $user->getPostalCode(), 'city' => $user->getCity(),
        ]);
    }

    #[Route('/demo/compte', name: 'customer_demo', defaults: ['section' => 'orders'], methods: ['GET'])]
    #[Route('/demo/compte/{section}', name: 'customer_demo_section', requirements: ['section' => 'orders|history|profile'], methods: ['GET'])]
    public function demo(string $section): Response
    {
        $orders = [];
        foreach ([['1048','pending','printemps','Menu Printemps',14,392,'2026-07-25','12:30','2026-07-18 09:12','menu-paques.jpg'],
            ['1052','in_preparation','jardin','Jardin Gourmand',8,248,'2026-07-30','19:00','2026-07-24 08:30','menu-vegetarien.jpg'],
            ['1046','completed','fete','Table de fête',6,234,'2026-06-12','20:00','2026-06-13 10:00','menu-fete.jpg']] as $row) {
            [$id,$status,$slug,$title,$people,$total,$date,$time,$updated,$image] = $row;
            $updated = new \DateTimeImmutable($updated, new \DateTimeZone('Europe/Paris'));
            $orders[] = ['id'=>$id,'reference'=>'VG-'.$id,'status'=>$status,'slug'=>$slug,'menu_title'=>$title,
                'people_count'=>$people,'total_amount'=>$total,'service_date'=>$date,'delivery_time'=>$time,'updated_at'=>$updated,
                'image'=>'images/menus/'.$slug.'/'.$image,'delivery_address_line1'=>'12 rue des Lilas','delivery_postal_code'=>'33200',
                'delivery_city'=>'Bordeaux','customer_note'=>'','review_id'=>null,
                'history'=>[['created_at'=>$updated,'code'=>$status,'public_comment'=>'']]];
        }
        return $this->page($section, true, $orders, ['first_name'=>'Lucie','last_name'=>'Bernard','email'=>'lucie@exemple.fr','phone'=>'06 00 00 00 00','address'=>'12 rue des Lilas','postal_code'=>'33200','city'=>'Bordeaux Caudéran']);
    }

    private function page(string $section, bool $demo, array $orders, array $profile): Response
    {
        $reviewOrder = null;
        foreach ($orders as $order) if ($order['status'] === 'completed' && !$order['review_id']) { $reviewOrder = $order; break; }
        $orders = array_values(array_filter($orders, static fn ($o) => in_array($o['status'], ['completed','cancelled'], true) === ($section === 'history')));
        return $this->render('customer/account.html.twig', compact('section','demo','orders','profile','reviewOrder') + ['statuses'=>EmployeeWorkspace::STATUSES]);
    }

    #[Route('/compte/parametres', name: 'customer_profile_save', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveProfile(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if (!$this->isCsrfTokenValid('customer_profile', $request->request->getString('_token'))) throw $this->createAccessDeniedException();
        $p = $request->request;
        $user = $this->getUser();
        $field = $p->getString('field');
        try {
            $required = static function (string $value, int $max): string {
                $value = trim($value);
                if ($value === '' || mb_strlen($value) > $max) throw new \DomainException('Veuillez compléter les champs avec des valeurs valides.');
                return $value;
            };
            if ($field === 'name') {
                $first = $required($p->getString('first_name'),100);
                $last = $required($p->getString('last_name'),100);
                $user->setFirstName($first); $user->setLastName($last);
            } elseif ($field === 'email') {
                $email = $required($p->getString('email'),180);
                if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \DomainException('Adresse e-mail invalide.');
                if (!$hasher->isPasswordValid($user,$p->getString('current_password'))) throw new \DomainException('Le mot de passe actuel est incorrect.');
                $user->setEmail($email);
            } elseif ($field === 'phone') {
                $phone = $required($p->getString('phone'),30);
                if (!preg_match('/^\+?[\d\s().-]{6,30}$/',$phone)) throw new \DomainException('Numéro de téléphone invalide.');
                $user->setPhone($phone);
            } elseif ($field === 'address') {
                $address = $required($p->getString('address'),255); $postal = $required($p->getString('postal_code'),20); $city = $required($p->getString('city'),120);
                $user->setAddressLine1($address); $user->setPostalCode($postal); $user->setCity($city);
            } elseif ($field === 'password') {
                if (!$hasher->isPasswordValid($user,$p->getString('current_password'))) throw new \DomainException('Le mot de passe actuel est incorrect.');
                $password = $p->getString('password');
                if (mb_strlen($password)<12 || strlen($password)>72 || $password!==$p->getString('confirmation')) throw new \DomainException('Utilisez au moins 12 caractères et une confirmation identique (72 octets maximum).');
                $user->setPasswordHash($hasher->hashPassword($user,$password));
            } else { throw $this->createNotFoundException(); }
            $em->flush();
            $this->addFlash('notice','Les paramètres de votre compte ont été enregistrés.');
        } catch (\DomainException $e) { $this->addFlash('notice',$e->getMessage()); }
        catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) { $this->addFlash('notice','Cette adresse e-mail ne peut pas être utilisée.'); }
        return $this->redirectToRoute('customer_profile',[],303);
    }

    #[Route('/compte/commandes/{id}', name: 'customer_order_save', requirements: ['id'=>'[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveOrder(string $id, Request $request, Connection $db): Response
    {
        if (!$this->isCsrfTokenValid('customer_order'.$id,$request->request->getString('_token'))) throw $this->createAccessDeniedException();
        $p = $request->request;
        $db->beginTransaction();
        try {
            $order = $db->fetchAssociative('SELECT o.*,s.code FROM orders o JOIN order_statuses s ON s.id=o.current_status_id WHERE o.id=? AND o.user_id=? FOR UPDATE OF o',[$id,$this->getUser()->getId()]);
            if (!$order) throw $this->createNotFoundException();
            $action = $p->getString('action');
            if (!in_array($action,['cancel','edit','comment'],true)) throw $this->createNotFoundException();
            if (($action !== 'comment' && $order['code'] !== 'pending') || in_array($order['code'],['completed','cancelled'],true)) throw new \DomainException('Cette commande ne peut plus être modifiée.');
            $note = trim($p->getString('comment'));
            if (mb_strlen($note)>2000 || ($action !== 'edit' && $note==='')) throw new \DomainException('Saisissez un commentaire de 1 à 2 000 caractères.');
            if ($action === 'cancel') {
                $status = $db->fetchOne("SELECT id FROM order_statuses WHERE code='cancelled'");
                $db->executeStatement('UPDATE orders SET current_status_id=?,cancellation_reason=?,cancelled_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$note,$id]);
                $db->executeStatement('INSERT INTO order_status_history(order_id,status_id,changed_by,public_comment) VALUES (?,?,?,?)',[$id,$status,$this->getUser()->getId(),'Annulation demandée par le client : '.$note]);
            } elseif ($action === 'edit') {
                $time=$p->getString('time');
                if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)) throw new \DomainException('Heure de livraison invalide.');
                $db->executeStatement('UPDATE orders SET delivery_time=?,customer_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$time,$note,$id]);
                $db->executeStatement('INSERT INTO order_status_history(order_id,status_id,changed_by,public_comment) VALUES (?,?,?,?)',[$id,$order['current_status_id'],$this->getUser()->getId(),'Heure de livraison modifiée par le client : '.$time]);
            } else {
                $db->executeStatement('UPDATE orders SET customer_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$note,$id]);
                $db->executeStatement('INSERT INTO order_status_history(order_id,status_id,changed_by,public_comment) VALUES (?,?,?,?)',[$id,$order['current_status_id'],$this->getUser()->getId(),'Commentaire du client : '.$note]);
            }
            $db->commit(); $this->addFlash('notice',$action==='cancel'?'Votre commande a été annulée.':'Votre modification a été enregistrée.');
        } catch (\DomainException $e) { $db->rollBack(); $this->addFlash('notice',$e->getMessage()); }
        catch (\Throwable $e) { $db->rollBack(); throw $e; }
        return $this->redirectToRoute('customer_orders',[],303);
    }
}
