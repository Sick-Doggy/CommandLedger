<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Console;

use ArcaCodicum\CommandLedger\Console\StatusCommand;
use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRecord;
use ArcaCodicum\CommandLedger\Persistence\ExecutionStatus;
use ArcaCodicum\CommandLedger\Runner\StatusBuilder;
use ArcaCodicum\CommandLedger\Tests\Fake\FakeCommandFileLocator;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryExecutionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class StatusCommandTest extends TestCase
{
    private function tester(): CommandTester
    {
        $repository = new InMemoryExecutionRepository();
        $now = new \DateTimeImmutable();
        $repository->seed(new ExecutionRecord(1, '20260101120000', '20260101120000_test.php', ExecutionStatus::FAILED, 3, 'out', 'boom', $now, $now, 5));

        return new CommandTester(new StatusCommand(new StatusBuilder(
            new FakeCommandFileLocator([
                new CommandFileDescriptor('/tmp/20260101120000.php', VersionedFileName::forNew('20260101120000', 'test')),
                new CommandFileDescriptor('/tmp/20260101120001.php', VersionedFileName::forNew('20260101120001', 'test')),
            ]),
            $repository,
            60,
        )));
    }

    public function testPrintsTableWithStatuses(): void
    {
        $tester = $this->tester();
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('failed', $tester->getDisplay());
        self::assertStringContainsString('pending', $tester->getDisplay());
        self::assertStringContainsString('1 failed', $tester->getDisplay());
        self::assertStringContainsString('1 pending', $tester->getDisplay());
    }

    public function testVerboseShowsCapturedOutputForFailure(): void
    {
        $tester = $this->tester();
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertStringContainsString('boom', $tester->getDisplay());
        self::assertStringContainsString('out', $tester->getDisplay());
    }

    public function testVerboseWritesCapturedOutputRaw(): void
    {
        $repository = new InMemoryExecutionRepository();
        $now = new \DateTimeImmutable();
        $repository->seed(new ExecutionRecord(
            1,
            '20260101120000',
            '20260101120000_test.php',
            ExecutionStatus::FAILED,
            3,
            '<info>important</info>',
            '<fg=bogus>boom</>',
            $now,
            $now,
            5,
        ));

        $command = new StatusCommand(new StatusBuilder(
            new FakeCommandFileLocator([
                new CommandFileDescriptor('/tmp/20260101120000.php', VersionedFileName::forNew('20260101120000', 'test')),
            ]),
            $repository,
            60,
        ));

        $tester = new CommandTester($command);
        $status = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(0, $status);
        self::assertStringContainsString('<info>important</info>', $tester->getDisplay());
        self::assertStringContainsString('<fg=bogus>boom</>', $tester->getDisplay());
    }
}
