<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Locator;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\Exception\InvalidCommandFileException;
use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\CommandFileLoader;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;

final class CommandFileLoaderTest extends TestCase
{
    private function loader(): CommandFileLoader
    {
        return new CommandFileLoader(new ProcessRunner(
            __DIR__.'/../../Fixtures/console.php',
            PHP_BINARY,
            5,
            4096,
            'test',
            false,
        ));
    }

    private function descriptor(string $relativePath): CommandFileDescriptor
    {
        $path = __DIR__.'/../../Fixtures/command-files/'.$relativePath;
        $name = VersionedFileName::parse(basename($path));
        self::assertNotNull($name);

        return new CommandFileDescriptor($path, $name);
    }

    public function testLoadsValidFileAndRunsUp(): void
    {
        $file = $this->loader()->load($this->descriptor('valid/20260101120000_ok.php'));

        $file->up();

        self::assertCount(1, $file->results());
        self::assertSame(0, $file->results()[0]->exitCode);
    }

    public function testRejectsFileWhoseClassDoesNotMatchName(): void
    {
        $this->expectException(InvalidCommandFileException::class);
        $this->expectExceptionMessage('Version20260101120001Wrongname');

        $this->loader()->load($this->descriptor('invalid-class-name/20260101120001_wrongname.php'));
    }

    public function testRejectsClassNotExtendingAbstractCommandFile(): void
    {
        $this->expectException(InvalidCommandFileException::class);
        $this->expectExceptionMessage('must extend');

        $this->loader()->load($this->descriptor('not-extending/20260101120002_wrong.php'));
    }

    public function testWrapsParseErrors(): void
    {
        $this->expectException(InvalidCommandFileException::class);
        $this->expectExceptionMessage('Could not load');

        $this->loader()->load($this->descriptor('broken/20260101120003_broken.php'));
    }

    public function testRejectsFileWithoutExpectedClass(): void
    {
        $this->expectException(InvalidCommandFileException::class);
        $this->expectExceptionMessage('Version20260101120004Missing');

        $this->loader()->load($this->descriptor('missing-class/20260101120004_missing.php'));
    }

    public function testIsolatesIncludedFileVariableScope(): void
    {
        $file = $this->loader()->load($this->descriptor('variable-shadow/20260101120005_shadow.php'));

        self::assertInstanceOf(AbstractCommandFile::class, $file);
        $file->up();
        self::assertCount(0, $file->results());
    }
}
