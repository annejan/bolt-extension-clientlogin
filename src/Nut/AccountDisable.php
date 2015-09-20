<?php

namespace Bolt\Extension\Bolt\ClientLogin\Nut;

use Bolt\Nut\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Nut command to disable a ClientLogin account.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AccountDisable extends BaseCommand
{
    /**
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
        ->setName('clientlogin:disable')
        ->setDescription('Disable a ClientLogin account')
        ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login name for the account')
        ;
    }

    /**
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $login = $input->getOption('login');

        $this->auditLog(__CLASS__, 'ClientLogin admin command run');
        $output->writeln("\n<info>ClientLogin admin command run!</info>");
    }
}
