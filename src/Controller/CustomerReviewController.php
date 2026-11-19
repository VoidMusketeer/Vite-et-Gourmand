<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CustomerReviewController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/compte/avis', name:'customer_review_create', methods:['POST'])]
    public function create(Request $request,Connection $db): Response
    {
        $p=$request->request;
        if (!$this->isCsrfTokenValid('customer_review',$p->getString('_token'))) throw $this->createAccessDeniedException();
        $rating=$p->getString('rating');
        $comment=trim($p->getString('comment'));
        $ok=in_array($rating,['1','2','3','4','5'],true) && $comment!=='' && mb_strlen($comment)<=2000;
        $count=$ok ? $db->executeStatement("INSERT INTO reviews(order_id,rating,comment)
            SELECT o.id,?,? FROM orders o JOIN order_statuses s ON s.id=o.current_status_id
            WHERE o.id::text=? AND o.user_id=? AND s.code='completed' ON CONFLICT(order_id) DO NOTHING",
            [$rating,$comment,$p->getString('order'),$this->getUser()->getId()]) : 0;
        $this->addFlash('notice',$count ? 'Merci, votre avis a été transmis pour validation.' : 'Avis non enregistré. Vérifiez la commande, la note et le commentaire.');
        return $this->redirectToRoute('app_account',[],303);
    }
}
