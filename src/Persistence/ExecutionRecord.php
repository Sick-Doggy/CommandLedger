<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Persistence;

final class ExecutionRecord
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $version,
        public readonly string $fileName,
        public readonly string $status,
        public readonly ?int $exitCode,
        public readonly ?string $stdout,
        public readonly ?string $stderr,
        public readonly ?\DateTimeImmutable $startedAt,
        public readonly ?\DateTimeImmutable $finishedAt,
        public readonly ?int $durationMs,
    ) {
    }
}
