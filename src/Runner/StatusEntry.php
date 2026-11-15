<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

final class StatusEntry
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $fileName,
        public readonly string $status,
        public readonly ?int $exitCode,
        public readonly ?int $durationMs,
        public readonly ?\DateTimeImmutable $startedAt,
        public readonly ?string $stdout,
        public readonly ?string $stderr,
    ) {
    }
}
