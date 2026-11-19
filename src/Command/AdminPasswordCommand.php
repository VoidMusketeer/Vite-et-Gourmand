<?php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputArgument};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: "app:admin:password",
    description: "Définir le mot de passe de Julie ou José",
)]
final class AdminPasswordCommand extends Command
{
    public function __construct(private Connection $db)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument("email", InputArgument::REQUIRED);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument("email");
        $password = $io->askHidden("Nouveau mot de passe (12 à 72 octets)");
        if (
            !is_string($password) ||
            strlen($password) < 12 ||
            strlen($password) > 72 ||
            $password !== $io->askHidden("Confirmer le mot de passe")
        ) {
            $io->error("Mot de passe invalide ou confirmation différente.");
            return Command::FAILURE;
        }
        $count = $this->db->executeStatement(
            "UPDATE users SET password_hash=? WHERE email=? AND role_id=(SELECT id FROM roles WHERE code='administrator')",
            [password_hash($password, PASSWORD_DEFAULT), $email],
        );
        if (!$count) {
            $io->error("Compte administrateur introuvable.");
            return Command::FAILURE;
        }
        $io->success("Mot de passe enregistré.");
        return Command::SUCCESS;
    }
}
