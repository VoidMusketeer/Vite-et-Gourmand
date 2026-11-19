<?php
namespace App\Controller;

use App\Order\Checkout;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CheckoutController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route("/paiement", name: "app_checkout_submit", methods: ["POST"])]
    public function submit(Request $request, Checkout $checkout): Response
    {
        if (
            !$this->isCsrfTokenValid(
                "checkout",
                $request->request->getString("_token"),
            )
        ) {
            throw $this->createAccessDeniedException();
        }
        $session = $request->getSession();
        try {
            $references = $checkout->submit(
                $this->getUser()->getId(),
                $request->request->getString("submission"),
                $session->get("basket", []),
                $request->request->all(),
            );
        } catch (\DomainException $e) {
            $this->addFlash("checkout_error", $e->getMessage());
            return $this->redirectToRoute("app_checkout", [], 303);
        }
        if (
            $session->get("checkout_submission") ===
            $request->request->getString("submission")
        ) {
            $session->remove("basket");
            $session->remove("checkout_submission");
        }
        $this->addFlash(
            "notice",
            "Demande enregistrée : " .
                implode(", ", $references) .
                ". Les menus sont réservés.",
        );
        return $this->redirectToRoute("customer_orders", [], 303);
    }
}
