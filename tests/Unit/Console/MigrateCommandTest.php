<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Console;

use ArcaCodicum\CommandLedger\Console\MigrateCommand;
use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFile;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLoader;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLocator;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryExecutionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateCommandTest extends TestCase
{
    public function testPrintsExecutedFilesAndReturnsSuccess(): void
    {
        $repository = new InMemoryExecutionRepository();
        $descriptor = new CommandFileDescriptor('/tmp/20260101120000.php', VersionedFileName::forNew('20260101120000', 'test'));
        $file = FakeCommandFile::create(static function (): void {
        }, static function (): void {
        });
        $runner = new LedgerRunner(
            new FakeCommandFileLocator([$descriptor]),
            new FakeCommandFileLoader(['20260101120000' => $file]),
            $repository,
            60,
        );

        $tester = new CommandTester(new MigrateCommand($runner));
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('EXECUTED', $tester->getDisplay());
        self::assertStringContainsString('1 executed', $tester->getDisplay());
    }

    public function testReturnsFailureWhenAFileFails(): void
    {
        $repository = new InMemoryExecutionRepository();
        $descriptor = new CommandFileDescriptor('/tmp/20260101120000.php', VersionedFileName::forNew('20260101120000', 'test'));
        $file = FakeCommandFile::create(
            static function (): void {
                throw new \RuntimeException('boom');
            },
            static function (): void {
            },
        );
        $runner = new LedgerRunner(
            new FakeCommandFileLocator([$descriptor]),
            new FakeCommandFileLoader(['20260101120000' => $file]),
            $repository,
            60,
        );

        $tester = new CommandTester(new MigrateCommand($runner));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('FAILED', $tester->getDisplay());
    }

    public function testFailureMessageWithConsoleMarkupIsPrintedRaw(): void
    {
        $repository = new InMemoryExecutionRepository();
        $descriptor = new CommandFileDescriptor('/tmp/20260101120000.php', VersionedFileName::forNew('20260101120000', 'test'));
        $file = FakeCommandFile::create(
            static function (): void {
                throw new \RuntimeException('<fg=bogus>boom</>');
            },
            static function (): void {
            },
        );
        $runner = new LedgerRunner(
            new FakeCommandFileLocator([$descriptor]),
            new FakeCommandFileLoader(['20260101120000' => $file]),
            $repository,
            60,
        );

        $tester = new CommandTester(new MigrateCommand($runner));
        $status = $tester->execute([]);

        self::assertSame(1, $status);
        self::assertStringContainsString('FAILED', $tester->getDisplay());
        self::assertStringContainsString('<fg=bogus>boom</>', $tester->getDisplay());
    }
}
