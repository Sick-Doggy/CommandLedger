<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Runner;

use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRecord;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;
use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFile;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLoader;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLocator;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryExecutionRepository;
use PHPUnit\Framework\TestCase;

final class LedgerRunnerRollbackTest extends TestCase
{
    private function descriptor(string $version): CommandFileDescriptor
    {
        return new CommandFileDescriptor('/tmp/'.$version.'.php', VersionedFileName::forNew($version, 'test'));
    }

    private function seedSuccess(InMemoryExecutionRepository $repository, string $version): void
    {
        $at = new \DateTimeImmutable('2026-01-01 12:00:00');
        $repository->seed(new ExecutionRecord(1, $version, $version.'_test.php', ExecutionStatus::SUCCESS, 0, '', '', $at, $at, 0));
    }

    private function runner(array $descriptors, array $files, InMemoryExecutionRepository $repository): LedgerRunner
    {
        return new LedgerRunner(
            new FakeCommandFileLocator($descriptors),
            new FakeCommandFileLoader($files),
            $repository,
            60,
        );
    }

    public function testRollsBackLatestSuccessAndDeletesRecord(): void
    {
        $repository = new InMemoryExecutionRepository();
        $this->seedSuccess($repository, '20260101120000');
        $this->seedSuccess($repository, '20260101120001');
        $downCalled = false;
        $file = FakeCommandFile::create(
            static function (): void {
            },
            function () use (&$downCalled): void {
                $downCalled = true;
            },
        );

        $report = $this->runner(
            [$this->descriptor('20260101120000'), $this->descriptor('20260101120001')],
            ['20260101120001' => $file],
            $repository,
        )->rollback(1);

        self::assertTrue($downCalled);
        self::assertSame(['20260101120001'], $report->reverted);
        self::assertArrayNotHasKey('20260101120001', $repository->records());
        self::assertArrayHasKey('20260101120000', $repository->records());
    }

    public function testRollsBackRequestedNumberOfSteps(): void
    {
        $repository = new InMemoryExecutionRepository();
        $this->seedSuccess($repository, '20260101120000');
        $this->seedSuccess($repository, '20260101120001');
        $this->seedSuccess($repository, '20260101120002');
        $files = [
            '20260101120002' => FakeCommandFile::create(static function (): void {
            }, static function (): void {
            }),
            '20260101120001' => FakeCommandFile::create(static function (): void {
            }, static function (): void {
            }),
        ];

        $report = $this->runner(
            [$this->descriptor('20260101120000'), $this->descriptor('20260101120001'), $this->descriptor('20260101120002')],
            $files,
            $repository,
        )->rollback(2);

        self::assertSame(['20260101120002', '20260101120001'], $report->reverted);
        self::assertSame(['20260101120000'], array_map('strval', array_keys($repository->records())));
    }

    public function testToRevertsOnlyVersionsNewerThanTarget(): void
    {
        $repository = new InMemoryExecutionRepository();
        $this->seedSuccess($repository, '20260101120000');
        $this->seedSuccess($repository, '20260101120001');
        $this->seedSuccess($repository, '20260101120002');
        $files = [
            '20260101120002' => FakeCommandFile::create(static function (): void {
            }, static function (): void {
            }),
            '20260101120001' => FakeCommandFile::create(static function (): void {
            }, static function (): void {
            }),
        ];

        $report = $this->runner(
            [$this->descriptor('20260101120000'), $this->descriptor('20260101120001'), $this->descriptor('20260101120002')],
            $files,
            $repository,
        )->rollback(1, '20260101120000');

        self::assertSame(['20260101120002', '20260101120001'], $report->reverted);
        self::assertSame(['20260101120000'], array_map('strval', array_keys($repository->records())));
    }

    public function testFailedDownLeavesRecordUntouched(): void
    {
        $repository = new InMemoryExecutionRepository();
        $this->seedSuccess($repository, '20260101120000');
        $file = FakeCommandFile::create(
            static function (): void {
            },
            static function (): void {
                throw new \RuntimeException('down failed');
            },
        );

        $report = $this->runner([$this->descriptor('20260101120000')], ['20260101120000' => $file], $repository)->rollback(1);

        self::assertTrue($report->hasFailed());
        self::assertSame('20260101120000', $report->failedVersion);
        self::assertSame('down failed', $report->error);
        self::assertArrayHasKey('20260101120000', $repository->records());
    }

    public function testMissingFileThrowsBeforeTouchingRecords(): void
    {
        $repository = new InMemoryExecutionRepository();
        $this->seedSuccess($repository, '20260101120000');

        $this->expectException(CommandLedgerException::class);
        $this->expectExceptionMessage('file is missing');

        $this->runner([], [], $repository)->rollback(1);
    }

    public function testUnknownTargetVersionThrows(): void
    {
        $repository = new InMemoryExecutionRepository();
        $this->seedSuccess($repository, '20260101120000');

        $this->expectException(CommandLedgerException::class);
        $this->expectExceptionMessage('is not applied');

        $this->runner([$this->descriptor('20260101120000')], [], $repository)->rollback(1, '20260101999999');
    }
}
