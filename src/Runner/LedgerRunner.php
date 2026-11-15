<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\Exception\CommandFailedException;
use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Locator\CommandFileLoaderInterface;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocatorInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepositoryInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;
use ArcaCodicum\CommandLedger\Process\OutputTruncator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LedgerRunner
{
    private readonly LoggerInterface $logger;
    private readonly OutputTruncator $truncator;
    private readonly StaleExecutionPolicy $stalePolicy;

    public function __construct(
        private readonly CommandFileLocatorInterface $locator,
        private readonly CommandFileLoaderInterface $loader,
        private readonly ExecutionRepositoryInterface $repository,
        private readonly int $staleThresholdSeconds,
        ?LoggerInterface $logger = null,
        int $outputLimitBytes = 65536,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->truncator = new OutputTruncator($outputLimitBytes);
        $this->stalePolicy = new StaleExecutionPolicy($staleThresholdSeconds);
    }

    public function migrate(): MigrateReport
    {
        $records = $this->repository->findAll();
        $files = $this->locator->locate();
        $now = new \DateTimeImmutable();

        $filesByVersion = [];
        foreach ($files as $file) {
            $filesByVersion[$file->version()] = $file;
        }

        foreach ($records as $version => $record) {
            if (!isset($filesByVersion[$version])) {
                $this->logger->warning('Command file for executed version {version} is missing.', ['version' => $version]);
            }
        }

        $pending = [];
        $results = [];

        foreach ($files as $file) {
            $version = $file->version();
            $record = $records[$version] ?? null;

            if ($record === null || $record->status === ExecutionStatus::FAILED) {
                $pending[] = $file;
                continue;
            }

            if ($record->status === ExecutionStatus::SUCCESS) {
                $results[] = new MigrateResult($version, $file->fileName(), MigrateResult::SKIPPED);
                continue;
            }

            if ($this->stalePolicy->isStale($record->startedAt, $now)) {
                $pending[] = $file;
                continue;
            }

            $this->logger->warning('Version {version} is already running; skipping.', ['version' => $version]);
            $results[] = new MigrateResult($version, $file->fileName(), MigrateResult::SKIPPED);
        }

        foreach ($pending as $file) {
            $version = $file->version();
            $startedAt = new \DateTimeImmutable();
            $this->repository->markRunning($version, $file->fileName(), $startedAt);
            $this->logger->info('Running command file {file} (version {version}).', [
                'file' => $file->fileName(),
                'version' => $version,
            ]);

            $instance = null;

            try {
                $instance = $this->loader->load($file);
                $instance->up();
            } catch (\Throwable $exception) {
                $finishedAt = new \DateTimeImmutable();
                $durationMs = $this->durationMs($startedAt, $finishedAt);
                [$exitCode, $stdout, $stderr, $fullStdout, $fullStderr] = $this->failureOutput($instance, $exception);
                $stdout = $this->truncator->truncate($stdout);
                $stderr = $this->truncator->truncate($stderr);
                $this->repository->markFailed(
                    $version,
                    $exitCode,
                    $stdout,
                    $stderr,
                    $startedAt,
                    $finishedAt,
                    $durationMs,
                );
                $this->logger->error('Command file {file} failed: {message}', [
                    'version' => $version,
                    'file' => $file->fileName(),
                    'exit_code' => $exitCode,
                    'duration_ms' => $durationMs,
                    'message' => $exception->getMessage(),
                ]);
                $this->logger->debug('Command file {file} output: {stdout}{stderr}', [
                    'file' => $file->fileName(),
                    'stdout' => $fullStdout,
                    'stderr' => $fullStderr,
                ]);
                $results[] = new MigrateResult(
                    $version,
                    $file->fileName(),
                    MigrateResult::FAILED,
                    $exitCode,
                    $exception->getMessage(),
                );

                return new MigrateReport($results);
            }

            $finishedAt = new \DateTimeImmutable();
            $durationMs = $this->durationMs($startedAt, $finishedAt);
            [$exitCode, $stdout, $stderr, $fullStdout, $fullStderr] = $this->successOutput($instance);
            $stdout = $this->truncator->truncate($stdout);
            $stderr = $this->truncator->truncate($stderr);
            $this->repository->markSuccess(
                $version,
                $exitCode,
                $stdout,
                $stderr,
                $startedAt,
                $finishedAt,
                $durationMs,
            );
            $this->logger->info('Command file {file} succeeded.', [
                'version' => $version,
                'file' => $file->fileName(),
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
            ]);
            $this->logger->debug('Command file {file} output: {stdout}{stderr}', [
                'file' => $file->fileName(),
                'stdout' => $fullStdout,
                'stderr' => $fullStderr,
            ]);
            $results[] = new MigrateResult($version, $file->fileName(), MigrateResult::EXECUTED, $exitCode);
        }

        return new MigrateReport($results);
    }

    public function rollback(int $steps = 1, ?string $to = null): RollbackReport
    {
        if ($steps < 1) {
            throw new CommandLedgerException('Steps must be at least 1.');
        }

        $successful = $this->repository->findSuccessful();
        krsort($successful);

        if ($to !== null) {
            if (!isset($successful[$to])) {
                throw new CommandLedgerException(sprintf('Version "%s" is not applied.', $to));
            }

            $targets = [];
            foreach (array_keys($successful) as $version) {
                if (strcmp((string) $version, $to) > 0) {
                    $targets[] = (string) $version;
                }
            }
        } else {
            $targets = array_map('strval', array_slice(array_keys($successful), 0, $steps));
        }

        $filesByVersion = [];
        foreach ($this->locator->locate() as $file) {
            $filesByVersion[$file->version()] = $file;
        }

        foreach ($targets as $version) {
            if (!isset($filesByVersion[$version])) {
                throw new CommandLedgerException(sprintf(
                    'Cannot roll back version "%s": its command file is missing.',
                    $version,
                ));
            }
        }

        $reverted = [];

        foreach ($targets as $version) {
            try {
                $this->loader->load($filesByVersion[$version])->down();
            } catch (\Throwable $exception) {
                $this->logger->error('Rollback of version {version} failed: {message}', [
                    'version' => $version,
                    'message' => $exception->getMessage(),
                ]);

                return new RollbackReport($reverted, $version, $exception->getMessage());
            }

            $this->repository->delete($version);
            $this->logger->info('Rolled back version {version}.', ['version' => $version]);
            $reverted[] = $version;
        }

        return new RollbackReport($reverted);
    }

    /** @return array{0: int, 1: string, 2: string, 3: string, 4: string} */
    private function successOutput(AbstractCommandFile $file): array
    {
        $exitCode = 0;
        $stdout = '';
        $stderr = '';
        $fullStdout = '';
        $fullStderr = '';

        foreach ($file->results() as $result) {
            $exitCode = $result->exitCode;
            $stdout .= $result->stdout;
            $stderr .= $result->stderr;
            $fullStdout .= $result->fullStdout;
            $fullStderr .= $result->fullStderr;
        }

        return [$exitCode, $stdout, $stderr, $fullStdout, $fullStderr];
    }

    /** @return array{0: ?int, 1: string, 2: string, 3: string, 4: string} */
    private function failureOutput(?AbstractCommandFile $file, \Throwable $exception): array
    {
        $exitCode = null;
        $stdout = '';
        $stderr = '';
        $fullStdout = '';
        $fullStderr = '';

        if ($file !== null) {
            foreach ($file->results() as $result) {
                $stdout .= $result->stdout;
                $stderr .= $result->stderr;
                $fullStdout .= $result->fullStdout;
                $fullStderr .= $result->fullStderr;
            }
        }

        if ($exception instanceof CommandFailedException) {
            $exitCode = $exception->exitCode;
            $stderr .= $exception->stderr;
            $fullStderr .= $exception->fullStderr;
            $stdout .= $exception->stdout;
            $fullStdout .= $exception->fullStdout;
        } else {
            $stderr .= $exception->getMessage();
            $fullStderr .= $exception->getMessage();
        }

        return [$exitCode, $stdout, $stderr, $fullStdout, $fullStderr];
    }

    private function durationMs(\DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt): int
    {
        return max(0, (int) round(
            ((float) $finishedAt->format('U.u') - (float) $startedAt->format('U.u')) * 1000,
        ));
    }
}
