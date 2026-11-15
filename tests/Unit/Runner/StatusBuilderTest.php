<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Runner;

use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRecord;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;
use ArcaCodicum\CommandLedger\Runner\LedgerStatus;
use ArcaCodicum\CommandLedger\Runner\StatusBuilder;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLocator;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryExecutionRepository;
use PHPUnit\Framework\TestCase;

final class StatusBuilderTest extends TestCase
{
    private function descriptor(string $version): CommandFileDescriptor
    {
        return new CommandFileDescriptor('/tmp/'.$version.'.php', VersionedFileName::forNew($version, 'test'));
    }

    private function record(
        string $version,
        string $status,
        ?\DateTimeImmutable $startedAt = null,
        ?string $stderr = null,
    ): ExecutionRecord {
        $startedAt ??= new \DateTimeImmutable();

        return new ExecutionRecord(1, $version, $version.'_test.php', $status, 0, '', $stderr, $startedAt, $startedAt, 1);
    }

    public function testBuildsAllStatuses(): void
    {
        $repository = new InMemoryExecutionRepository();
        $repository->seed($this->record('20260101120001', ExecutionStatus::SUCCESS));
        $repository->seed($this->record('20260101120002', ExecutionStatus::FAILED, stderr: 'boom'));
        $repository->seed($this->record('20260101120003', ExecutionStatus::RUNNING));
        $repository->seed($this->record('20260101120004', ExecutionStatus::RUNNING, (new \DateTimeImmutable())->modify('-2 hours')));
        $repository->seed($this->record('20260101120005', ExecutionStatus::SUCCESS));

        $locator = new FakeCommandFileLocator([
            $this->descriptor('20260101120000'),
            $this->descriptor('20260101120001'),
            $this->descriptor('20260101120002'),
            $this->descriptor('20260101120003'),
            $this->descriptor('20260101120004'),
        ]);

        $entries = (new StatusBuilder($locator, $repository, 60))->build();
        $statuses = [];
        foreach ($entries as $entry) {
            $statuses[$entry->version] = $entry->status;
        }

        self::assertSame(LedgerStatus::PENDING, $statuses['20260101120000']);
        self::assertSame(LedgerStatus::SUCCESS, $statuses['20260101120001']);
        self::assertSame(LedgerStatus::FAILED, $statuses['20260101120002']);
        self::assertSame(LedgerStatus::RUNNING, $statuses['20260101120003']);
        self::assertSame(LedgerStatus::STALE_RUNNING, $statuses['20260101120004']);
        self::assertSame(LedgerStatus::MISSING, $statuses['20260101120005']);
        self::assertSame(
            ['20260101120000', '20260101120001', '20260101120002', '20260101120003', '20260101120004', '20260101120005'],
            array_map(static fn ($entry) => $entry->version, $entries),
        );
    }

    public function testKeepsFailureOutputForVerboseReport(): void
    {
        $repository = new InMemoryExecutionRepository();
        $repository->seed($this->record('20260101120000', ExecutionStatus::FAILED, stderr: 'boom'));

        $entries = (new StatusBuilder(
            new FakeCommandFileLocator([$this->descriptor('20260101120000')]),
            $repository,
            60,
        ))->build();

        self::assertSame('boom', $entries[0]->stderr);
    }

    public function testRunningWithoutStartedAtIsReportedAsStale(): void
    {
        $repository = new InMemoryExecutionRepository();
        $repository->seed(new ExecutionRecord(
            1,
            '20260101120000',
            '20260101120000_test.php',
            ExecutionStatus::RUNNING,
            null,
            null,
            null,
            null,
            null,
            null,
        ));

        $entries = (new StatusBuilder(
            new FakeCommandFileLocator([$this->descriptor('20260101120000')]),
            $repository,
            60,
        ))->build();

        self::assertSame(LedgerStatus::STALE_RUNNING, $entries[0]->status);
    }
}
