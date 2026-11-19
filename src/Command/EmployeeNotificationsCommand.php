<?php
namespace App\Command;
use App\Employee\EquipmentMailer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(
    name: "app:employee-notifications",
    description: "Relancer les notifications de comptes employés et de retour de matériel",
)]
final class EmployeeNotificationsCommand extends Command
{
    public function __construct(
        private EquipmentMailer $mailer,
        private Connection $db,
    ) {
        parent::__construct();
    }
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $output->writeln(
            $this->mailer->sendPending() . " notification(s) envoyée(s).",
        );
        $pending = (int) $this->db->fetchOne(
            "SELECT count(*) FROM employee_mail_outbox WHERE sent_at IS NULL",
        );
        $output->writeln($pending . " notification(s) en attente.");
        return $pending ? Command::FAILURE : Command::SUCCESS;
    }
}
