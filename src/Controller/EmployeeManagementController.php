<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\CustomerReviews;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYEE')]
#[Route('/employe')]
final class EmployeeManagementController extends AbstractController
{
    public function __construct(private Connection $db) {}
    private function token(Request $request, string $key): void
    {
        if (!$this->isCsrfTokenValid($key, $request->request->getString('_token'))) throw $this->createAccessDeniedException();
    }
    #[Route('/menus', name: 'employee_menus', methods: ['GET'])]
    public function menus(): Response
    {
        return $this->render('employee/menus.html.twig', ['menus' => $this->db->fetchAllAssociative("SELECT id,title,publication_status,minimum_people,minimum_price FROM menus WHERE publication_status<>'archived' ORDER BY title")]);
    }
    #[Route('/menus/{id}', name: 'employee_menu_edit', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    public function menu(Request $request, string $id): Response
    {
        $menu = $this->db->fetchAssociative("SELECT *,available_from::date AS start_date,available_until::date AS end_date FROM menus WHERE id=? AND publication_status<>'archived'", [$id]) ?: throw $this->createNotFoundException();
        $dishes = $this->db->fetchAllAssociative('SELECT d.id,d.name,t.label FROM dishes d JOIN dish_types t ON t.id=d.dish_type_id WHERE d.is_active ORDER BY t.display_order,d.name');
        $selected = $this->db->fetchFirstColumn('SELECT dish_id FROM menu_dishes WHERE menu_id=? AND NOT is_optional ORDER BY display_order', [$id]);
        $error = null;
        if ($request->isMethod('POST')) {
            $this->token($request, 'employee_menu' . $id);
            foreach (['title', 'description', 'conditions', 'minimum_people', 'minimum_price', 'publication_status', 'start_date', 'end_date'] as $field) $menu[$field] = $request->request->getString($field);
            $selected = $request->request->all('dishes');
            $min = filter_var($menu['minimum_people'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
            if (!trim($menu['title']) || mb_strlen($menu['title']) > 160 || !trim($menu['description']) || mb_strlen($menu['description']) > 5000 || mb_strlen($menu['conditions']) > 5000 || !$min || !preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $menu['minimum_price']) || !in_array($menu['publication_status'], ['draft', 'published', 'unpublished'], true)) $error = 'Vérifiez le titre, la description, le prix global, le minimum et la publication.';
            if (!$selected || array_diff($selected, array_column($dishes, 'id'))) $error = 'Choisissez au moins un plat du catalogue.';
            foreach (['start_date', 'end_date'] as $key) {
                $date = $menu[$key] ?? '';
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if ($date !== '' && (!$parsed || $parsed->format('Y-m-d') !== $date)) $error = 'Date invalide.';
            }
            if ($menu['start_date'] && $menu['end_date'] && $menu['end_date'] < $menu['start_date']) $error = 'La fin doit suivre le début.';
            if (!$error) {
                $this->db->transactional(function () use ($id, $menu, $min, $selected) {
                    $old = $this->db->fetchAssociative('SELECT available_from::date AS start_date,available_until::date AS end_date FROM menus WHERE id=? FOR UPDATE', [$id]);
                    $meta = json_decode($menu['catalogue'] ?? '{}', true) ?: [];
                    if ($menu['start_date'] || $menu['end_date'] || $old['start_date'] || $old['end_date']) {
                        $meta['start'] = $meta['end'] = null;
                        $meta['season'] = $menu['start_date'] || $menu['end_date'] ? trim(($menu['start_date'] ? 'Du ' . (new \DateTimeImmutable($menu['start_date']))->format('d/m/Y') : '') . ($menu['end_date'] ? ' au ' . (new \DateTimeImmutable($menu['end_date']))->format('d/m/Y') : '')) : 'Toute l’année';
                    }
                    $this->db->update('menus', ['catalogue' => json_encode($meta, JSON_THROW_ON_ERROR)], ['id' => $id]);
                    $this->db->update('menus', ['title' => trim($menu['title']), 'description' => trim($menu['description']), 'conditions' => trim($menu['conditions']), 'minimum_people' => $min, 'minimum_price' => $menu['minimum_price'], 'publication_status' => $menu['publication_status'], 'available_from' => $menu['start_date'] ?: null, 'available_until' => $menu['end_date'] ? $menu['end_date'] . ' 23:59:59' : null, 'updated_by' => $this->getUser()->getId()], ['id' => $id]);
                    $this->db->executeStatement('DELETE FROM menu_dishes WHERE menu_id=? AND NOT is_optional', [$id]);
                    foreach (array_values(array_unique($selected)) as $i => $dish) $this->db->executeStatement('INSERT INTO menu_dishes(menu_id,dish_id,display_order) VALUES (?,?,?) ON CONFLICT(menu_id,dish_id) DO UPDATE SET is_optional=false,additional_price=0,display_order=EXCLUDED.display_order', [$id, $dish, $i]);
                });
                $this->addFlash('success', 'Menu modifié. Les commandes existantes conservent leur prix et leur intitulé.');
                return $this->redirectToRoute('employee_menus', [], 303);
            }
        }
        return $this->render('employee/menu.html.twig', compact('menu', 'selected', 'dishes', 'error'), new Response(status: $error ? 422 : 200));
    }
    #[Route('/menus/{id}/supprimer', name: 'employee_menu_delete', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function deleteMenu(Request $request, string $id): Response
    {
        $this->token($request, 'delete_menu' . $id);
        if ($request->request->getString('confirmed') !== '1') throw $this->createAccessDeniedException();
        if (!$this->db->executeStatement("UPDATE menus SET publication_status='archived',updated_by=? WHERE id=?", [$this->getUser()->getId(), $id])) throw $this->createNotFoundException();
        $this->addFlash('success', 'Menu supprimé du catalogue. Son archive est conservée pour les commandes.');
        return $this->redirectToRoute('employee_menus', [], 303);
    }
    #[Route('/plats', name: 'employee_dishes', methods: ['GET'])]
    public function dishes(): Response
    {
        return $this->render('employee/dishes.html.twig', ['dishes' => $this->db->fetchAllAssociative('SELECT d.id,d.name,t.label,(SELECT count(*) FROM menu_dishes md WHERE md.dish_id=d.id) AS uses FROM dishes d JOIN dish_types t ON t.id=d.dish_type_id WHERE d.is_active ORDER BY t.display_order,d.name')]);
    }
    #[Route('/plats/{id}', name: 'employee_dish_edit', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    public function dish(Request $request, string $id): Response
    {
        $dish = $this->db->fetchAssociative('SELECT * FROM dishes WHERE id=? AND is_active', [$id]) ?: throw $this->createNotFoundException();
        $types = $this->db->fetchAllAssociative('SELECT id,label FROM dish_types ORDER BY display_order');
        $allergens = $this->db->fetchAllAssociative('SELECT id,name FROM allergens ORDER BY name');
        $selected = $this->db->fetchFirstColumn('SELECT allergen_id FROM dish_allergens WHERE dish_id=?', [$id]);
        $error = null;
        if ($request->isMethod('POST')) {
            $this->token($request, 'employee_dish' . $id);
            foreach (['name', 'description', 'dish_type_id'] as $field) $dish[$field] = $request->request->getString($field);
            $selected = $request->request->all('allergens');
            if (!trim($dish['name']) || mb_strlen($dish['name']) > 160 || mb_strlen($dish['description'] ?? '') > 5000 || !in_array($dish['dish_type_id'], array_column($types, 'id')) || array_diff($selected, array_column($allergens, 'id'))) $error = 'Vérifiez le nom, le type et les allergènes.';
            if (!$error) {
                $this->db->transactional(function () use ($id, $dish, $selected) {
                    $this->db->executeQuery('SELECT id FROM dishes WHERE id=? FOR UPDATE', [$id]);
                    $this->db->update('dishes', ['name' => trim($dish['name']), 'description' => trim($dish['description'] ?? ''), 'dish_type_id' => $dish['dish_type_id'], 'allergens_reviewed' => 'true'], ['id' => $id]);
                    $this->db->executeStatement('DELETE FROM dish_allergens WHERE dish_id=?', [$id]);
                    foreach (array_unique($selected) as $allergen) $this->db->insert('dish_allergens', ['dish_id' => $id, 'allergen_id' => $allergen]);
                });
                $this->addFlash('success', 'Plat et allergènes mis à jour dans les menus associés.');
                return $this->redirectToRoute('employee_dishes', [], 303);
            }
        }
        return $this->render('employee/dish.html.twig', compact('dish', 'types', 'allergens', 'selected', 'error'), new Response(status: $error ? 422 : 200));
    }
    #[Route('/plats/{id}/supprimer', name: 'employee_dish_delete', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function deleteDish(Request $request, string $id): Response
    {
        $this->token($request, 'delete_dish' . $id);
        if ($request->request->getString('confirmed') !== '1') throw $this->createAccessDeniedException();
        $this->db->transactional(function () use ($id) {
            if (!$this->db->executeStatement('UPDATE dishes SET is_active=false WHERE id=?', [$id])) throw $this->createNotFoundException();
            $this->db->executeStatement("UPDATE menus SET publication_status='unpublished',updated_by=? WHERE id IN (SELECT menu_id FROM menu_dishes WHERE dish_id=?) AND publication_status='published'", [$this->getUser()->getId(), $id]);
        });
        $this->addFlash('success', 'Plat retiré du catalogue. Les menus publiés qui l’utilisent sont dépubliés : remplacez ce plat avant de les republier.');
        return $this->redirectToRoute('employee_dishes', [], 303);
    }
    #[Route('/horaires', name: 'employee_hours', methods: ['GET', 'POST'])]
    public function hours(Request $request): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $this->token($request, 'employee_hours');
            $day = $request->request->getInt('day');
            $open = $request->request->getString('opens_at');
            $close = $request->request->getString('closes_at');
            $closed = $request->request->getString('closed') === '1';
            $action = $request->request->getString('action');
            if ($day < 1 || $day > 7 || !in_array($action, ['save', 'delete'], true)) $error = 'Jour ou action invalide.';
            elseif ($action === 'save' && !$closed && (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $open) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $close) || $close <= $open)) $error = 'La fermeture doit suivre l’ouverture, avec des horaires valides.';
            if (!$error) {
                if ($action === 'delete') $this->db->executeStatement('DELETE FROM opening_hours WHERE day_of_week=?', [$day]);
                else $this->db->executeStatement('INSERT INTO opening_hours(day_of_week,opens_at,closes_at,is_closed,updated_by) VALUES (?,?,?,?,?) ON CONFLICT(day_of_week) DO UPDATE SET opens_at=EXCLUDED.opens_at,closes_at=EXCLUDED.closes_at,is_closed=EXCLUDED.is_closed,updated_by=EXCLUDED.updated_by', [$day, $closed ? null : $open, $closed ? null : $close, $closed ? 'true' : 'false', $this->getUser()->getId()]);
                $this->addFlash('success', 'Horaires publics mis à jour.');
                return $this->redirectToRoute('employee_hours', [], 303);
            }
        }
        return $this->render('employee/hours.html.twig', ['hours' => $this->db->fetchAllAssociativeIndexed('SELECT day_of_week,opens_at,closes_at,is_closed FROM opening_hours ORDER BY day_of_week'), 'error' => $error], new Response(status: $error ? 422 : 200));
    }
    #[Route('/avis', name: 'employee_reviews', methods: ['GET'])]
    public function reviews(CustomerReviews $reviews): Response
    {
        return $this->render('employee/reviews.html.twig', ['reviews' => $reviews->list()]);
    }
    #[Route('/avis/{id}', name: 'employee_review_moderate', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function moderate(Request $request, string $id): Response
    {
        $this->token($request, 'employee_review' . $id);
        $state = $request->request->getString('state');
        if (!in_array($state, ['approved', 'rejected'], true)) throw $this->createAccessDeniedException();
        if (!$this->db->executeStatement('UPDATE reviews SET moderation_status=?,moderated_by=?,moderated_at=CURRENT_TIMESTAMP WHERE id=?', [$state, $this->getUser()->getId(), $id])) throw $this->createNotFoundException();
        $this->addFlash('success', $state === 'approved' ? 'Avis validé et visible sur l’accueil.' : 'Avis refusé et retiré de l’accueil.');
        return $this->redirectToRoute('employee_reviews', [], 303);
    }
}
