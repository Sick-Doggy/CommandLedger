<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Persistence;

interface ExecutionRepositoryInterface
{
    /** @return array<string, ExecutionRecord> */
    public function findAll(): array;

    /** @return array<string, ExecutionRecord> */
    public function findSuccessful(): array;

    public function markRunning(string $version, string $fileName, \DateTimeImmutable $startedAt): void;

    public function markSuccess(
        string $version,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void;

    public function markFailed(
        string $version,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void;

    public function delete(string $version): void;

    public function tableExists(): bool;

    public function createTable(): void;
}
