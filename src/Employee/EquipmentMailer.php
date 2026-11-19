<?php
declare(strict_types=1);
namespace App\Employee;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

final class EquipmentMailer
{
    public function __construct(
        private Connection $db,
        private TransportInterface $transport,
        #[Autowire("%env(MAIL_FROM_ADDRESS)%")] private string $sender,
        #[Autowire("%env(MAILER_DSN)%")] private string $dsn,
    ) {}

    public function sendPending(
        ?string $orderId = null,
        ?string $employeeId = null,
    ): int {
        $sent = 0;
        $ids = $this->db->fetchFirstColumn(
            "SELECT id FROM employee_mail_outbox WHERE sent_at IS NULL" .
                ($orderId
                    ? " AND order_id=?"
                    : ($employeeId
                        ? " AND employee_id=?"
                        : "")) .
                " ORDER BY id LIMIT 100",
            $orderId ? [$orderId] : ($employeeId ? [$employeeId] : []),
        );
        foreach ($ids as $id) {
            $sent += (int) $this->db->transactional(function () use (
                $id,
            ): bool {
                $mail = $this->db->fetchAssociative(
                    "SELECT * FROM employee_mail_outbox WHERE id=? AND sent_at IS NULL FOR UPDATE SKIP LOCKED",
                    [$id],
                );
                if (!$mail) {
                    return false;
                }
                try {
                    if (
                        !filter_var($this->sender, FILTER_VALIDATE_EMAIL) ||
                        str_ends_with($this->sender, ".invalid") ||
                        str_contains($this->dsn, "null://")
                    ) {
                        throw new \RuntimeException(
                            "Configurez MAIL_FROM_ADDRESS et un transport MAILER_DSN réel.",
                        );
                    }
                    $from = new Address($this->sender, "Vite & Gourmand");
                    $this->transport->send(
                        (new Email())
                            ->from($from)
                            ->replyTo($from)
                            ->to($mail["recipient"])
                            ->subject($mail["subject"])
                            ->text($mail["body"]),
                    );
                    $this->db->executeStatement(
                        "UPDATE employee_mail_outbox SET sent_at=CURRENT_TIMESTAMP,attempts=attempts+1,last_error=NULL WHERE id=?",
                        [$id],
                    );
                    return true;
                } catch (\Throwable) {
                    $this->db->executeStatement(
                        "UPDATE employee_mail_outbox SET attempts=attempts+1,last_error=? WHERE id=?",
                        [
                            "Envoi non confirmé. Vérifiez la configuration mail puis relancez la notification.",
                            $id,
                        ],
                    );
                    return false;
                }
            });
        }
        return $sent;
    }
}
