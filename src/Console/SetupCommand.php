<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Console;

use ArcaCodicum\CommandLedger\Persistence\ExecutionRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SetupCommand extends Command
{
    public function __construct(private readonly ExecutionRepositoryInterface $repository)
    {
        parent::__construct('command-ledger:setup');
    }

    protected function configure(): void
    {
        $this->setDescription('Create the command ledger table if it does not exist.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->repository->tableExists()) {
            $io->success('Table already exists.');

            return self::SUCCESS;
        }

        $this->repository->createTable();
        $io->success('Table created.');

        return self::SUCCESS;
    }
}
