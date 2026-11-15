<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger;

final class CommandResult
{
    public function __construct(
        public readonly string $command,
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly string $fullStdout,
        public readonly string $fullStderr,
    ) {
    }
}
