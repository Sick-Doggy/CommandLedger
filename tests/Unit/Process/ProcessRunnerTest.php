<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Process;

use ArcaCodicum\CommandLedger\Exception\CommandFailedException;
use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;

final class ProcessRunnerTest extends TestCase
{
    private function runner(int $timeout = 5, int $outputLimit = 4096): ProcessRunner
    {
        return new ProcessRunner(
            __DIR__.'/../../Fixtures/console.php',
            PHP_BINARY,
            $timeout,
            $outputLimit,
            'test',
            false,
        );
    }

    public function testSuccessfulCommandReturnsResult(): void
    {
        $result = $this->runner()->execute('test:ok');

        self::assertSame('test:ok', $result->command);
        self::assertSame(0, $result->exitCode);
        self::assertSame("ok\n", $result->stdout);
        self::assertSame('', $result->stderr);
    }

    public function testNonZeroExitThrowsCommandFailedException(): void
    {
        try {
            $this->runner()->execute('test:fail');
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException $exception) {
            self::assertSame('test:fail', $exception->command);
            self::assertSame(3, $exception->exitCode);
            self::assertStringContainsString('boom', $exception->stderr);
        }
    }

    public function testArgumentsAndOptionsArePassedAsSeparateArgvElements(): void
    {
        $result = $this->runner()->execute(
            'test:echo',
            ['hello world', '--weird=value'],
            ['--limit' => '100', '--dry-run' => true, '--skip' => false],
        );

        /** @var list<string> $argv */
        $argv = json_decode(trim($result->stdout), true, 512, JSON_THROW_ON_ERROR);

        self::assertContains('hello world', $argv);
        self::assertContains('--weird=value', $argv);
        self::assertContains('--limit=100', $argv);
        self::assertContains('--dry-run', $argv);
        self::assertNotContains('--skip', $argv);
        self::assertContains('--no-interaction', $argv);
        self::assertContains('--env=test', $argv);
        self::assertContains('--no-debug', $argv);
    }

    public function testTimeoutThrowsCommandFailedException(): void
    {
        $this->expectException(CommandFailedException::class);
        $this->expectExceptionMessage('timed out');

        $this->runner(timeout: 1)->execute('test:sleep', ['2']);
    }

    public function testOutputIsTruncatedToTailWithMarker(): void
    {
        $result = $this->runner(outputLimit: 10)->execute('test:long-output');

        self::assertStringStartsWith("[truncated]\n", $result->stdout);
        self::assertSame("[truncated]\n".str_repeat('a', 9)."\n", $result->stdout);
        self::assertSame(str_repeat('a', 100)."\n", $result->fullStdout);
    }

    public function testFailedCommandKeepsStdout(): void
    {
        try {
            $this->runner()->execute('test:fail-with-output');
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException $exception) {
            self::assertSame("partial output\n", $exception->stdout);
            self::assertStringContainsString('boom', $exception->stderr);
        }
    }

    public function testTimeoutKeepsPartialOutput(): void
    {
        try {
            $this->runner(timeout: 1)->execute('test:slow-output', ['2']);
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException $exception) {
            self::assertSame("started\n", $exception->stdout);
            self::assertStringContainsString('timed out', $exception->stderr);
        }
    }

    public function testTruncationDoesNotSplitUtf8Character(): void
    {
        $result = $this->runner(outputLimit: 8)->execute('test:utf8-output');

        self::assertSame(1, preg_match('//u', $result->stdout));
        self::assertStringStartsWith("[truncated]\n", $result->stdout);
        self::assertStringEndsWith("ą\n", $result->stdout);
        self::assertSame(str_repeat('ą', 40)."\n", $result->fullStdout);
    }

    public function testExecuteScriptRunsBashScriptWithArguments(): void
    {
        $result = $this->runner()->executeScript(
            __DIR__.'/../../Fixtures/scripts/echo_args.sh',
            ['hello world', 'plain'],
        );

        self::assertSame(0, $result->exitCode);
        self::assertSame("hello world\nplain\n", $result->stdout);
    }

    public function testExecuteScriptRejectsMissingFile(): void
    {
        $this->expectException(CommandLedgerException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $this->runner()->executeScript('/definitely/missing-script.sh');
    }

    public function testExecuteScriptFailureThrows(): void
    {
        try {
            $this->runner()->executeScript(__DIR__.'/../../Fixtures/scripts/echo_args.sh', ['fail']);
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException $exception) {
            self::assertSame(3, $exception->exitCode);
            self::assertStringContainsString('script failed', $exception->stderr);
        }
    }

    public function testExecuteBashRunsCommand(): void
    {
        $result = $this->runner()->executeBash('printf "bash-output\n"');

        self::assertSame(0, $result->exitCode);
        self::assertSame("bash-output\n", $result->stdout);
    }

    public function testExecuteBashFailureThrows(): void
    {
        try {
            $this->runner()->executeBash('echo boom >&2; exit 7');
            self::fail('Expected CommandFailedException.');
        } catch (CommandFailedException $exception) {
            self::assertSame(7, $exception->exitCode);
            self::assertStringContainsString('boom', $exception->stderr);
        }
    }

    public function testExecuteBashTimeoutThrows(): void
    {
        $this->expectException(CommandFailedException::class);
        $this->expectExceptionMessage('timed out');

        $this->runner(timeout: 1)->executeBash('sleep 2');
    }

    public function testExecuteScriptDoesNotInterpretShellMetacharactersInArguments(): void
    {
        $result = $this->runner()->executeScript(
            __DIR__.'/../../Fixtures/scripts/echo_args.sh',
            ['a; echo injected', '$(echo sub)'],
        );

        self::assertSame("a; echo injected\n\$(echo sub)\n", $result->stdout);
    }

    public function testExecuteBashInterpretsShellMetacharacters(): void
    {
        $result = $this->runner()->executeBash("printf 'abc' | tr a z");

        self::assertSame('zbc', $result->stdout);
    }
}
