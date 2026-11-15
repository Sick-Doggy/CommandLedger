<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Locator;

use ArcaCodicum\CommandLedger\Exception\DirectoryNotFoundException;
use ArcaCodicum\CommandLedger\Exception\InvalidCommandFileException;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocator;
use PHPUnit\Framework\TestCase;

final class CommandFileLocatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/cl-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    private function touchFile(string $name): void
    {
        file_put_contents($this->directory.'/'.$name, "<?php\n");
    }

    public function testReturnsFilesSortedByVersionAndIgnoresNonPhp(): void
    {
        $this->touchFile('20260101120500_second.php');
        $this->touchFile('20260101120000_first.php');
        $this->touchFile('notes.txt');

        $files = (new CommandFileLocator($this->directory))->locate();

        self::assertCount(2, $files);
        self::assertSame('20260101120000', $files[0]->version());
        self::assertSame('20260101120500', $files[1]->version());
    }

    public function testRejectsFileWithInvalidName(): void
    {
        $this->touchFile('clear_cache.php');

        $this->expectException(InvalidCommandFileException::class);
        $this->expectExceptionMessage('clear_cache.php');

        (new CommandFileLocator($this->directory))->locate();
    }

    public function testRejectsInvalidDate(): void
    {
        $this->touchFile('20261332250000_bad.php');

        $this->expectException(InvalidCommandFileException::class);
        $this->expectExceptionMessage('20261332250000_bad.php');

        (new CommandFileLocator($this->directory))->locate();
    }

    public function testRejectsDuplicateVersionNamingBothFiles(): void
    {
        $this->touchFile('20260101120000_a.php');
        $this->touchFile('20260101120000_b.php');

        try {
            (new CommandFileLocator($this->directory))->locate();
            self::fail('Expected InvalidCommandFileException.');
        } catch (InvalidCommandFileException $exception) {
            self::assertStringContainsString('20260101120000_a.php', $exception->getMessage());
            self::assertStringContainsString('20260101120000_b.php', $exception->getMessage());
        }
    }

    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(DirectoryNotFoundException::class);

        (new CommandFileLocator($this->directory.'/missing'))->locate();
    }

    public function testFindsFilesInDirectoryWithGlobMetacharacters(): void
    {
        $directory = sys_get_temp_dir().'/cl-[x]-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/20260101120000_a.php', "<?php\n");

        try {
            $files = (new CommandFileLocator($directory))->locate();

            self::assertCount(1, $files);
            self::assertSame('20260101120000', $files[0]->version());
        } finally {
            @unlink($directory.'/20260101120000_a.php');
            @rmdir($directory);
        }
    }
}
