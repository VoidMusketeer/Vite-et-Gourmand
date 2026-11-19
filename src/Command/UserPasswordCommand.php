<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:password', description: 'Définir le mot de passe d’un compte par son adresse e-mail')]
final class UserPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail du compte');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Exécutez cette commande dans un terminal interactif.');
            return Command::FAILURE;
        }

        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => trim((string) $input->getArgument('email')),
        ]);
        if (!$user instanceof User) {
            $io->error('Compte introuvable.');
            return Command::FAILURE;
        }

        $question = new Question('Nouveau mot de passe (12 à 72 octets) : ');
        $question->setHidden(true)->setHiddenFallback(false);
        $password = $io->askQuestion($question);
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            $io->error('Le mot de passe doit contenir entre 12 et 72 octets.');
            return Command::FAILURE;
        }

        $confirmation = new Question('Confirmer le mot de passe : ');
        $confirmation->setHidden(true)->setHiddenFallback(false);
        if ($password !== $io->askQuestion($confirmation)) {
            $io->error('La confirmation est différente. Aucun changement enregistré.');
            return Command::FAILURE;
        }

        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        $this->em->flush();
        $io->success('Mot de passe enregistré.');
        return Command::SUCCESS;
    }
}
