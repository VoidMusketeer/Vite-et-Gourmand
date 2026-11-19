<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher,
        MailerInterface $mailer, #[Autowire('%env(MAIL_FROM_ADDRESS)%')] string $sender): Response
    {
        $values = $errors = [];
        $limits = ['firstName' => 100, 'lastName' => 100, 'email' => 254, 'phone' => 30,
            'addressLine1' => 255, 'addressLine2' => 255, 'postalCode' => 20, 'city' => 120];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('registration', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Le formulaire a expiré. Rechargez la page.');
            }
            foreach ($limits as $field => $limit) {
                $values[$field] = trim($request->request->getString($field));
                if ($values[$field] === '' && $field !== 'addressLine2') $errors[$field] = 'Ce champ est obligatoire.';
                elseif (mb_strlen($values[$field]) > $limit) $errors[$field] = "Maximum $limit caractères.";
            }
            if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Saisissez une adresse e-mail valide.';
            $phone = preg_replace('/[\s().-]/', '', $values['phone']);
            if (!preg_match('/^(?:0[67][0-9]{8}|\+33[67][0-9]{8}|\+(?!33)[1-9][0-9]{7,14})$/D', $phone)) {
                $errors['phone'] = 'Saisissez un mobile français (06 ou 07) ou un numéro international avec son indicatif.';
            }
            $password = $request->request->getString('password');
            if (mb_strlen($password) < 10 || strlen($password) > 72 || !preg_match('/\p{Lu}/u', $password)
                || !preg_match('/\p{Ll}/u', $password) || !preg_match('/[0-9]/', $password)
                || !preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
                $errors['password'] = 'Utilisez au moins 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial (72 octets maximum).';
            }
            if ($password !== $request->request->getString('confirmation')) $errors['confirmation'] = 'Les mots de passe ne correspondent pas.';
            if ($request->request->getString('terms') !== '1') $errors['terms'] = 'Veuillez accepter les conditions générales et la politique de confidentialité.';
            if (!$errors && $em->getRepository(User::class)->findOneBy(['email' => $values['email']])) {
                $errors['email'] = 'Cette adresse est déjà utilisée. Connectez-vous ou réinitialisez votre mot de passe.';
            }
            if (!$errors) {
                $role = $em->getRepository(Role::class)->findOneBy(['code' => 'user']);
                if (!$role) throw new \LogicException('Le rôle utilisateur doit être initialisé.');
                $user = new User($role, $values['email'], '', $values['firstName'], $values['lastName'],
                    $phone, $values['addressLine1'], $values['postalCode'], $values['city']);
                $user->setAddressLine2($values['addressLine2'] ?: null);
                $user->setPasswordHash($hasher->hashPassword($user, $password));
                try {
                    // The configured Doctrine mail queue shares the account transaction.
                    $em->wrapInTransaction(function () use ($em, $user, $mailer, $sender): void {
                        $em->persist($user);
                        $em->flush();
                        $mailer->send((new Email())->from(new Address($sender, 'Vite & Gourmand'))
                            ->to($user->getEmail())->subject('Bienvenue chez Vite & Gourmand !')
                            ->text("Bonjour ".$user->getFirstName().",\n\nBienvenue chez Vite & Gourmand ! Votre compte utilisateur a été créé. Vous pouvez maintenant vous connecter pour retrouver vos commandes et gérer vos coordonnées.\n\nÀ bientôt,\nL’équipe Vite & Gourmand"));
                    });
                    $this->addFlash('registration_success', 'Votre compte a été créé. Vous pouvez vous connecter. Un e-mail de bienvenue vous sera envoyé automatiquement.');
                    return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException) {
                    $errors['email'] = 'Cette adresse est déjà utilisée.';
                }
            }
        }
        return $this->render('pages/register.html.twig', ['values' => $values, 'errors' => $errors],
            new Response(status: $errors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
