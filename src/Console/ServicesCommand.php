<?php

namespace ByJG\Scriptify\Console;

use ByJG\Scriptify\Scriptify;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServicesCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('services')
            ->setDescription('List all services installed by scriptify')
            ->addOption(
                'only-names',
                null,
                InputOption::VALUE_NONE,
                'List only the services names without header'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $list = Scriptify::listServices();



        if ($input->getOption('only-names')) {
            $output->writeln($list);
            return 0;
        }

        $output->writeln("");
        if (count($list) == 0) {
            $output->writeln("There is no scriptify services installed.");
        } else {
            $output->writeln("List of scriptify services: ");
            foreach ($list as $filename) {
                $output->writeln(" - " . basename($filename));
            }
        }
        $output->writeln("");

        return 0;
    }
}
