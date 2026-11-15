<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Console;

use ArcaCodicum\CommandLedger\Runner\LedgerStatus;
use ArcaCodicum\CommandLedger\Runner\StatusBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class StatusCommand extends Command
{
    public function __construct(private readonly StatusBuilder $builder)
    {
        parent::__construct('command-ledger:status');
    }

    protected function configure(): void
    {
        $this->setDescription('Show the status of command files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entries = $this->builder->build();

        if ($entries === []) {
            $io->info('No command files found.');

            return self::SUCCESS;
        }

        $rows = [];
        $counts = [];

        foreach ($entries as $entry) {
            $counts[$entry->status] = ($counts[$entry->status] ?? 0) + 1;
            $rows[] = [
                $entry->version,
                $entry->fileName ?? '-',
                $entry->status,
                $entry->exitCode ?? '-',
                $entry->durationMs !== null ? $entry->durationMs.' ms' : '-',
                $entry->startedAt?->format('Y-m-d H:i:s') ?? '-',
            ];
        }

        $io->table(['Version', 'File', 'Status', 'Exit', 'Duration', 'Started'], $rows);

        if ($output->isVerbose()) {
            foreach ($entries as $entry) {
                if ($entry->status !== LedgerStatus::FAILED) {
                    continue;
                }

                $io->section('Output for '.$entry->version);

                if ($entry->stdout !== null && $entry->stdout !== '') {
                    $io->writeln('<comment>stdout</comment>');
                    $io->writeln($entry->stdout, OutputInterface::OUTPUT_RAW);
                }

                if ($entry->stderr !== null && $entry->stderr !== '') {
                    $io->writeln('<comment>stderr</comment>');
                    $io->writeln($entry->stderr, OutputInterface::OUTPUT_RAW);
                }
            }
        }

        $summary = [];
        foreach ($counts as $status => $count) {
            $summary[] = sprintf('%d %s', $count, $status);
        }

        $io->writeln(implode(', ', $summary).'.');

        return self::SUCCESS;
    }
}
