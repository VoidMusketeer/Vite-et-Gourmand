<?php

namespace App\Controller\PublicSite;

use App\Catalog\MenuCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class NavigationController extends AbstractController
{
    public function __construct(private MenuCatalog $catalog) {}

    #[Route("/", name: "app_home", methods: ["GET"])]
    public function home(\App\Catalog\CustomerReviews $reviews): Response
    {
        return $this->render("pages/home.html.twig", [
            "menus" => $this->catalog->all(homepageOnly: true),
            "reviews" => $reviews->list(true, homepageOnly: true),
        ]);
    }
    #[Route("/menus", name: "app_menus", methods: ["GET"])]
    public function menus(Request $request): Response
    {
        $guests = max(0, (int) $request->query->getString("guests"));
        $query = $request->query;
        $themes = $query->all("themes");
        $diets = $query->all("diets");
        $occasion = $query->getString("occasion");
        if ($occasion !== "") {
            $themes[] = $occasion;
        }
        $legacyTag = $query->getString(
            "tag",
            $query->getString("regime", "Tous"),
        );
        $date = $query->getString("date");
        $day = substr($date, 5);
        $search = trim($query->getString("q"));
        $sort = $query->getString("sort") === "desc" ? "desc" : "asc";

        $options = $this->catalog->filterOptions();
        $priceLimit = $options["priceLimit"];
        $maxPrice = max(
            0,
            min(
                $priceLimit,
                (int) $query->getString("price", (string) $priceLimit),
            ),
        );
        $menus = $this->catalog->all(
            filters: compact(
                "guests",
                "themes",
                "diets",
                "legacyTag",
                "day",
                "date",
                "maxPrice",
                "search",
                "sort",
            ),
        );

        return $this->render("pages/menus.html.twig", [
            "menus" => $menus,
            "guests" => $guests,
            "themes" => $themes,
            "diets" => $diets,
            "date" => $date,
            "price" => $maxPrice,
            "sort" => $sort,
            "search" => $search,
            "legacyTag" => $legacyTag,
            "themeOptions" => $options["themes"],
            "dietOptions" => $options["diets"],
            "priceLimit" => $priceLimit,
        ]);
    }

    #[Route("/menus/{id}", name: "app_menu_detail", methods: ["GET"])]
    public function menu(string $id): Response
    {
        $menu =
            $this->catalog->find($id) ?? throw $this->createNotFoundException();

        return $this->render("pages/menu_detail.html.twig", [
            "menu" => $menu,
            "id" => $id,
            "earliest" => $this->earliest($menu),
        ]);
    }

    private function earliest(array $menu): string
    {
        return max(
            $menu["first_date"] ?? "",
            (new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris")))
                ->modify("+" . $menu["hours"] . " hours")
                ->format("Y-m-d"),
        );
    }

    #[Route("/panier", name: "app_basket", methods: ["GET"])]
    #[Route("/paiement", name: "app_checkout", methods: ["GET"])]
    public function basket(Request $request): Response
    {
        $items = [];
        $total = 0;
        foreach ($request->getSession()->get("basket", []) as $id => $entry) {
            $menu = $this->catalog->find($id);
            if (!$menu) {
                continue;
            }
            // Older sessions store only the guest count.
            $guests = is_array($entry) ? $entry["guests"] : $entry;
            $selected = is_array($entry) ? $entry["options"] : [];
            $menu["base_price"] = $menu["price"];
            $menu["earliest"] = $this->earliest($menu);
            foreach ($menu["options"] as $option) {
                if (in_array($option["name"], $selected, true)) {
                    $menu["price"] += $option["price"];
                }
            }
            $menu["selected"] = $selected;
            $menu["date"] = is_array($entry) ? $entry["date"] : null;
            $subtotal = $menu["price"] * $guests;
            $items[$id] = $menu + [
                "guests" => $guests,
                "subtotal" => $subtotal,
            ];
            $total += $subtotal;
        }
        if ($request->attributes->get("_route") === "app_checkout") {
            $this->denyAccessUnlessGranted("ROLE_USER");
            if (!$items) {
                return $this->redirectToRoute("app_basket");
            }
            if (!$request->getSession()->has("checkout_submission")) {
                $request
                    ->getSession()
                    ->set("checkout_submission", bin2hex(random_bytes(16)));
            }
            return $this->render("pages/checkout.html.twig", [
                "items" => $items,
                "total" => $total,
                "submission" => $request
                    ->getSession()
                    ->get("checkout_submission"),
            ]);
        }
        return $this->render("pages/basket.html.twig", [
            "items" => $items,
            "total" => $total,
        ]);
    }

    #[Route(
        "/panier/actions",
        name: "app_basket_action",
        methods: ["POST"],
        priority: 10,
    )]
    public function basketAction(Request $request): Response
    {
        if (
            !$this->isCsrfTokenValid(
                "basket",
                $request->request->getString("_token"),
            )
        ) {
            throw $this->createAccessDeniedException();
        }
        $session = $request->getSession();
        if ($request->request->getString("action") === "clear") {
            $session->set("basket_undo", $session->get("basket", []));
            $session->set("basket", []);
        } elseif ($request->request->getString("action") === "undo") {
            $session->set(
                "basket",
                array_replace(
                    $session->remove("basket_undo") ?? [],
                    $session->get("basket", []),
                ),
            );
        }
        return $this->redirectToRoute(
            "app_basket",
            [],
            Response::HTTP_SEE_OTHER,
        );
    }

    #[Route("/panier/{id}", name: "app_basket_item", methods: ["POST"])]
    public function basketItem(string $id, Request $request): Response
    {
        if (!$this->getUser()) {
            $request
                ->getSession()
                ->set(
                    "_security.main.target_path",
                    $this->generateUrl("app_menu_detail", ["id" => $id]),
                );
            return $this->redirectToRoute(
                "app_login",
                [],
                Response::HTTP_SEE_OTHER,
            );
        }
        $menu =
            $this->catalog->find($id) ?? throw $this->createNotFoundException();
        if (
            !$this->isCsrfTokenValid(
                "basket",
                $request->request->getString("_token"),
            )
        ) {
            throw $this->createAccessDeniedException();
        }
        $session = $request->getSession();
        $basket = $session->get("basket", []);
        if ($request->request->getString("action") === "remove") {
            $session->set(
                "basket_undo",
                isset($basket[$id]) ? [$id => $basket[$id]] : [],
            );
            unset($basket[$id]);
        } else {
            $guests = $request->request->getInt("guests");
            if ($menu["stock"] < 1) {
                $this->addFlash("basket_error", "Ce menu est épuisé.");
                return $this->redirectToRoute("app_basket");
            }
            if ($guests < $menu["min"] || $guests > 9999) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException(
                    "Nombre de personnes incorrect.",
                );
            }
            $date = $request->request->getString("date");
            if ($request->request->getString("date_format") === "fr") {
                $frenchDate = \DateTimeImmutable::createFromFormat(
                    "!d/m/Y",
                    $date,
                );
                if (!$frenchDate || $frenchDate->format("d/m/Y") !== $date) {
                    $this->addFlash(
                        "basket_error",
                        "Saisissez une date valide au format JJ/MM/AAAA.",
                    );
                    return $this->redirectToRoute("app_basket");
                }
                $date = $frenchDate->format("Y-m-d");
            }
            $parsed = \DateTimeImmutable::createFromFormat("!Y-m-d", $date);
            $day = substr($date, 5);
            if (
                !$parsed ||
                $parsed->format("Y-m-d") !== $date ||
                $date < $this->earliest($menu) ||
                ($menu["last_date"] && $date > $menu["last_date"]) ||
                ($menu["start"] &&
                    ($day < $menu["start"] || $day > $menu["end"]))
            ) {
                $this->addFlash(
                    "basket_error",
                    "Cette date est indisponible pour ce menu. Vérifiez le délai de préparation et la période de disponibilité.",
                );
                return $this->redirectToRoute("app_basket");
            }
            $options = $request->request->all("options");
            foreach ($options as $option) {
                if (
                    !in_array(
                        $option,
                        array_column($menu["options"], "name"),
                        true,
                    )
                ) {
                    throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException(
                        "Option inconnue.",
                    );
                }
            }
            $basket[$id] = [
                "guests" => $guests,
                "options" => array_unique($options),
                "date" => $date,
            ];
        }
        $session->set("basket", $basket);
        return $this->redirectToRoute(
            "app_basket",
            [],
            Response::HTTP_SEE_OTHER,
        );
    }

    #[Route("/contact", name: "app_contact", methods: ["GET"])]
    public function contact(): Response
    {
        return $this->render("pages/contact.html.twig");
    }

    #[Route("/notre-histoire", name: "app_story", methods: ["GET"])]
    public function story(): Response
    {
        return $this->render("pages/about.html.twig");
    }

    #[Route(
        "/mentions-legales",
        name: "app_legal",
        defaults: ["title" => "Mentions légales"],
        methods: ["GET"],
    )]
    #[Route(
        "/cgv",
        name: "app_terms",
        defaults: ["title" => "CGV"],
        methods: ["GET"],
    )]
    #[Route(
        "/confidentialite",
        name: "app_privacy",
        defaults: ["title" => "Confidentialité"],
        methods: ["GET"],
    )]
    public function page(string $title): Response
    {
        if ($title === "CGV") {
            return $this->render("pages/equipment_terms.html.twig");
        }
        return $this->render("pages/placeholder.html.twig", [
            "title" => $title,
        ]);
    }

    #[Route("/connexion", name: "app_login", methods: ["GET", "POST"])]
    public function login(
        \Symfony\Component\Security\Http\Authentication\AuthenticationUtils $auth,
    ): Response {
        return $this->render("pages/login.html.twig", [
            "error" => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route(
        "/mot-de-passe-oublie",
        name: "app_forgot_password",
        methods: ["GET", "POST"],
    )]
    public function forgotPassword(Request $request): Response
    {
        $error = null;
        $submitted = false;
        $email = "";
        if ($request->isMethod("POST")) {
            if (
                !$this->isCsrfTokenValid(
                    "forgot_password",
                    $request->request->getString("_token"),
                )
            ) {
                throw $this->createAccessDeniedException();
            }
            $email = trim($request->request->getString("email"));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Veuillez saisir une adresse e-mail valide.";
            } else {
                $submitted = true;
                $email = "";
            }
        }

        return $this->render(
            "pages/forgot_password.html.twig",
            [
                "error" => $error,
                "submitted" => $submitted,
                "email" => $email,
            ],
            new Response(
                status: $error
                    ? Response::HTTP_UNPROCESSABLE_ENTITY
                    : Response::HTTP_OK,
            ),
        );
    }

    #[Route("/compte", name: "app_account", methods: ["GET"])]
    #[IsGranted("ROLE_USER")]
    public function account(): Response
    {
        if ($this->isGranted("ROLE_ADMIN")) {
            return $this->redirectToRoute("app_admin");
        }
        if ($this->isGranted("ROLE_EMPLOYEE")) {
            return $this->redirectToRoute("app_employee");
        }
        return $this->redirectToRoute("customer_orders");
    }

    #[Route("/demo/administration", name: "app_admin_demo", methods: ["GET"])]
    public function adminDemo(): Response
    {
        return $this->render("pages/admin.html.twig");
    }

    #[Route("/deconnexion", name: "app_logout", methods: ["POST"])]
    public function logout(): never
    {
        throw new \LogicException(
            "Logout must be handled by the security firewall.",
        );
    }
}
