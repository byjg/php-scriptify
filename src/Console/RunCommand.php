<?php

namespace ByJG\Scriptify\Console;

use ByJG\Scriptify\Runner;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription('Run a PHP class without become a daemon')
            ->addArgument(
                'classname',
                InputArgument::REQUIRED,
                'The PHP class and method like ClassName::Method'
            )
            ->addOption(
                'bootstrap',
                'b',
                InputOption::VALUE_OPTIONAL,
                'The relative path from root directory for the bootstrap file, like ./vendor/autoload.php',
                'vendor/autoload.php'
            )
            ->addOption(
                'rootdir',
                'r',
                InputOption::VALUE_OPTIONAL,
                'The root path where your application is installed',
                getcwd()
            )
            ->addOption(
                '--arg',
                "a",
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'is an optional arguments for your class',
                []
            )
            ->addOption(
                'daemon',
                'd',
                InputOption::VALUE_NONE,
                'Run as a daemon'
            )
            ->addOption(
                'showdocs',
                's',
                InputOption::VALUE_NONE,
                'Show docs and exit'
            );

    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $className = $input->getArgument('classname');
        $rootPath = $input->getOption('rootdir');
        $bootstrap = $rootPath . "/" . $input->getOption('bootstrap');

        if (!file_exists($rootPath)) {
            throw new \Exception("The rootpath '$bootstrap' does not exists. Use absolute path or relative path from current directory.");
        }

        if (!file_exists($bootstrap)) {
            throw new \Exception("The bootstrap file '$bootstrap' does not exists. Use a relative path from the root path.");
        }

        chdir($rootPath);

        // A bootstrap file that RETURNS a PSR-11 container hands it to Scriptify;
        // that is the whole protocol. The default bootstrap (vendor/autoload.php)
        // returns Composer's ClassLoader, which is not a container, so nothing
        // changes for anyone who does not opt in.
        $loaded = require_once $bootstrap;
        $container = $loaded instanceof ContainerInterface ? $loaded : null;

        $runner = new Runner(
            $className,
            $input->getOption("arg"),
            $input->getOption('daemon'),
            $container
        );

        if ($input->getOption('showdocs')) {
            $runner->showDocs();
        } else {
            $runner->execute();
        }

        return 0;
    }
}
