<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Console;

use ArcaCodicum\CommandLedger\Console\SetupCommand;
use ArcaCodicum\CommandLedger\Tests\Fake\InMemoryExecutionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SetupCommandTest extends TestCase
{
    public function testCreatesMissingTable(): void
    {
        $repository = new InMemoryExecutionRepository(false);
        $tester = new CommandTester(new SetupCommand($repository));

        self::assertSame(0, $tester->execute([]));
        self::assertTrue($repository->tableExists());
        self::assertStringContainsString('created', strtolower($tester->getDisplay()));
    }

    public function testReportsExistingTable(): void
    {
        $repository = new InMemoryExecutionRepository(true);
        $tester = new CommandTester(new SetupCommand($repository));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }
}
