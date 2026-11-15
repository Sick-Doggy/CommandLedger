<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\CommandResult;
use ArcaCodicum\CommandLedger\Exception\CommandFailedException;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;

final class AbstractCommandFileTest extends TestCase
{
    private function runner(): ProcessRunner
    {
        return new ProcessRunner(
            __DIR__.'/../Fixtures/console.php',
            PHP_BINARY,
            5,
            4096,
            'test',
            false,
        );
    }

    public function testExecuteRecordsSuccessfulResult(): void
    {
        $file = new class ($this->runner()) extends AbstractCommandFile {
            public function up(): void
            {
                $this->execute('test:ok');
            }

            public function down(): void
            {
            }
        };

        $file->up();

        self::assertCount(1, $file->results());
        self::assertInstanceOf(CommandResult::class, $file->results()[0]);
        self::assertSame(0, $file->results()[0]->exitCode);
    }

    public function testFailedExecuteThrowsAndDoesNotRecordResult(): void
    {
        $file = new class ($this->runner()) extends AbstractCommandFile {
            public function up(): void
            {
                $this->execute('test:fail');
            }

            public function down(): void
            {
            }
        };

        try {
            $file->up();
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException) {
            self::assertCount(0, $file->results());
        }
    }

    public function testExecuteScriptAndExecuteBashRecordResults(): void
    {
        $file = new class ($this->runner()) extends AbstractCommandFile {
            public function up(): void
            {
                $this->executeScript(__DIR__.'/../Fixtures/scripts/echo_args.sh', ['one']);
                $this->executeBash('printf two');
            }

            public function down(): void
            {
            }
        };

        $file->up();

        self::assertCount(2, $file->results());
        self::assertSame("one\n", $file->results()[0]->stdout);
        self::assertSame('two', $file->results()[1]->stdout);
    }

    public function testFailedExecuteBashDoesNotRecordResult(): void
    {
        $file = new class ($this->runner()) extends AbstractCommandFile {
            public function up(): void
            {
                $this->executeBash('exit 5');
            }

            public function down(): void
            {
            }
        };

        try {
            $file->up();
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException) {
            self::assertCount(0, $file->results());
        }
    }
}
