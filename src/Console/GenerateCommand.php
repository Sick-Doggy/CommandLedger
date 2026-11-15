<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Console;

use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Generator\CommandFileGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GenerateCommand extends Command
{
    public function __construct(private readonly CommandFileGenerator $generator)
    {
        parent::__construct('command-ledger:generate');
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a new command file with the current timestamp.');
        $this->addArgument('name', InputArgument::OPTIONAL, 'File name, e.g. import_products.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if ($name === null) {
            if (!$input->isInteractive()) {
                $io->error('The name argument is required in non-interactive mode.');

                return self::FAILURE;
            }

            $name = $io->ask('Name for the command file (e.g. import_products)');
        }

        if (!is_string($name) || trim($name) === '') {
            $io->error('A non-empty name is required.');

            return self::FAILURE;
        }

        try {
            $path = $this->generator->generate($name);
        } catch (CommandLedgerException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $io->success('Created '.$path);

        return self::SUCCESS;
    }
}
