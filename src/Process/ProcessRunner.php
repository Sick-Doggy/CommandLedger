<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Process;

use ArcaCodicum\CommandLedger\CommandResult;
use ArcaCodicum\CommandLedger\Exception\CommandFailedException;
use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class ProcessRunner
{
    private readonly OutputTruncator $truncator;

    public function __construct(
        private readonly string $consolePath,
        private readonly ?string $phpBinary,
        private readonly int $timeoutSeconds,
        int $outputLimitBytes,
        private readonly string $environment,
        private readonly bool $debug,
    ) {
        $this->truncator = new OutputTruncator($outputLimitBytes);
    }

    /**
     * @param array<array-key, scalar> $arguments
     * @param array<string, scalar|array<array-key, scalar>|null> $options
     */
    public function execute(string $command, array $arguments = [], array $options = []): CommandResult
    {
        return $this->run($this->buildArgv($command, $arguments, $options), $command);
    }

    /** @param array<array-key, scalar> $arguments */
    public function executeScript(string $scriptPath, array $arguments = []): CommandResult
    {
        if (!is_file($scriptPath) || !is_readable($scriptPath)) {
            throw new CommandLedgerException(sprintf(
                'Script "%s" does not exist or is not readable.',
                $scriptPath,
            ));
        }

        $argv = ['bash', $scriptPath];

        foreach ($arguments as $argument) {
            $argv[] = (string) $argument;
        }

        return $this->run($argv, $scriptPath);
    }

    public function executeBash(string $command): CommandResult
    {
        return $this->run(['bash', '-c', $command], $command);
    }

    /** @param list<string> $argv */
    private function run(array $argv, string $label): CommandResult
    {
        $process = new Process($argv);
        $process->setTimeout($this->timeoutSeconds > 0 ? (float) $this->timeoutSeconds : null);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $fullStdout = $process->getOutput();
            $fullStderr = $process->getErrorOutput();
            $timeoutMessage = sprintf('Command "%s" timed out after %d seconds.', $label, $this->timeoutSeconds);
            $fullError = trim($fullStderr) !== '' ? $fullStderr."\n".$timeoutMessage : $timeoutMessage;

            throw new CommandFailedException(
                $label,
                -1,
                $this->truncator->truncate($fullError),
                $fullError,
                $this->truncator->truncate($fullStdout),
                $fullStdout,
            );
        }

        $fullStdout = $process->getOutput();
        $fullStderr = $process->getErrorOutput();
        $stdout = $this->truncator->truncate($fullStdout);
        $stderr = $this->truncator->truncate($fullStderr);

        if (!$process->isSuccessful()) {
            throw new CommandFailedException($label, $process->getExitCode() ?? -1, $stderr, $fullStderr, $stdout, $fullStdout);
        }

        return new CommandResult($label, $process->getExitCode() ?? 0, $stdout, $stderr, $fullStdout, $fullStderr);
    }

    /**
     * @param array<array-key, scalar> $arguments
     * @param array<string, scalar|array<array-key, scalar>|null> $options
     * @return list<string>
     */
    private function buildArgv(string $command, array $arguments, array $options): array
    {
        $argv = [$this->phpBinary ?? PHP_BINARY, $this->consolePath, $command];

        foreach ($arguments as $argument) {
            $argv[] = (string) $argument;
        }

        foreach ($options as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $option = '--'.ltrim((string) $name, '-');

            if ($value === true) {
                $argv[] = $option;
                continue;
            }

            foreach ((array) $value as $item) {
                $argv[] = $option.'='.(string) $item;
            }
        }

        $argv[] = '--no-interaction';
        $argv[] = '--env='.$this->environment;

        if (!$this->debug) {
            $argv[] = '--no-debug';
        }

        return $argv;
    }
}
