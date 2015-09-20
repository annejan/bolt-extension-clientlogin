<?php

namespace Bolt\Extension\Bolt\ClientLogin\Nut;

use Bolt\Nut\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Nut command to set/reset a ClientLogin account password.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class PasswordReset extends BaseCommand
{
    /**
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName('clientlogin:reset')
            ->setDescription('Set or reset a ClientLogin account password')
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login name for the account')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for the account')
        ;
    }

    /**
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $login = $input->getOption('login');
        $password = $input->getOption('password');

        $this->auditLog(__CLASS__, 'ClientLogin admin command run');
        $output->writeln("\n<info>ClientLogin admin command run!</info>");
    }
}
