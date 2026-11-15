<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Fake;

use ArcaCodicum\CommandLedger\Exception\TableNotInitializedException;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRecord;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepositoryInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;

final class InMemoryExecutionRepository implements ExecutionRepositoryInterface
{
    /** @var array<string, ExecutionRecord> */
    private array $records = [];

    public function __construct(private bool $tableExists = true)
    {
    }

    public function seed(ExecutionRecord $record): void
    {
        $this->records[$record->version] = $record;
    }

    /** @return array<string, ExecutionRecord> */
    public function records(): array
    {
        return $this->records;
    }

    public function findAll(): array
    {
        if (!$this->tableExists) {
            throw new TableNotInitializedException('Table does not exist.');
        }

        ksort($this->records);

        return $this->records;
    }

    public function findSuccessful(): array
    {
        return array_filter(
            $this->findAll(),
            static fn (ExecutionRecord $record): bool => $record->status === ExecutionStatus::SUCCESS,
        );
    }

    public function markRunning(string $version, string $fileName, \DateTimeImmutable $startedAt): void
    {
        $existing = $this->records[$version] ?? null;
        $this->records[$version] = new ExecutionRecord(
            $existing?->id ?? count($this->records) + 1,
            $version,
            $fileName,
            ExecutionStatus::RUNNING,
            null,
            null,
            null,
            $startedAt,
            null,
            null,
        );
    }

    public function markSuccess(
        string $version,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void {
        $this->write($version, ExecutionStatus::SUCCESS, $exitCode, $stdout, $stderr, $startedAt, $finishedAt, $durationMs);
    }

    public function markFailed(
        string $version,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void {
        $this->write($version, ExecutionStatus::FAILED, $exitCode, $stdout, $stderr, $startedAt, $finishedAt, $durationMs);
    }

    public function delete(string $version): void
    {
        unset($this->records[$version]);
    }

    public function tableExists(): bool
    {
        return $this->tableExists;
    }

    public function createTable(): void
    {
        $this->tableExists = true;
    }

    private function write(
        string $version,
        string $status,
        ?int $exitCode,
        string $stdout,
        string $stderr,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
        int $durationMs,
    ): void {
        $existing = $this->records[$version] ?? null;
        $this->records[$version] = new ExecutionRecord(
            $existing?->id ?? count($this->records) + 1,
            $version,
            $existing?->fileName ?? '',
            $status,
            $exitCode,
            $stdout,
            $stderr,
            $startedAt,
            $finishedAt,
            $durationMs,
        );
    }
}
