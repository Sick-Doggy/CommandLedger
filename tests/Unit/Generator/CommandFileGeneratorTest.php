<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Generator;

use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Generator\CommandFileGenerator;
use PHPUnit\Framework\TestCase;

final class CommandFileGeneratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/cl-gen-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    private function generator(?string $directory = null): CommandFileGenerator
    {
        return new CommandFileGenerator(
            $directory ?? $this->directory,
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-01-01 12:00:00'),
        );
    }

    public function testNormalizesNameAndGeneratesFile(): void
    {
        $path = $this->generator()->generate('Import Products');

        self::assertSame($this->directory.'/20260101120000_import_products.php', $path);
        self::assertFileExists($path);
        $content = (string) file_get_contents($path);
        self::assertStringContainsString('declare(strict_types=1);', $content);
        self::assertStringContainsString('final class Version20260101120000ImportProducts extends AbstractCommandFile', $content);
        self::assertStringContainsString('public function up(): void', $content);
        self::assertStringContainsString('public function down(): void', $content);
    }

    public function testRejectsEmptyAndDigitPrefixedNames(): void
    {
        $generator = $this->generator();

        foreach (['', '   ', '123bad'] as $name) {
            try {
                $generator->generate($name);
                self::fail('Expected CommandLedgerException for name '.$name);
            } catch (CommandLedgerException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCollisionIncrementsVersionByOneSecond(): void
    {
        touch($this->directory.'/20260101120000_other.php');

        $path = $this->generator()->generate('next');

        self::assertSame($this->directory.'/20260101120001_next.php', $path);
    }

    public function testCreatesMissingDirectory(): void
    {
        $nested = $this->directory.'/nested';

        $path = $this->generator($nested)->generate('nested_command');

        self::assertFileExists($path);
        self::assertDirectoryExists($nested);
    }

    public function testNeverOverwritesExistingFile(): void
    {
        $generator = $this->generator();
        $first = $generator->generate('same');
        $second = $generator->generate('same');

        self::assertNotSame($first, $second);
        self::assertFileExists($first);
        self::assertFileExists($second);
        self::assertSame($this->directory.'/20260101120001_same.php', $second);
    }

    public function testWorksInDirectoryWithGlobMetacharacters(): void
    {
        $directory = sys_get_temp_dir().'/cl-gen-[x]-'.bin2hex(random_bytes(4));
        mkdir($directory);
        touch($directory.'/20260101120000_same.php');

        try {
            $path = $this->generator($directory)->generate('same');

            self::assertSame($directory.'/20260101120001_same.php', $path);
            self::assertFileExists($directory.'/20260101120000_same.php');
        } finally {
            @unlink($directory.'/20260101120001_same.php');
            @unlink($directory.'/20260101120000_same.php');
            @rmdir($directory);
        }
    }

    public function testCreatesDirectoryWithoutWorldWritePermission(): void
    {
        $nested = $this->directory.'/nested-perms';
        $previousUmask = umask(0);

        try {
            $this->generator($nested)->generate('perms');
        } finally {
            umask($previousUmask);
        }

        self::assertSame(0755, fileperms($nested) & 0777);

        @unlink($nested.'/20260101120000_perms.php');
        @rmdir($nested);
    }
}
