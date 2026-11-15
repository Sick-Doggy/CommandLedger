<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger;

use ArcaCodicum\CommandLedger\Process\ProcessRunner;

abstract class AbstractCommandFile implements CommandFileInterface
{
    /** @var list<CommandResult> */
    private array $results = [];

    final public function __construct(private readonly ProcessRunner $runner)
    {
    }

    /**
     * @param array<array-key, scalar> $arguments
     * @param array<string, scalar|array<array-key, scalar>|null> $options
     */
    final protected function execute(string $command, array $arguments = [], array $options = []): CommandResult
    {
        return $this->record($this->runner->execute($command, $arguments, $options));
    }

    /** @param array<array-key, scalar> $arguments */
    final protected function executeScript(string $scriptPath, array $arguments = []): CommandResult
    {
        return $this->record($this->runner->executeScript($scriptPath, $arguments));
    }

    final protected function executeBash(string $command): CommandResult
    {
        return $this->record($this->runner->executeBash($command));
    }

    /** @return list<CommandResult> */
    final public function results(): array
    {
        return $this->results;
    }

    private function record(CommandResult $result): CommandResult
    {
        $this->results[] = $result;

        return $result;
    }
}
