<?php

namespace Bolt\Extension\Bolt\ClientLogin\Nut;

use Bolt\Nut\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Nut command to create a ClientLogin account.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AccountCreate extends BaseCommand
{
    /**
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName('clientlogin:create')
            ->setDescription('Create a ClientLogin account')
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login name for the account')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for the account')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'A valid email address')
        ;
    }

    /**
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $login = $input->getOption('login');
        $password = $input->getOption('password');
        $email = $input->getOption('email');

        $this->auditLog(__CLASS__, 'ClientLogin admin command run');
        $output->writeln("\n<info>ClientLogin admin command run!</info>");
    }
}
