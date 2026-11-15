<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Runner;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\Exception\CommandFailedException;
use ArcaCodicum\CommandLedger\Exception\TableNotInitializedException;
use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocator;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRecord;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;
use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFile;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLoader;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLocator;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryExecutionRepository;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LedgerRunnerMigrateTest extends TestCase
{
    private function descriptor(string $version): CommandFileDescriptor
    {
        return new CommandFileDescriptor(
            '/tmp/'.$version.'.php',
            VersionedFileName::forNew($version, 'test'),
        );
    }

    private function runner(
        array $descriptors,
        array $files,
        InMemoryExecutionRepository $repository,
        int $staleThresholdSeconds = 60,
        ?LoggerInterface $logger = null,
        int $outputLimitBytes = 65536,
    ): LedgerRunner {
        return new LedgerRunner(
            new FakeCommandFileLocator($descriptors),
            new FakeCommandFileLoader($files),
            $repository,
            $staleThresholdSeconds,
            $logger,
            $outputLimitBytes,
        );
    }

    public function testPendingFileIsExecutedAndRecordedAsSuccess(): void
    {
        $repository = new InMemoryExecutionRepository();
        $upCalled = false;
        $file = FakeCommandFile::create(
            function () use (&$upCalled): void {
                $upCalled = true;
            },
            static function (): void {
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository)->migrate();

        self::assertTrue($upCalled);
        self::assertSame(1, $report->executed());
        self::assertFalse($report->hasFailed());

        $record = $repository->records()['20260101120000'];
        self::assertSame(ExecutionStatus::SUCCESS, $record->status);
        self::assertSame(0, $record->exitCode);
        self::assertNotNull($record->startedAt);
        self::assertNotNull($record->finishedAt);
        self::assertNotNull($record->durationMs);
    }

    public function testSuccessfulVersionIsSkippedOnSecondRun(): void
    {
        $repository = new InMemoryExecutionRepository();
        $startedAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $repository->seed(new ExecutionRecord(1, '20260101120000', 'old.php', ExecutionStatus::SUCCESS, 0, '', '', $startedAt, $startedAt, 0));

        $report = $this->runner(
            [$this->descriptor('20260101120000')],
            [],
            $repository,
        )->migrate();

        self::assertSame(0, $report->executed());
        self::assertSame(1, $report->skipped());
    }

    public function testFailedVersionIsRetriedOnNextRun(): void
    {
        $repository = new InMemoryExecutionRepository();
        $startedAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $repository->seed(new ExecutionRecord(1, '20260101120000', 'old.php', ExecutionStatus::FAILED, 3, '', 'boom', $startedAt, $startedAt, 0));
        $upCalled = false;
        $file = FakeCommandFile::create(
            function () use (&$upCalled): void {
                $upCalled = true;
            },
            static function (): void {
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository)->migrate();

        self::assertTrue($upCalled);
        self::assertSame(1, $report->executed());
        self::assertSame(ExecutionStatus::SUCCESS, $repository->records()['20260101120000']->status);
    }

    public function testFailureStopsRunAndLeavesLaterFilesUnprocessed(): void
    {
        $repository = new InMemoryExecutionRepository();
        $secondCalled = false;
        $failing = FakeCommandFile::create(
            static function (): void {
                throw new CommandFailedException('test:fail', 3, 'boom');
            },
            static function (): void {
            },
        );
        $second = FakeCommandFile::create(
            function () use (&$secondCalled): void {
                $secondCalled = true;
            },
            static function (): void {
            },
        );

        $report = $this->runner(
            [$this->descriptor('20260101120000'), $this->descriptor('20260101120001')],
            ['20260101120000' => $failing, '20260101120001' => $second],
            $repository,
        )->migrate();

        self::assertTrue($report->hasFailed());
        self::assertFalse($secondCalled);
        self::assertSame(3, $report->failed()?->exitCode);

        $record = $repository->records()['20260101120000'];
        self::assertSame(ExecutionStatus::FAILED, $record->status);
        self::assertSame(3, $record->exitCode);
        self::assertStringContainsString('boom', (string) $record->stderr);
        self::assertArrayNotHasKey('20260101120001', $repository->records());
    }

    public function testUserExceptionIsRecordedAsFailureWithMessage(): void
    {
        $repository = new InMemoryExecutionRepository();
        $file = FakeCommandFile::create(
            static function (): void {
                throw new \RuntimeException('user failure');
            },
            static function (): void {
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository)->migrate();

        self::assertTrue($report->hasFailed());
        $record = $repository->records()['20260101120000'];
        self::assertSame(ExecutionStatus::FAILED, $record->status);
        self::assertNull($record->exitCode);
        self::assertStringContainsString('user failure', (string) $record->stderr);
    }

    public function testStaleRunningEntryIsRetried(): void
    {
        $repository = new InMemoryExecutionRepository();
        $stale = (new \DateTimeImmutable())->modify('-2 hours');
        $repository->seed(new ExecutionRecord(1, '20260101120000', 'old.php', ExecutionStatus::RUNNING, null, null, null, $stale, null, null));
        $upCalled = false;
        $file = FakeCommandFile::create(
            function () use (&$upCalled): void {
                $upCalled = true;
            },
            static function (): void {
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository, 60)->migrate();

        self::assertTrue($upCalled);
        self::assertSame(1, $report->executed());
        self::assertSame(ExecutionStatus::SUCCESS, $repository->records()['20260101120000']->status);
    }

    public function testFreshRunningEntryIsSkipped(): void
    {
        $repository = new InMemoryExecutionRepository();
        $repository->seed(new ExecutionRecord(1, '20260101120000', 'old.php', ExecutionStatus::RUNNING, null, null, null, new \DateTimeImmutable(), null, null));
        $upCalled = false;
        $file = FakeCommandFile::create(
            function () use (&$upCalled): void {
                $upCalled = true;
            },
            static function (): void {
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository, 60)->migrate();

        self::assertFalse($upCalled);
        self::assertSame(0, $report->executed());
        self::assertSame(1, $report->skipped());
    }

    public function testFailedCommandStdoutIsPersisted(): void
    {
        $repository = new InMemoryExecutionRepository();
        $file = FakeCommandFile::create(
            static function (): void {
                throw new CommandFailedException('test:fail', 3, 'boom', null, "partial\n", "full partial\n");
            },
            static function (): void {
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository)->migrate();

        self::assertTrue($report->hasFailed());
        $record = $repository->records()['20260101120000'];
        self::assertSame(ExecutionStatus::FAILED, $record->status);
        self::assertSame("partial\n", $record->stdout);
    }

    public function testLoaderFailureIsRecordedAsFailed(): void
    {
        $repository = new InMemoryExecutionRepository();

        $report = $this->runner([$this->descriptor('20260101120000')], [], $repository)->migrate();

        self::assertTrue($report->hasFailed());
        self::assertStringContainsString(
            'No fake command file',
            (string) $repository->records()['20260101120000']->stderr,
        );
    }

    public function testTableCheckHappensBeforeDirectoryScan(): void
    {
        $runner = new LedgerRunner(
            new CommandFileLocator('/definitely/missing-command-directory'),
            new FakeCommandFileLoader([]),
            new InMemoryExecutionRepository(false),
            60,
        );

        $this->expectException(TableNotInitializedException::class);

        $runner->migrate();
    }

    public function testAggregatedOutputIsTruncatedToConfiguredLimit(): void
    {
        $repository = new InMemoryExecutionRepository();
        $processRunner = new ProcessRunner(
            __DIR__.'/../../Fixtures/console.php',
            PHP_BINARY,
            5,
            1000,
            'test',
            false,
        );
        $file = new class ($processRunner) extends AbstractCommandFile {
            public function up(): void
            {
                $this->execute('test:long-output');
                $this->execute('test:long-output');
                $this->execute('test:long-output');
            }

            public function down(): void
            {
            }
        };

        $this->runner(
            [$this->descriptor('20260101120000')],
            ['20260101120000' => $file],
            $repository,
            60,
            null,
            20,
        )->migrate();

        $stdout = (string) $repository->records()['20260101120000']->stdout;

        self::assertStringStartsWith("[truncated]\n", $stdout);
        self::assertLessThanOrEqual(strlen("[truncated]\n") + 20, strlen($stdout));
    }

    public function testInfoLogsContainVersionExitCodeAndDuration(): void
    {
        $repository = new InMemoryExecutionRepository();
        $logger = new InMemoryLogger();
        $file = FakeCommandFile::create(static function (): void {
        }, static function (): void {
        });

        $this->runner(
            [$this->descriptor('20260101120000')],
            ['20260101120000' => $file],
            $repository,
            60,
            $logger,
        )->migrate();

        $started = $this->loggerRecord($logger, 'Running command file');
        self::assertSame('20260101120000', $started['context']['version']);

        $succeeded = $this->loggerRecord($logger, 'succeeded');
        self::assertSame('20260101120000', $succeeded['context']['version']);
        self::assertSame(0, $succeeded['context']['exit_code']);
        self::assertArrayHasKey('duration_ms', $succeeded['context']);
    }

    public function testFailureLogsContainVersionExitCodeAndDuration(): void
    {
        $repository = new InMemoryExecutionRepository();
        $logger = new InMemoryLogger();
        $file = FakeCommandFile::create(
            static function (): void {
                throw new \RuntimeException('boom');
            },
            static function (): void {
            },
        );

        $this->runner(
            [$this->descriptor('20260101120000')],
            ['20260101120000' => $file],
            $repository,
            60,
            $logger,
        )->migrate();

        $failed = $this->loggerRecord($logger, 'failed');
        self::assertSame('20260101120000', $failed['context']['version']);
        self::assertArrayHasKey('exit_code', $failed['context']);
        self::assertArrayHasKey('duration_ms', $failed['context']);
    }

    /** @return array{level: mixed, message: string, context: array<string, mixed>} */
    private function loggerRecord(InMemoryLogger $logger, string $fragment): array
    {
        foreach ($logger->records as $record) {
            if (str_contains($record['message'], $fragment)) {
                return $record;
            }
        }

        self::fail('No log record containing "'.$fragment.'".');
    }
}
