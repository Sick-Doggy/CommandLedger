<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Console;

use ArcaCodicum\CommandLedger\Console\RollbackCommand;
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
use Symfony\Component\Console\Tester\CommandTester;

final class RollbackCommandTest extends TestCase
{
    private function command(InMemoryExecutionRepository $repository): RollbackCommand
    {
        $version = '20260101120000';
        $descriptor = new CommandFileDescriptor('/tmp/'.$version.'.php', VersionedFileName::forNew($version, 'test'));
        $file = FakeCommandFile::create(static function (): void {
        }, static function (): void {
        });
        $runner = new LedgerRunner(
            new FakeCommandFileLocator([$descriptor]),
            new FakeCommandFileLoader([$version => $file]),
            $repository,
            60,
        );

        return new RollbackCommand($runner);
    }

    public function testRollsBackAndReportsVersion(): void
    {
        $repository = new InMemoryExecutionRepository();
        $at = new \DateTimeImmutable('2026-01-01 12:00:00');
        $repository->seed(new ExecutionRecord(1, '20260101120000', 'a.php', ExecutionStatus::SUCCESS, 0, '', '', $at, $at, 0));

        $tester = new CommandTester($this->command($repository));
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('REVERTED', $tester->getDisplay());
        self::assertSame([], $repository->records());
    }

    public function testRejectsBothStepsAndTo(): void
    {
        $tester = new CommandTester($this->command(new InMemoryExecutionRepository()));

        self::assertSame(1, $tester->execute(['--steps' => '1', '--to' => '20260101120000']));
        self::assertStringContainsString('either --steps or --to', $tester->getDisplay());
    }

    public function testReportsNothingToRollBack(): void
    {
        $tester = new CommandTester($this->command(new InMemoryExecutionRepository()));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Nothing to roll back', $tester->getDisplay());
    }

    public function testPrintsAlreadyRevertedVersionsBeforeFailure(): void
    {
        $repository = new InMemoryExecutionRepository();
        $at = new \DateTimeImmutable('2026-01-01 12:00:00');

        foreach (['20260101120000', '20260101120001'] as $version) {
            $repository->seed(new ExecutionRecord(1, $version, $version.'_test.php', ExecutionStatus::SUCCESS, 0, '', '', $at, $at, 0));
        }

        $descriptors = [
            new CommandFileDescriptor('/tmp/20260101120000.php', VersionedFileName::forNew('20260101120000', 'test')),
            new CommandFileDescriptor('/tmp/20260101120001.php', VersionedFileName::forNew('20260101120001', 'test')),
        ];
        $files = [
            '20260101120001' => FakeCommandFile::create(static function (): void {
            }, static function (): void {
            }),
            '20260101120000' => FakeCommandFile::create(
                static function (): void {
                },
                static function (): void {
                    throw new \RuntimeException('down failed');
                },
            ),
        ];

        $runner = new LedgerRunner(
            new FakeCommandFileLocator($descriptors),
            new FakeCommandFileLoader($files),
            $repository,
            60,
        );

        $tester = new CommandTester(new RollbackCommand($runner));
        $status = $tester->execute(['--steps' => '2']);

        self::assertSame(1, $status);
        self::assertStringContainsString('REVERTED', $tester->getDisplay());
        self::assertStringContainsString('20260101120001', $tester->getDisplay());
        self::assertStringContainsString('down failed', $tester->getDisplay());
    }
}
