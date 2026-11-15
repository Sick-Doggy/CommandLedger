<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

final class MigrateResult
{
    public const EXECUTED = 'executed';
    public const SKIPPED = 'skipped';
    public const FAILED = 'failed';

    public function __construct(
        public readonly string $version,
        public readonly string $fileName,
        public readonly string $status,
        public readonly ?int $exitCode = null,
        public readonly ?string $error = null,
    ) {
    }
}
