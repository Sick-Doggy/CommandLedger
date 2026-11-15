<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

use ArcaCodicum\CommandLedger\Locator\CommandFileLocatorInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRecord;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepositoryInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;

final class StatusBuilder
{
    private readonly StaleExecutionPolicy $stalePolicy;

    public function __construct(
        private readonly CommandFileLocatorInterface $locator,
        private readonly ExecutionRepositoryInterface $repository,
        int $staleThresholdSeconds,
    ) {
        $this->stalePolicy = new StaleExecutionPolicy($staleThresholdSeconds);
    }

    /** @return list<StatusEntry> */
    public function build(): array
    {
        $files = $this->locator->locate();
        $records = $this->repository->findAll();
        $now = new \DateTimeImmutable();

        $filesByVersion = [];
        foreach ($files as $file) {
            $filesByVersion[$file->version()] = $file;
        }

        $versions = array_unique(array_merge(array_keys($filesByVersion), array_keys($records)));
        sort($versions);

        $entries = [];
        foreach ($versions as $version) {
            $version = (string) $version;
            $file = $filesByVersion[$version] ?? null;
            $record = $records[$version] ?? null;

            if ($file === null && $record !== null) {
                $entries[] = $this->entry($record, LedgerStatus::MISSING);
                continue;
            }

            if ($file === null) {
                continue;
            }

            if ($record === null) {
                $entries[] = new StatusEntry($version, $file->fileName(), LedgerStatus::PENDING, null, null, null, null, null);
                continue;
            }

            $entries[] = $this->entry($record, $this->statusFor($record, $now));
        }

        return $entries;
    }

    private function statusFor(ExecutionRecord $record, \DateTimeImmutable $now): string
    {
        if ($record->status === ExecutionStatus::SUCCESS) {
            return LedgerStatus::SUCCESS;
        }

        if ($record->status === ExecutionStatus::FAILED) {
            return LedgerStatus::FAILED;
        }

        return $this->stalePolicy->isStale($record->startedAt, $now)
            ? LedgerStatus::STALE_RUNNING
            : LedgerStatus::RUNNING;
    }

    private function entry(ExecutionRecord $record, string $status): StatusEntry
    {
        return new StatusEntry(
            $record->version,
            $record->fileName,
            $status,
            $record->exitCode,
            $record->durationMs,
            $record->startedAt,
            $record->stdout,
            $record->stderr,
        );
    }
}
