<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:accounts:easy-access', description: 'Create or reset the three requested demonstration accounts')]
final class EasyAccessAccountsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accounts = [
            ['Admin', 'administrator', 'ROLE_ADMIN'],
            ['Employe', 'employee', 'ROLE_EMPLOYEE'],
            ['Client', 'user', 'ROLE_USER'],
        ];
        $this->em->wrapInTransaction(function () use ($accounts): void {
            foreach ($accounts as [$name, $roleCode, $securityRole]) {
                $role = $this->em->getRepository(Role::class)->findOneBy(['code' => $roleCode]);
                if (!$role instanceof Role) {
                    throw new \RuntimeException('Missing role: '.$roleCode);
                }
                $email = strtolower($name).'@gmail.com';
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                if (!$user instanceof User) {
                    $user = new User($role, $email, '', $name, 'Demo', '', '', '', 'Bordeaux');
                    $this->em->persist($user);
                }
                $user->setRole($role);
                $user->setIsActive(true);
                $user->setPasswordHash($this->hasher->hashPassword($user, $name.'Acount12345!'));
            }
        });

        $this->em->clear();
        $io = new SymfonyStyle($input, $output);
        foreach ($accounts as [$name, $roleCode, $securityRole]) {
            $email = strtolower($name).'@gmail.com';
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user instanceof User || !$user->getIsActive()
                || $user->getRole()->getCode() !== $roleCode
                || !in_array($securityRole, $user->getRoles(), true)
                || !$this->hasher->isPasswordValid($user, $name.'Acount12345!')) {
                throw new \RuntimeException('Account verification failed: '.$email);
            }
            $io->writeln('Verified active account, role and password: '.$email);
        }
        $io->success('All three easy-access accounts are ready.');

        return Command::SUCCESS;
    }
}
