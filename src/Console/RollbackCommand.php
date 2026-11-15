<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Console;

use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RollbackCommand extends Command
{
    public function __construct(private readonly LedgerRunner $runner)
    {
        parent::__construct('command-ledger:rollback');
    }

    protected function configure(): void
    {
        $this->setDescription('Run down() for executed command files and remove their records.');
        $this->addOption('steps', null, InputOption::VALUE_REQUIRED, 'Number of latest applied files to roll back.');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Roll back all versions newer than this applied version.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stepsOption = $input->getOption('steps');
        $to = $input->getOption('to');

        if ($stepsOption !== null && $to !== null) {
            $io->error('Use either --steps or --to, not both.');

            return self::FAILURE;
        }

        $steps = 1;

        if ($stepsOption !== null) {
            if (!is_string($stepsOption) || preg_match('/^\d+$/', $stepsOption) !== 1 || (int) $stepsOption < 1) {
                $io->error('Option --steps must be a positive integer.');

                return self::FAILURE;
            }

            $steps = (int) $stepsOption;
        }

        try {
            $report = $this->runner->rollback($steps, is_string($to) ? $to : null);
        } catch (CommandLedgerException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($report->reverted as $version) {
            $io->writeln(sprintf('<info>REVERTED</info> %s', $version));
        }

        if ($report->hasFailed()) {
            $io->error(sprintf('Rollback of %s failed: %s', $report->failedVersion, $report->error));

            return self::FAILURE;
        }

        if ($report->reverted === []) {
            $io->info('Nothing to roll back.');

            return self::SUCCESS;
        }

        $io->success(sprintf('%d rolled back.', count($report->reverted)));

        return self::SUCCESS;
    }
}
