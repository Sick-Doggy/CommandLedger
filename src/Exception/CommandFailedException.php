<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Exception;

final class CommandFailedException extends CommandLedgerException
{
    public readonly string $fullStderr;
    public readonly string $fullStdout;

    public function __construct(
        public readonly string $command,
        public readonly int $exitCode,
        public readonly string $stderr,
        ?string $fullStderr = null,
        public readonly string $stdout = '',
        ?string $fullStdout = null,
    ) {
        $this->fullStderr = $fullStderr ?? $stderr;
        $this->fullStdout = $fullStdout ?? $stdout;

        parent::__construct(sprintf(
            'Command "%s" failed with exit code %d: %s',
            $command,
            $exitCode,
            trim($stderr),
        ));
    }
}
