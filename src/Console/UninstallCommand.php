<?php

namespace ByJG\Scriptify\Console;

use ByJG\Scriptify\Scriptify;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UninstallCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('uninstall')
            ->setDescription('Uninstall the Linux service previously installed by scriptify')
            ->addArgument(
                'servicename',
                InputArgument::REQUIRED,
                'The unix service name.'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceName = $input->getArgument('servicename');
        Scriptify::uninstall($serviceName);
        $output->writeln('Service uninstalled. Maybe the service still running. ');

        return 0;
    }
}
