<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Console;

use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use ArcaCodicum\CommandLedger\Runner\MigrateResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrateCommand extends Command
{
    public function __construct(private readonly LedgerRunner $runner)
    {
        parent::__construct('command-ledger:migrate');
    }

    protected function configure(): void
    {
        $this->setDescription('Execute pending command files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = $this->runner->migrate();

        foreach ($report->results as $result) {
            $label = sprintf('%s %s', $result->version, $result->fileName);

            if ($result->status === MigrateResult::EXECUTED) {
                $io->writeln(sprintf('<info>EXECUTED</info> %s', $label));
            } elseif ($result->status === MigrateResult::SKIPPED) {
                $io->writeln(sprintf('<comment>SKIPPED</comment> %s', $label));
            } else {
                $io->writeln(sprintf('<error>FAILED</error> %s', $label));

                if ($result->error !== null) {
                    $io->writeln($result->error, OutputInterface::OUTPUT_RAW);
                }
            }
        }

        if ($report->hasFailed()) {
            $io->error('Execution stopped due to a failure.');

            return self::FAILURE;
        }

        $io->success(sprintf('%d executed, %d skipped.', $report->executed(), $report->skipped()));

        return self::SUCCESS;
    }
}
